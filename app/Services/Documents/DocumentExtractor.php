<?php

namespace App\Services\Documents;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use ZipArchive;

class DocumentExtractor
{
    /**
     * @return array{pages: list<array{page: int|null, section: string|null, text: string}>, page_count: int, parser: string, warnings: list<string>, needs_review: bool}
     */
    public function extract(string $path, string $extension): array
    {
        return match ($extension) {
            'pdf' => $this->extractPdf($path),
            'docx' => $this->extractDocx($path),
            'xlsx' => $this->extractXlsx($path),
            'pptx' => $this->extractPptx($path),
            'csv' => $this->extractCsv($path),
            'jpg', 'jpeg', 'png', 'webp' => $this->extractImage($path),
            default => throw ValidationException::withMessages(['file' => 'Parser untuk format ini tidak tersedia.']),
        };
    }

    /**
     * @return array{pages: list<array{page: int|null, section: string|null, text: string}>, page_count: int, parser: string, warnings: list<string>, needs_review: bool}
     */
    private function extractPdf(string $path): array
    {
        $document = (new Parser)->parseFile($path);
        $pages = $document->getPages();
        if (count($pages) > config('documents.max_pages')) {
            throw ValidationException::withMessages(['file' => 'PDF melebihi batas 500 halaman.']);
        }

        $result = [];
        $hasTextlessPage = false;
        foreach (array_values($pages) as $index => $page) {
            $text = $page->getText();
            $hasTextlessPage = $hasTextlessPage || trim($text) === '';
            $result[] = ['page' => $index + 1, 'section' => null, 'text' => $text];
        }

        return [
            'pages' => $result,
            'page_count' => count($pages),
            'parser' => 'smalot/pdfparser',
            'warnings' => $hasTextlessPage ? ['Satu atau lebih halaman PDF tidak menghasilkan teks dan memerlukan pemeriksaan.'] : [],
            'needs_review' => $hasTextlessPage,
        ];
    }

