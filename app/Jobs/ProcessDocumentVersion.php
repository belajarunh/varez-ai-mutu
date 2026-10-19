<?php

namespace App\Jobs;

use App\Models\DocumentVersion;
use App\Services\Documents\DocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessDocumentVersion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 240;

    public function __construct(
        public readonly int $institutionId,
        public readonly int $versionId,
    ) {}

    public function handle(DocumentProcessor $processor): void
    {
        $processor->process($this->institutionId, $this->versionId);
    }

    public function failed(?Throwable $exception): void
    {
        $version = DocumentVersion::query()
            ->where('institution_id', $this->institutionId)
            ->whereKey($this->versionId)
            ->first();
        if ($version === null || in_array($version->extraction_status, [
            DocumentVersion::STATUS_READY,
            DocumentVersion::STATUS_FAILED,
            DocumentVersion::STATUS_NEEDS_REVIEW,
        ], true)) {
            return;
        }

        $version->forceFill([
            'extraction_status' => DocumentVersion::STATUS_FAILED,
            'scan_status' => $version->scan_status === 'scanning' ? 'error' : $version->scan_status,
            'metadata_json' => ['error_code' => 'processing_failed'],
        ])->save();
        Log::error('Document processing failed after retries.', [
            'institution_id' => $this->institutionId,
            'document_version_id' => $this->versionId,
            'exception_type' => $exception === null ? null : $exception::class,
        ]);
    }
}
