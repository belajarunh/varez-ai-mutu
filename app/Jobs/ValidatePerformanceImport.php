<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Services\Performance\PerformanceImportValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ValidatePerformanceImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $institutionId,
        public readonly int $batchId,
    ) {}

    public function handle(PerformanceImportValidator $validator): void
    {
        $batch = $this->batch();
        if ($batch === null || $batch->status !== ImportBatch::STATUS_VALIDATING) {
            return;
        }

        try {
            $validator->validateBatch($batch);
        } catch (ValidationException $exception) {
            $this->markFatalFailure($batch, $exception->errors());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $batch = $this->batch();
        if ($batch === null || $batch->status !== ImportBatch::STATUS_VALIDATING) {
            return;
        }

        $this->markFatalFailure($batch, [
            'processing' => ['Validasi gagal setelah beberapa percobaan. Periksa file dan coba unggah ulang.'],
        ]);
    }

    private function batch(): ?ImportBatch
    {
        return ImportBatch::query()
            ->where('institution_id', $this->institutionId)
            ->whereKey($this->batchId)
            ->first();
    }

    /**
     * @param  array<string, array<int, string>>  $messages
     */
    private function markFatalFailure(ImportBatch $batch, array $messages): void
    {
        DB::transaction(function () use ($batch, $messages): void {
            ImportError::query()->where('import_batch_id', $batch->getKey())->delete();
            $errorRows = [];
            foreach ($messages as $field => $fieldMessages) {
                foreach ($fieldMessages as $message) {
                    $errorRows[] = [
                        'import_batch_id' => $batch->getKey(),
                        'institution_id' => $batch->institution_id,
                        'row_number' => null,
                        'column_name' => $field === 'file' ? null : $field,
                        'error_code' => 'fatal_validation_error',
                        'message' => $message,
                        'row_payload_json' => null,
                        'is_fatal' => true,
                        'created_at' => now(),
                    ];
                }
            }
            ImportError::query()->insert($errorRows);
            $batch->forceFill([
                'status' => ImportBatch::STATUS_FAILED,
                'row_count' => 0,
                'valid_count' => 0,
                'error_count' => count($errorRows),
                'fatal_error_count' => count($errorRows),
                'finished_at' => now(),
            ])->save();
        });
    }
}
