<?php

namespace App\Services\Performance;

use App\Models\PerformanceDatasetSchema;
use Illuminate\Validation\ValidationException;

class PerformanceDatasetSchemaDefinition
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function validate(string $datasetType, array $fields): void
    {
        $errors = [];
        if (! preg_match('/\A[a-z][a-z0-9_]{1,99}\z/', $datasetType)) {
            $errors['dataset_type'][] = 'Jenis dataset harus berupa kode huruf kecil, angka, dan garis bawah.';
        }
        if ($fields === [] || count($fields) > 100) {
            $errors['fields_json'][] = 'Definisi schema harus memiliki 1 sampai 100 field.';
        }

        $keys = [];
        $hasRecordKey = false;
        foreach ($fields as $index => $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key) || ! preg_match('/\A[a-z][a-z0-9_]{0,99}\z/', $key)) {
                $errors["fields_json.{$index}.key"][] = 'Key field harus berupa kode huruf kecil, angka, dan garis bawah.';

                continue;
            }
            if (isset($keys[$key])) {
                $errors["fields_json.{$index}.key"][] = 'Key field tidak boleh duplikat.';
            }
            $keys[$key] = true;

            if (! is_string($field['label'] ?? null) || trim($field['label']) === '') {
                $errors["fields_json.{$index}.label"][] = 'Label field wajib diisi.';
            }
            if (! is_bool($field['required'] ?? null) || ! is_bool($field['unique'] ?? null)) {
                $errors["fields_json.{$index}"][] = 'Field required dan unique harus berupa boolean.';
            }
            if (! in_array($field['type'] ?? null, PerformanceDatasetSchema::FIELD_TYPES, true)) {
                $errors["fields_json.{$index}.type"][] = 'Tipe field tidak didukung.';
            }
            if (($field['unique'] ?? false) && $key !== 'record_key') {
                $errors["fields_json.{$index}.unique"][] = 'Saat ini hanya record_key yang dapat diberi aturan unique.';
            }
            if ($key === 'record_key') {
                $hasRecordKey = ($field['type'] ?? null) === 'string' && ($field['required'] ?? false) && ($field['unique'] ?? false);
            }
        }

        if (! $hasRecordKey) {
            $errors['fields_json'][] = 'Schema wajib memiliki record_key bertipe string, required, dan unique.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
