<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DocumentProcessor
{
    public function __construct(
        private readonly DocumentExtractor $extractor,
        private readonly DocumentChunker $chunker,
    ) {}

    public function process(int $institutionId, int $versionId): void
    {
        $version = DocumentVersion::query()
            ->with('document')
            ->where('institution_id', $institutionId)
            ->whereKey($versionId)
            ->firstOrFail();

        if (in_array($version->extraction_status, [
            DocumentVersion::STATUS_READY,
            DocumentVersion::STATUS_FAILED,
            DocumentVersion::STATUS_NEEDS_REVIEW,
        ], true)) {
            return;
        }
        if (! $version->document instanceof Document || $version->document->status !== Document::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['document' => 'Dokumen tidak aktif untuk diproses.']);
        }

        $version->forceFill(['extraction_status' => DocumentVersion::STATUS_PROCESSING])->save();
        $temporaryPath = $this->copyToTemporaryFile($version);

        try {
            $checksum = hash_file('sha256', $temporaryPath);
            if (! is_string($checksum) || ! hash_equals($version->checksum_sha256, $checksum)) {
                throw ValidationException::withMessages(['file' => 'Checksum file berubah setelah diunggah.']);
            }

            $scannerBinary = config('documents.scanner_binary');
            if (is_string($scannerBinary) && trim($scannerBinary) !== '') {
                $version->forceFill(['scan_status' => 'scanning'])->save();
            }
            try {
                $scanStatus = $this->scan($temporaryPath);
            } catch (\Throwable $exception) {
                if (is_string($scannerBinary) && trim($scannerBinary) !== '') {
                    $version->forceFill(['scan_status' => 'error'])->save();
                }
                throw $exception;
            }
            $version->forceFill(['scan_status' => $scanStatus])->save();
            if ($scanStatus === 'infected') {
                $version->forceFill([
                    'extraction_status' => DocumentVersion::STATUS_FAILED,
                    'scan_status' => 'infected',
                    'metadata_json' => ['error_code' => 'malware_detected'],
                ])->save();

                return;
            }

            $extension = strtolower($version->file_extension);
            $extraction = $this->extractor->extract($temporaryPath, $extension);
            $normalizedPages = [];
            $allText = '';
            foreach ($extraction['pages'] as $page) {
                $text = $this->chunker->normalize($page['text']);
                $allText .= ($allText === '' ? '' : "\n").$text;
                $normalizedPages[] = [...$page, 'text' => $text];
                if (mb_strlen($allText) > 10_000_000) {
                    throw ValidationException::withMessages(['file' => 'Teks hasil ekstraksi melebihi batas aman.']);
                }
            }

            $chunks = $this->chunker->chunk($normalizedPages, $extraction['parser']);
            $needsReview = $extraction['needs_review']
                || mb_strlen($allText) < 40
                || str_word_count($allText) < 5;
            $status = $needsReview ? DocumentVersion::STATUS_NEEDS_REVIEW : DocumentVersion::STATUS_READY;
            if ($needsReview && $chunks !== []) {
                foreach ($chunks as &$chunk) {
                    $chunk['metadata_json']['review_required'] = true;
                }
                unset($chunk);
            }

            DB::transaction(function () use (
                $version,
                $scanStatus,
                $extraction,
                $allText,
                $chunks,
                $status,
            ): void {
                DocumentChunk::query()->where('institution_id', $version->institution_id)
                    ->where('document_version_id', $version->getKey())
                    ->delete();
                foreach (array_chunk($chunks, 250) as $chunkRows) {
                    $rows = array_map(fn (array $chunk): array => [
                        'institution_id' => $version->institution_id,
                        'document_version_id' => $version->getKey(),
                        ...$chunk,
                        'metadata_json' => json_encode($chunk['metadata_json'], JSON_THROW_ON_ERROR),
                        'index_status' => $status === DocumentVersion::STATUS_READY ? 'ready' : 'needs_review',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $chunkRows);
                    DocumentChunk::query()->insert($rows);
                }
                $version->forceFill([
                    'extraction_status' => $status,
                    'scan_status' => $scanStatus,
                    'page_count' => $extraction['page_count'],
                    'extracted_character_count' => mb_strlen($allText),
                    'metadata_json' => [
                        'parser' => $extraction['parser'],
                        'warnings' => $extraction['warnings'],
                        'quality' => $status === DocumentVersion::STATUS_READY ? 'acceptable' : 'needs_review',
                        'chunk_count' => count($chunks),
                    ],
                ])->save();
            });
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function scan(string $path): string
    {
        $binary = config('documents.scanner_binary');
        if (! is_string($binary) || trim($binary) === '') {
            return 'not_scanned';
        }

        $resolvedBinary = (new ExecutableFinder)->find($binary);
        if ($resolvedBinary === null) {
            throw new \RuntimeException('Configured document scanner is unavailable.');
        }

        $process = new Process([$resolvedBinary, '--no-summary', $path]);
        $process->setTimeout(30);
        $process->run();
        if ($process->getExitCode() === 0) {
            return 'clean';
        }
        if ($process->getExitCode() === 1) {
            return 'infected';
        }

        throw new \RuntimeException('Configured document scanner failed.');
    }

    private function copyToTemporaryFile(DocumentVersion $version): string
    {
        $stream = Storage::disk($version->storage_disk)->readStream($version->storage_key);
        if (! is_resource($stream)) {
            throw new \RuntimeException('Private document file could not be read.');
        }
        $path = tempnam(sys_get_temp_dir(), 'varez-document-');
        if (! is_string($path)) {
            fclose($stream);
            throw new \RuntimeException('Temporary document file could not be created.');
        }

        $target = fopen($path, 'wb');
        if ($target === false) {
            fclose($stream);
            unlink($path);
            throw new \RuntimeException('Temporary document file could not be opened.');
        }
        $copied = stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);
        if ($copied !== $version->size_bytes) {
            unlink($path);
            throw new \RuntimeException('Private document file size changed while reading.');
        }

        return $path;
    }
}