    /**
     * @return array{pages: list<array{page: int|null, section: string|null, text: string}>, page_count: int, parser: string, warnings: list<string>, needs_review: bool}
     */
    private function extractDocx(string $path): array
    {
        $archive = $this->openArchive($path);
        $hasEmbeddedImages = false;
        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                if (is_array($stat) && str_starts_with($stat['name'], 'word/media/')) {
                    $hasEmbeddedImages = true;
                    break;
                }
            }
            $xml = $archive->getFromName('word/document.xml');
        } finally {
            $archive->close();
        }
        if (! is_string($xml)) {
            throw ValidationException::withMessages(['file' => 'Konten DOCX tidak dapat dibaca.']);
        }

        $document = $this->parseXml($xml);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = $xpath->query('//w:body//w:p');
        if ($paragraphs === false) {
            throw ValidationException::withMessages(['file' => 'Struktur DOCX tidak dapat diproses.']);
        }

        $section = null;
        $pages = [];
        foreach ($paragraphs as $paragraph) {
            $text = $this->xmlText($xpath, $paragraph, 'w:t');
            if (trim($text) === '') {
                continue;
            }
            $styleNodes = $xpath->query('./w:pPr/w:pStyle/@w:val', $paragraph);
            $style = $styleNodes === false ? null : $styleNodes->item(0)?->nodeValue;
            if (is_string($style) && preg_match('/^Heading[1-6]$/i', $style)) {
                $section = $text;
            }
            $pages[] = ['page' => null, 'section' => $section, 'text' => $text];
        }

        return [
            'pages' => $pages,
            'page_count' => 0,
            'parser' => 'ooxml-docx',
            'warnings' => array_filter([
                'Nomor halaman tidak tersedia dari parser DOCX.',
                $hasEmbeddedImages ? 'Gambar tertanam tidak di-OCR dan memerlukan pemeriksaan manual.' : null,
            ]),
            'needs_review' => $hasEmbeddedImages,
        ];
    }

    /**
     * @return array{pages: list<array{page: int|null, section: string|null, text: string}>, page_count: int, parser: string, warnings: list<string>, needs_review: bool}
     */
    private function extractXlsx(string $path): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        try {
            if (count($spreadsheet->getAllSheets()) > config('documents.max_pages')) {
                throw ValidationException::withMessages(['file' => 'Workbook memiliki terlalu banyak sheet.']);
            }
            $pages = [];
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $rows = $sheet->getHighestRow();
                $columns = Coordinate::columnIndexFromString($sheet->getHighestColumn());
                if ($rows > config('documents.max_spreadsheet_rows') || $columns > config('documents.max_spreadsheet_columns')) {
                    throw ValidationException::withMessages(['file' => 'Sheet melebihi batas baris atau kolom yang dapat diproses.']);
                }

                for ($row = 1; $row <= $rows; $row++) {
                    $values = [];
                    for ($column = 1; $column <= $columns; $column++) {
                        $value = $sheet->getCell([$column, $row])->getValue();
                        if (is_scalar($value) && (string) $value !== '') {
                            $values[] = (string) $value;
                        }
                    }
                    if ($values !== []) {
                        $pages[] = ['page' => null, 'section' => $sheet->getTitle(), 'text' => implode(' | ', $values)];
                    }
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return [
            'pages' => $pages,
            'page_count' => 0,
            'parser' => 'phpoffice/phpspreadsheet',
            'warnings' => ['Nomor halaman tidak berlaku untuk workbook spreadsheet.'],
            'needs_review' => false,
        ];
    }

    /**
     * @return array{pages: list<array{page: int|null, section: string|null, text: string}>, page_count: int, parser: string, warnings: list<string>, needs_review: bool}
     */
    private function extractPptx(string $path): array
    {
        $archive = $this->openArchive($path);
        try {
            $slideNames = [];
            $hasEmbeddedImages = false;
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                if (is_array($stat)) {
                    if (preg_match('~^ppt/slides/slide[0-9]+\.xml$~', $stat['name'])) {
                        $slideNames[] = $stat['name'];
                    }
                    if (str_starts_with($stat['name'], 'ppt/media/')) {
                        $hasEmbeddedImages = true;
                    }
                }
            }
            usort($slideNames, fn (string $left, string $right): int => $this->slideNumber($left) <=> $this->slideNumber($right));
            if (count($slideNames) > config('documents.max_pages')) {
                throw ValidationException::withMessages(['file' => 'Presentasi melebihi batas 500 slide.']);
            }

            $pages = [];
            foreach ($slideNames as $index => $name) {
                $xml = $archive->getFromName($name);
                if (! is_string($xml)) {
                    throw ValidationException::withMessages(['file' => 'Konten slide tidak dapat dibaca.']);
                }
                $document = $this->parseXml($xml);
                $xpath = new \DOMXPath($document);
                $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $paragraphs = $xpath->query('//a:p');
                if ($paragraphs === false) {
                    throw ValidationException::withMessages(['file' => 'Struktur slide tidak dapat diproses.']);
                }
                $texts = [];
                foreach ($paragraphs as $paragraph) {
                    $text = $this->xmlText($xpath, $paragraph, 'a:t');
                    if (trim($text) !== '') {
                        $texts[] = $text;
                    }
                }
                if ($texts !== []) {
                    $pages[] = ['page' => $index + 1, 'section' => null, 'text' => implode("\n", $texts)];
                }
            }
        } finally {
            $archive->close();
        }

        return [
            'pages' => $pages,
            'page_count' => count($slideNames),
            'parser' => 'ooxml-pptx',
            'warnings' => $hasEmbeddedImages ? ['Gambar slide tidak di-OCR dan memerlukan pemeriksaan manual.'] : [],
            'needs_review' => $hasEmbeddedImages,
        ];
    }

    /**
     * @return array{pages: list<array{page: int|null, section: string|null, text: string}>, page_count: int, parser: string, warnings: list<string>, needs_review: bool}
     */
    private function extractCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'File CSV tidak dapat dibuka.']);
        }
        $pages = [];
        try {
            $rowNumber = 0;
            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $rowNumber++;
                if ($rowNumber > config('documents.max_spreadsheet_rows')) {
                    throw ValidationException::withMessages(['file' => 'CSV melebihi batas 50.000 baris.']);
                }
                if (count($row) > config('documents.max_spreadsheet_columns')) {
                    throw ValidationException::withMessages(['file' => 'CSV melebihi batas 200 kolom.']);
                }
                $values = array_filter($row, fn (mixed $value): bool => is_string($value) && trim($value) !== '');
                if ($values !== []) {
                    $pages[] = ['page' => null, 'section' => null, 'text' => implode(' | ', $values)];
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'pages' => $pages,
            'page_count' => 0,
            'parser' => 'php-csv',
            'warnings' => ['Nomor halaman tidak berlaku untuk CSV.'],
            'needs_review' => false,
        ];
    }

    /**
     * @return array{pages: list<array{page: int|null, section: string|null, text: string}>, page_count: int, parser: string, warnings: list<string>, needs_review: bool}
     */
    private function extractImage(string $path): array
    {
        $binary = config('documents.ocr_binary');
        if (! is_string($binary) || trim($binary) === '') {
            return [
                'pages' => [],
                'page_count' => 1,
                'parser' => 'ocr-unavailable',
                'warnings' => ['OCR tidak dikonfigurasi; gambar memerlukan pemeriksaan manual.'],
                'needs_review' => true,
            ];
        }

        $process = new Process([
            $binary,
            $path,
            'stdout',
            '-l',
            (string) config('documents.ocr_language', 'ind+eng'),
            '--psm',
            '3',
        ]);
        $process->setTimeout(90);
        $process->run();
        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages(['file' => 'OCR gagal memproses gambar.']);
        }

        return [
            'pages' => [['page' => 1, 'section' => null, 'text' => $process->getOutput()]],
            'page_count' => 1,
            'parser' => 'tesseract',
            'warnings' => [],
            'needs_review' => false,
        ];
    }

    private function parseXml(string $xml): \DOMDocument
    {
        if (strlen($xml) > 20_000_000 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw ValidationException::withMessages(['file' => 'Bagian XML melebihi batas atau berisi deklarasi yang dilarang.']);
        }

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                throw ValidationException::withMessages(['file' => 'XML dokumen rusak atau tidak valid.']);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function xmlText(\DOMXPath $xpath, \DOMNode $context, string $query): string
    {
        $nodes = $xpath->query('.//'.$query, $context);
        if ($nodes === false) {
            return '';
        }
        $parts = [];
        foreach ($nodes as $node) {
            $parts[] = $node->textContent;
        }

        return implode('', $parts);
    }

    private function openArchive(string $path): ZipArchive
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Arsip dokumen tidak dapat dibuka.']);
        }

        return $archive;
    }

    private function slideNumber(string $name): int
    {
        return preg_match('/slide([0-9]+)\.xml$/', $name, $matches) === 1 ? (int) $matches[1] : 0;
    }
}
