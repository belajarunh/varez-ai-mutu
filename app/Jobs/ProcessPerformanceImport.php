<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Services\Performance\PerformanceImportProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessPerformanceImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public readonly int $institutionId,
        public readonly int $batchId,
    ) {}

    public function handle(PerformanceImportProcessor $processor): void
    {
        $batch = $this->batch();
        if ($batch === null || $batch->status === ImportBatch::STATUS_COMPLETED) {
            return;
        }
        if ($batch->status !== ImportBatch::STATUS_QUEUED) {
            return;
        }

        $processor->process($this->institutionId, $this->batchId);
    }

    public function failed(?Throwable $exception): void
    {
        $batch = $this->batch();
        if ($batch === null || $batch->status === ImportBatch::STATUS_COMPLETED) {
            return;
        }

        DB::transaction(function () use ($batch): void {
            ImportError::query()->where('import_batch_id', $batch->getKey())
                ->where('is_fatal', true)
                ->delete();
            ImportError::query()->create([
                'import_batch_id' => $batch->getKey(),
                'institution_id' => $batch->institution_id,
                'error_code' => 'processing_failed',
                'message' => 'Impor gagal setelah beberapa percobaan. Tidak ada perubahan parsial yang disimpan.',
                'is_fatal' => true,
            ]);
            $batch->forceFill([
                'status' => ImportBatch::STATUS_FAILED,
                'fatal_error_count' => 1,
                'error_count' => $batch->error_count + 1,
                'finished_at' => now(),
            ])->save();
        });
    }

    private function batch(): ?ImportBatch
    {
        return ImportBatch::query()
            ->where('institution_id', $this->institutionId)
            ->whereKey($this->batchId)
            ->first();
    }
}
