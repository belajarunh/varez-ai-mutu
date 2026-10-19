<?php

namespace App\Services\Performance;

use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\PerformanceDataset;
use App\Models\PerformanceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PerformanceImportProcessor
{
    private const INSERT_CHUNK_SIZE = 500;

    public function __construct(private readonly PerformanceImportValidator $validator) {}

    public function process(int $institutionId, int $batchId): void
    {
        DB::transaction(function () use ($institutionId, $batchId): void {
            $batch = ImportBatch::query()
                ->where('institution_id', $institutionId)
                ->whereKey($batchId)
                ->lockForUpdate()
                ->first();
            if ($batch === null || $batch->status === ImportBatch::STATUS_COMPLETED) {
                return;
            }
            if ($batch->status !== ImportBatch::STATUS_QUEUED) {
                return;
            }
            if ($batch->fatal_error_count > 0 || $batch->valid_count < 1 || $batch->confirmed_by === null) {
                throw ValidationException::withMessages(['batch' => 'Batch tidak memenuhi syarat untuk diproses.']);
            }

            $dataset = $this->targetDataset($batch);
            $batch->forceFill([
                'status' => ImportBatch::STATUS_PROCESSING,
                'started_at' => now(),
            ])->save();

            if ($batch->strategy === ImportBatch::STRATEGY_REPLACE) {
                $dataset->records()->delete();
            }

            $records = [];
            foreach ($this->validator->validRows($batch) as $row) {
                $records[] = [
                    'institution_id' => $institutionId,
                    'dataset_id' => $dataset->getKey(),
                    'record_key' => $row['record_key'],
                    'record_payload_json' => json_encode($row['record_payload_json'], JSON_THROW_ON_ERROR),
                    'source_row' => $row['source_row'],
                    'validation_status' => 'valid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($records) >= self::INSERT_CHUNK_SIZE) {
                    PerformanceRecord::query()->insert($records);
                    $records = [];
                }
            }
            if ($records !== []) {
                PerformanceRecord::query()->insert($records);
            }

            $batch->forceFill([
                'status' => ImportBatch::STATUS_COMPLETED,
                'finished_at' => now(),
            ])->save();

            AuditLog::query()->create([
                'institution_id' => $institutionId,
                'actor_user_id' => $batch->confirmed_by,
                'action' => 'performance_dataset.imported',
                'subject_type' => ImportBatch::class,
                'subject_id' => (string) $batch->getKey(),
                'after_json' => [
                    'dataset_id' => $dataset->getKey(),
                    'strategy' => $batch->strategy,
                    'schema_type' => $batch->schema->dataset_type,
                    'schema_version' => $batch->schema->schema_version,
                    'row_count' => $batch->row_count,
                    'valid_count' => $batch->valid_count,
                    'error_count' => $batch->error_count,
                    'checksum_sha256' => $batch->checksum_sha256,
                ],
                'correlation_id' => (string) Str::uuid(),
            ]);
        });
    }

    private function targetDataset(ImportBatch $batch): PerformanceDataset
    {
        if ($batch->target_dataset_id !== null) {
            $dataset = PerformanceDataset::query()
                ->where('institution_id', $batch->institution_id)
                ->whereKey($batch->target_dataset_id)
                ->lockForUpdate()
                ->first();
            if ($dataset === null || ! $this->matchesBatch($dataset, $batch)) {
                throw ValidationException::withMessages([
                    'target_dataset_id' => 'Dataset tujuan tidak lagi cocok dengan tenant, periode, unit, atau schema batch.',
                ]);
            }
            if ($batch->strategy === ImportBatch::STRATEGY_REPLACE && $dataset->status !== 'active') {
                throw ValidationException::withMessages(['target_dataset_id' => 'Dataset tujuan tidak aktif dan tidak dapat diganti.']);
            }

            return $dataset;
        }

        if ($batch->strategy !== ImportBatch::STRATEGY_APPEND) {
            throw ValidationException::withMessages(['target_dataset_id' => 'Strategi replace memerlukan dataset tujuan yang eksplisit.']);
        }

        $dataset = PerformanceDataset::query()->create([
            'institution_id' => $batch->institution_id,
            'unit_id' => $batch->unit_id,
            'academic_period_id' => $batch->academic_period_id,
            'dataset_type' => $batch->schema->dataset_type,
            'schema_version' => $batch->schema->schema_version,
            'performance_dataset_schema_id' => $batch->performance_dataset_schema_id,
            'source_import_batch_id' => $batch->getKey(),
            'status' => 'active',
        ]);
        $batch->forceFill(['target_dataset_id' => $dataset->getKey()])->save();

        return $dataset;
    }

    private function matchesBatch(PerformanceDataset $dataset, ImportBatch $batch): bool
    {
        return (int) $dataset->institution_id === (int) $batch->institution_id
            && (int) $dataset->academic_period_id === (int) $batch->academic_period_id
            && ($dataset->unit_id === null ? $batch->unit_id === null : (int) $dataset->unit_id === (int) $batch->unit_id)
            && $dataset->dataset_type === $batch->schema->dataset_type
            && (int) $dataset->schema_version === (int) $batch->schema->schema_version
            && (int) $dataset->performance_dataset_schema_id === (int) $batch->performance_dataset_schema_id
            && $dataset->status === 'active';
    }
}
