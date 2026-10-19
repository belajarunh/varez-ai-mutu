<?php

namespace App\Services\Performance;

use Generator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

class PerformanceImportFileReader
{
    public const MAX_FILE_BYTES = 10_485_760;

    public const MAX_ROWS = 50_000;

    public const MAX_COLUMNS = 100;

    private const MAX_XLSX_EXPANDED_BYTES = 52_428_800;

    /**
     * @return list<string>
     */
    public function headers(string $path, string $sourceType): array
    {
        $this->assertFile($path, $sourceType);
        $row = $sourceType === 'csv'
            ? $this->readCsvHeader($path)
            : $this->readXlsxHeader($path);

        return $this->normalizeHeaders($row);
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    public function rows(string $path, string $sourceType): Generator
    {
        $this->assertFile($path, $sourceType);

        if ($sourceType === 'csv') {
            yield from $this->csvRows($path);

            return;
        }

        yield from $this->xlsxRows($path);
    }

    private function assertFile(string $path, string $sourceType): void
    {
        if (! in_array($sourceType, ['csv', 'xlsx'], true) || ! is_file($path)) {
            throw ValidationException::withMessages(['file' => 'Format file tidak didukung atau file tidak ditemukan.']);
        }

        $size = filesize($path);
        if ($size === false || $size > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages(['file' => 'Ukuran file melebihi batas 10 MB.']);
        }

        if ($sourceType === 'xlsx') {
            $this->assertSafeXlsxArchive($path);
        }
    }

    private function assertSafeXlsxArchive(string $path): void
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Berkas XLSX tidak valid.']);
        }

        $expandedBytes = 0;
        try {
            if ($archive->numFiles > 1_000) {
                throw ValidationException::withMessages(['file' => 'Berkas XLSX memiliki terlalu banyak bagian.']);
            }

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                if ($stat === false) {
                    throw ValidationException::withMessages(['file' => 'Struktur XLSX tidak dapat dibaca.']);
                }

                $expandedBytes += $stat['size'];
                if ($expandedBytes > self::MAX_XLSX_EXPANDED_BYTES) {
                    throw ValidationException::withMessages(['file' => 'Ukuran hasil ekstraksi XLSX melebihi batas aman.']);
                }
            }
        } finally {
            $archive->close();
        }
    }

    /**
     * @return list<string|null>
     */
    private function readCsvHeader(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Berkas CSV tidak dapat dibuka.']);
        }

        try {
            $row = fgetcsv($handle, null, ',', '"', '');
            if (! is_array($row)) {
                throw ValidationException::withMessages(['file' => 'Berkas CSV tidak memiliki baris header.']);
            }

            return $row;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<mixed>
     */
    private function readXlsxHeader(string $path): array
    {
        $spreadsheet = $this->loadSpreadsheet($path);
        try {
            $worksheet = $spreadsheet->getSheet(0);
            $this->assertWorksheetSize($worksheet);

            return $this->worksheetRow($worksheet, 1);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeHeaders(array $row): array
    {
        if ($row === [] || count($row) > self::MAX_COLUMNS) {
            throw ValidationException::withMessages(['file' => 'Header wajib memiliki 1 sampai 100 kolom.']);
        }

        $headers = [];
        foreach ($row as $index => $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                throw ValidationException::withMessages(['file' => 'Header file harus berupa teks UTF-8.']);
            }

            $header = trim((string) $value);
            if ($index === 0) {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
            }
            if ($header === '' || ! mb_check_encoding($header, 'UTF-8')) {
                throw ValidationException::withMessages(['file' => 'Header kosong atau encoding bukan UTF-8.']);
            }
            if (in_array($header, $headers, true)) {
                throw ValidationException::withMessages(['file' => "Header duplikat: {$header}."]);
            }
            $headers[] = $header;
        }

        return $headers;
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    private function csvRows(string $path): Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Berkas CSV tidak dapat dibuka.']);
        }

        try {
            fgetcsv($handle, null, ',', '"', '');
            $rowNumber = 1;
            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $rowNumber++;
                if ($rowNumber > self::MAX_ROWS + 1) {
                    throw ValidationException::withMessages(['file' => 'File melebihi batas 50.000 baris data.']);
                }
                foreach ($row as $value) {
                    if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                        throw ValidationException::withMessages(['file' => "Encoding bukan UTF-8 pada baris {$rowNumber}."]);
                    }
                }
                yield $rowNumber => $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    private function xlsxRows(string $path): Generator
    {
        $spreadsheet = $this->loadSpreadsheet($path);
        try {
            $worksheet = $spreadsheet->getSheet(0);
            $this->assertWorksheetSize($worksheet);

            for ($rowNumber = 2; $rowNumber <= $worksheet->getHighestDataRow(); $rowNumber++) {
                yield $rowNumber => $this->worksheetRow($worksheet, $rowNumber);
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function loadSpreadsheet(string $path): Spreadsheet
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            return $reader->load($path);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['file' => 'Berkas XLSX rusak atau tidak dapat diproses.']);
        }
    }

    private function assertWorksheetSize(Worksheet $worksheet): void
    {
        if ($worksheet->getHighestDataRow() > self::MAX_ROWS + 1) {
            throw ValidationException::withMessages(['file' => 'File melebihi batas 50.000 baris data.']);
        }
        if (Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()) > self::MAX_COLUMNS) {
            throw ValidationException::withMessages(['file' => 'File melebihi batas 100 kolom.']);
        }
    }

    /**
     * @return list<mixed>
     */
    private function worksheetRow(Worksheet $worksheet, int $rowNumber): array
    {
        $lastColumn = min(
            Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()),
            self::MAX_COLUMNS,
        );
        $row = [];
        for ($column = 1; $column <= $lastColumn; $column++) {
            $cell = $worksheet->getCell([$column, $rowNumber]);
            $value = $cell->getValue();
            if (is_numeric($value) && Date::isDateTime($cell)) {
                $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                throw ValidationException::withMessages(['file' => "Encoding bukan UTF-8 pada baris {$rowNumber}."]);
            }
            $row[] = $value;
        }

        return $row;
    }
}
