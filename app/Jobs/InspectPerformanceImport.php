<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Services\Performance\PerformanceImportFileReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class InspectPerformanceImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $institutionId,
        public readonly int $batchId,
    ) {}

    public function handle(PerformanceImportFileReader $reader): void
    {
        $batch = $this->batch();
        if ($batch === null || $batch->status !== ImportBatch::STATUS_INSPECTING) {
            return;
        }

        try {
            $path = Storage::disk('local')->path($batch->storage_key);
            $headers = $reader->headers($path, $batch->source_type);
            $batch->forceFill([
                'headers_json' => $headers,
                'status' => ImportBatch::STATUS_AWAITING_MAPPING,
            ])->save();
        } catch (ValidationException $exception) {
            $this->markFailed($batch, $exception->errors());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $batch = $this->batch();
        if ($batch === null || $batch->status !== ImportBatch::STATUS_INSPECTING) {
            return;
        }

        $this->markFailed($batch, [
            'file' => ['File gagal diperiksa setelah beberapa percobaan. Pastikan formatnya valid, lalu unggah ulang.'],
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
    private function markFailed(ImportBatch $batch, array $messages): void
    {
        DB::transaction(function () use ($batch, $messages): void {
            $errorRows = [];
            foreach ($messages as $field => $fieldMessages) {
                foreach ($fieldMessages as $message) {
                    $errorRows[] = [
                        'import_batch_id' => $batch->getKey(),
                        'institution_id' => $batch->institution_id,
                        'row_number' => null,
                        'column_name' => $field,
                        'error_code' => 'file_inspection_failed',
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
                'error_count' => count($errorRows),
                'fatal_error_count' => count($errorRows),
                'finished_at' => now(),
            ])->save();
        });
    }
}
