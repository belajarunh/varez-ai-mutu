<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DocumentFileInspector
{
    private const TYPES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'csv' => 'text/csv',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * @return array{extension: string, mime_type: string, size_bytes: int}
     */
    public function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());
        $size = $file->getSize();

        if (! is_string($path) || ! is_file($path) || ! isset(self::TYPES[$extension])) {
            throw ValidationException::withMessages(['file' => 'Jenis file tidak didukung.']);
        }
        if (! is_int($size) || $size < 1 || $size > config('documents.max_file_bytes')) {
            throw ValidationException::withMessages(['file' => 'Ukuran dokumen harus antara 1 byte dan 25 MB.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($mime) || ! $this->matchesSignature($path, $extension, $mime)) {
            throw ValidationException::withMessages(['file' => 'Tipe atau signature file tidak sesuai dengan ekstensi yang dipilih.']);
        }

        if (in_array($extension, ['docx', 'xlsx', 'pptx'], true)) {
            $this->inspectOfficeArchive($path, $extension);
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $image = getimagesize($path);
            if ($image === false || $image[0] < 1 || $image[1] < 1 || $image[0] > 20_000 || $image[1] > 20_000) {
                throw ValidationException::withMessages(['file' => 'Dimensi gambar tidak valid atau melebihi batas aman.']);
            }
        }

        return ['extension' => $extension, 'mime_type' => self::TYPES[$extension], 'size_bytes' => $size];
    }

    private function matchesSignature(string $path, string $extension, string $mime): bool
    {
        if ($extension === 'pdf') {
            $handle = fopen($path, 'rb');
            $signature = $handle === false ? false : fread($handle, 5);
            if (is_resource($handle)) {
                fclose($handle);
            }

            return $mime === 'application/pdf' && $signature === '%PDF-';
        }

        if (in_array($extension, ['docx', 'xlsx', 'pptx'], true)) {
            return in_array($mime, [
                'application/zip',
                'application/x-zip',
                'application/x-zip-compressed',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ], true);
        }

        if ($extension === 'csv') {
            if (! in_array($mime, ['text/plain', 'text/csv', 'application/csv'], true)) {
                return false;
            }
            $contents = file_get_contents($path);

            return is_string($contents)
                && ! str_contains($contents, "\0")
                && mb_check_encoding($contents, 'UTF-8');
        }

        $image = getimagesize($path);
        $expectedMime = self::TYPES[$extension];

        return $mime === $expectedMime && is_array($image) && $image['mime'] === $expectedMime;
    }

    private function inspectOfficeArchive(string $path, string $extension): void
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Arsip dokumen Office rusak atau tidak valid.']);
        }

        $expandedBytes = 0;
        $names = [];
        try {
            if ($archive->numFiles < 1 || $archive->numFiles > config('documents.max_archive_files')) {
                throw ValidationException::withMessages(['file' => 'Arsip Office memiliki jumlah bagian yang tidak aman.']);
            }

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                if ($stat === false) {
                    throw ValidationException::withMessages(['file' => 'Struktur arsip Office tidak dapat dibaca.']);
                }
                $name = str_replace('\\', '/', $stat['name']);
                if (str_starts_with($name, '/') || preg_match('~(^|/)\.\.(/|$)~', $name)) {
                    throw ValidationException::withMessages(['file' => 'Arsip Office memiliki nama bagian yang tidak aman.']);
                }
                if (preg_match('/(?:^|\/)vbaProject\.bin$/i', $name)
                    || preg_match('~/(?:embeddings|activex)/~i', '/'.$name)) {
                    throw ValidationException::withMessages(['file' => 'Dokumen bermakro atau berisi objek tertanam tidak diizinkan.']);
                }

                $names[$name] = true;
                $expandedBytes += $stat['size'];
                if ($expandedBytes > config('documents.max_archive_expanded_bytes')) {
                    throw ValidationException::withMessages(['file' => 'Ukuran hasil ekstraksi arsip melebihi batas aman.']);
                }
            }

            $requiredPart = match ($extension) {
                'docx' => 'word/document.xml',
                'xlsx' => 'xl/workbook.xml',
                'pptx' => 'ppt/presentation.xml',
                default => throw ValidationException::withMessages(['file' => 'Format arsip Office tidak didukung.']),
            };
            if (! isset($names['[Content_Types].xml'], $names[$requiredPart])) {
                throw ValidationException::withMessages(['file' => 'Signature internal tidak sesuai format Office yang dipilih.']);
            }
        } finally {
            $archive->close();
        }
    }
}
