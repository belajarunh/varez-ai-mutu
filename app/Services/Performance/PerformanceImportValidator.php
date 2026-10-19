<?php

namespace App\Services\Performance;

use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\PerformanceDatasetSchema;
use App\Models\PerformanceRecord;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PerformanceImportValidator
{
    private const VALIDATION_CHUNK_SIZE = 500;

    public function __construct(private readonly PerformanceImportFileReader $reader) {}

    public function validateBatch(ImportBatch $batch): void
    {
        $path = $this->verifiedFilePath($batch);
        $schema = $batch->schema;
        if (! $schema instanceof PerformanceDatasetSchema) {
            throw ValidationException::withMessages(['schema' => 'Schema dataset batch tidak ditemukan.']);
        }

        $headers = $this->reader->headers($path, $batch->source_type);
        $mapping = $this->validatedMapping($schema, $headers, $batch->mapping_json ?? []);
        $fieldMap = $this->fieldMap($schema);
        $headerIndexes = array_flip($headers);
        ImportError::query()->where('import_batch_id', $batch->getKey())->delete();
        $state = [
            'error_count' => 0,
            'row_count' => 0,
            'valid_count' => 0,
            'seen_keys' => [],
            'preview' => [],
        ];
        $chunk = [];

        foreach ($this->reader->rows($path, $batch->source_type) as $rowNumber => $row) {
            $chunk[] = [$rowNumber, $row];
            if (count($chunk) >= self::VALIDATION_CHUNK_SIZE) {
                $state = $this->validateChunk($batch, $chunk, $mapping, $fieldMap, $headerIndexes, $state);
                $chunk = [];
            }
        }
        if ($chunk !== []) {
            $state = $this->validateChunk($batch, $chunk, $mapping, $fieldMap, $headerIndexes, $state);
        }
        if ($state['row_count'] === 0) {
            throw ValidationException::withMessages(['file' => 'File tidak memuat baris data untuk diimpor.']);
        }

        DB::transaction(function () use ($batch, $state): void {
            $batch->forceFill([
                'row_count' => $state['row_count'],
                'valid_count' => $state['valid_count'],
                'error_count' => $state['error_count'],
                'fatal_error_count' => 0,
                'preview_json' => $state['preview'],
                'status' => $state['error_count'] === 0
                    ? ImportBatch::STATUS_PREVIEW_READY
                    : ImportBatch::STATUS_PREVIEW_WITH_ERRORS,
                'finished_at' => now(),
            ])->save();
        });
    }

    /**
     * Replays only rows accepted by the completed preview. File checksum and row validation are
     * rechecked before a dataset is changed.
     *
     * @return Generator<int, array{record_key: string, record_payload_json: array<string, mixed>, source_row: int}>
     */
    public function validRows(ImportBatch $batch): Generator
    {
        $path = $this->verifiedFilePath($batch);
        $schema = $batch->schema;
        if (! $schema instanceof PerformanceDatasetSchema) {
            throw ValidationException::withMessages(['schema' => 'Schema dataset batch tidak ditemukan.']);
        }

        $headers = $this->reader->headers($path, $batch->source_type);
        $mapping = $this->validatedMapping($schema, $headers, $batch->mapping_json ?? []);
        $fieldMap = $this->fieldMap($schema);
        $headerIndexes = array_flip($headers);
        $invalidRowNumbers = $batch->errors()
            ->whereNotNull('row_number')
            ->pluck('row_number')
            ->map(fn ($rowNumber): int => (int) $rowNumber)
            ->flip()
            ->all();

        foreach ($this->reader->rows($path, $batch->source_type) as $rowNumber => $row) {
            if ($this->isBlankRow($row) || isset($invalidRowNumbers[$rowNumber])) {
                continue;
            }

            [$payload, $errors] = $this->normalizeRow($row, $mapping, $fieldMap, $headerIndexes);
            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'file' => 'Isi file berbeda dari hasil pratinjau. Jalankan validasi ulang sebelum impor.',
                ]);
            }

            yield $rowNumber => [
                'record_key' => $payload['record_key'],
                'record_payload_json' => $payload,
                'source_row' => $rowNumber,
            ];
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $mapping
     * @return array<string, string>
     */
    private function validatedMapping(PerformanceDatasetSchema $schema, array $headers, array $mapping): array
    {
        $fieldMap = $this->fieldMap($schema);
        $normalized = [];
        $usedHeaders = [];

        foreach ($fieldMap as $key => $field) {
            $column = $mapping[$key] ?? null;
            if ($column === null || $column === '') {
                if ($field['required']) {
                    throw ValidationException::withMessages([
                        "mapping.{$key}" => "Kolom untuk field {$field['label']} wajib dipetakan.",
                    ]);
                }

                continue;
            }
            if (! is_string($column) || ! in_array($column, $headers, true)) {
                throw ValidationException::withMessages([
                    "mapping.{$key}" => "Header untuk field {$field['label']} tidak ditemukan di file.",
                ]);
            }
            if (isset($usedHeaders[$column])) {
                throw ValidationException::withMessages([
                    "mapping.{$key}" => 'Satu kolom file tidak boleh dipetakan ke lebih dari satu field.',
                ]);
            }
            $usedHeaders[$column] = true;
            $normalized[$key] = $column;
        }

        if (! isset($normalized['record_key'])) {
            throw ValidationException::withMessages(['mapping.record_key' => 'Field record_key wajib dipetakan.']);
        }

        return $normalized;
    }

    /**
     * @param  list<array{int, array<int, mixed>}>  $chunk
     * @param  array<string, string>  $mapping
     * @param  array<string, array{key: string, label: string, type: string, required: bool, unique: bool}>  $fieldMap
     * @param  array<string, int>  $headerIndexes
     * @param  array{
     *     error_count: int,
     *     row_count: int,
     *     valid_count: int,
     *     seen_keys: array<string, true>,
     *     preview: list<array<string, mixed>>
     * }  $state
     * @return array{
     *     error_count: int,
     *     row_count: int,
     *     valid_count: int,
     *     seen_keys: array<string, true>,
     *     preview: list<array<string, mixed>>
     * }
     */
    private function validateChunk(
        ImportBatch $batch,
        array $chunk,
        array $mapping,
        array $fieldMap,
        array $headerIndexes,
        array $state,
    ): array {
        $candidateKeys = [];
        $normalized = [];
        $errorBuffer = [];

        foreach ($chunk as [$rowNumber, $row]) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $state['row_count']++;
            [$payload, $rowErrors] = $this->normalizeRow($row, $mapping, $fieldMap, $headerIndexes);
            if ($rowErrors === []) {
                $candidateKeys[] = $payload['record_key'];
                $normalized[$rowNumber] = $payload;

                continue;
            }
            foreach ($rowErrors as $rowError) {
                $errorBuffer[] = $this->errorData(
                    $batch,
                    $rowNumber,
                    $rowError['column'],
                    $rowError['code'],
                    $rowError['message'],
                    $this->mappedRowPreview($row, $mapping, $headerIndexes),
                );
            }
        }

        $existingKeys = [];
        if ($batch->strategy === ImportBatch::STRATEGY_APPEND && $batch->target_dataset_id !== null && $candidateKeys !== []) {
            $existingKeys = PerformanceRecord::query()
                ->where('institution_id', $batch->institution_id)
                ->where('dataset_id', $batch->target_dataset_id)
                ->whereIn('record_key', array_values(array_unique($candidateKeys)))
                ->pluck('record_key')
                ->flip()
                ->all();
        }

        foreach ($chunk as [$rowNumber, $row]) {
            if ($this->isBlankRow($row) || ! isset($normalized[$rowNumber])) {
                continue;
            }

            $payload = $normalized[$rowNumber];
            $recordKey = $payload['record_key'];
            $duplicateInFile = isset($state['seen_keys'][$recordKey]);
            $duplicateInDataset = isset($existingKeys[$recordKey]);
            if ($duplicateInFile || $duplicateInDataset) {
                $errorBuffer[] = $this->errorData(
                    $batch,
                    $rowNumber,
                    'record_key',
                    'duplicate_record_key',
                    $duplicateInFile
                        ? 'record_key duplikat di dalam file.'
                        : 'record_key sudah ada di dataset tujuan.',
                    $this->mappedRowPreview($row, $mapping, $headerIndexes),
                );

                continue;
            }

            $state['seen_keys'][$recordKey] = true;
            $state['valid_count']++;
            if (count($state['preview']) < 10) {
                $state['preview'][] = $payload;
            }
        }

        if ($errorBuffer !== []) {
            ImportError::query()->insert($errorBuffer);
            $state['error_count'] += count($errorBuffer);
        }

        return $state;
    }

    /**
     * @return array<string, array{key: string, label: string, type: string, required: bool, unique: bool}>
     */
    private function fieldMap(PerformanceDatasetSchema $schema): array
    {
        $fieldMap = [];
        foreach ($schema->fields_json ?? [] as $field) {
            if (
                is_string($field['key'] ?? null)
                && is_string($field['label'] ?? null)
                && is_string($field['type'] ?? null)
                && is_bool($field['required'] ?? null)
                && is_bool($field['unique'] ?? null)
            ) {
                $fieldMap[$field['key']] = [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'required' => $field['required'],
                    'unique' => $field['unique'],
                ];
            }
        }

        return $fieldMap;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, string>  $mapping
     * @param  array<string, array{key: string, label: string, type: string, required: bool, unique: bool}>  $fieldMap
     * @param  array<string, int>  $headerIndexes
     * @return array{0: array<string, mixed>, 1: list<array{column: string|null, code: string, message: string}>}
     */
    private function normalizeRow(array $row, array $mapping, array $fieldMap, array $headerIndexes): array
    {
        $payload = [];
        $errors = [];

        foreach ($mapping as $key => $column) {
            $index = $headerIndexes[$column];
            $value = $row[$index] ?? null;
            $field = $fieldMap[$key];
            $empty = $value === null || (is_string($value) && trim($value) === '');
            if ($empty && ! $field['required']) {
                $payload[$key] = null;

                continue;
            }
            if ($empty) {
                $errors[] = [
                    'column' => $column,
                    'code' => 'required_value_missing',
                    'message' => "Field {$field['label']} wajib diisi.",
                ];

                continue;
            }

            try {
                $payload[$key] = $this->normalizeValue($value, $field['type']);
            } catch (Throwable) {
                $errors[] = [
                    'column' => $column,
                    'code' => 'invalid_value',
                    'message' => "Nilai pada {$field['label']} tidak sesuai tipe {$field['type']}.",
                ];
            }
        }

        if (! isset($payload['record_key']) || ! is_string($payload['record_key']) || $payload['record_key'] === '') {
            $errors[] = [
                'column' => $mapping['record_key'] ?? null,
                'code' => 'invalid_record_key',
                'message' => 'record_key harus berupa teks yang tidak kosong.',
            ];
        } elseif (mb_strlen($payload['record_key']) > 255) {
            $errors[] = [
                'column' => $mapping['record_key'] ?? null,
                'code' => 'record_key_too_long',
                'message' => 'record_key tidak boleh lebih dari 255 karakter.',
            ];
        }

        return [$payload, $errors];
    }

    private function normalizeValue(mixed $value, string $type): mixed
    {
        if (! is_string($value) && ! is_numeric($value) && ! is_bool($value)) {
            throw new \InvalidArgumentException('Unsupported value type.');
        }
        $value = is_string($value) ? trim($value) : $value;

        return match ($type) {
            'string' => mb_substr((string) $value, 0, 1000) === (string) $value
                ? (string) $value
                : throw new \InvalidArgumentException('String too long.'),
            'integer' => filter_var((string) $value, FILTER_VALIDATE_INT) !== false
                ? (int) $value
                : throw new \InvalidArgumentException('Invalid integer.'),
            'decimal' => is_numeric($value)
                ? (string) $value
                : throw new \InvalidArgumentException('Invalid decimal.'),
            'date' => $this->normalizeDate((string) $value),
            'boolean' => $this->normalizeBoolean($value),
            default => throw new \InvalidArgumentException('Unsupported schema type.'),
        };
    }

    private function normalizeDate(string $value): string
    {
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        throw new \InvalidArgumentException('Invalid date.');
    }

    private function normalizeBoolean(mixed $value): bool
    {
        return match (mb_strtolower((string) $value)) {
            '1', 'true', 'yes', 'ya' => true,
            '0', 'false', 'no', 'tidak' => false,
            default => throw new \InvalidArgumentException('Invalid boolean.'),
        };
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, string>  $mapping
     * @param  array<string, int>  $headerIndexes
     * @return array<string, string|null>
     */
    private function mappedRowPreview(array $row, array $mapping, array $headerIndexes): array
    {
        $preview = [];
        foreach ($mapping as $field => $header) {
            $value = $row[$headerIndexes[$header]] ?? null;
            $preview[$field] = $value === null ? null : mb_substr((string) $value, 0, 200);
        }

        return $preview;
    }

    /**
     * @return array<string, mixed>
     */
    private function errorData(
        ImportBatch $batch,
        int $rowNumber,
        ?string $column,
        string $code,
        string $message,
        array $payload,
    ): array {
        return [
            'import_batch_id' => $batch->getKey(),
            'institution_id' => $batch->institution_id,
            'row_number' => $rowNumber,
            'column_name' => $column,
            'error_code' => $code,
            'message' => $message,
            'row_payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'is_fatal' => false,
            'created_at' => now(),
        ];
    }

    private function verifiedFilePath(ImportBatch $batch): string
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($batch->storage_key)) {
            throw ValidationException::withMessages(['file' => 'File impor tidak ditemukan. Upload ulang file sebelum melanjutkan.']);
        }

        $path = $disk->path($batch->storage_key);
        $checksum = hash_file('sha256', $path);
        if (! is_string($checksum) || ! hash_equals($batch->checksum_sha256, $checksum)) {
            throw ValidationException::withMessages(['file' => 'Checksum file berubah. Batch dibatalkan; upload ulang file yang benar.']);
        }

        return $path;
    }
}
