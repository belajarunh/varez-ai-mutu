<?php

return [
    'disk' => env('DOCUMENTS_DISK', 'local'),
    'max_file_bytes' => 26_214_400,
    'max_archive_files' => 3000,
    'max_archive_expanded_bytes' => 104_857_600,
    'max_pages' => 500,
    'max_spreadsheet_rows' => 50_000,
    'max_spreadsheet_columns' => 200,
    'scanner_binary' => env('DOCUMENT_SCANNER_BINARY'),
    'ocr_binary' => env('DOCUMENT_OCR_BINARY'),
    'ocr_language' => env('DOCUMENT_OCR_LANGUAGE', 'ind+eng'),
];
