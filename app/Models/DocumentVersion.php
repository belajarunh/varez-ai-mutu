<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property array<string, mixed>|null $metadata_json
 * @property string $extraction_status
 * @property string $scan_status
 * @property string $file_extension
 * @property string $checksum_sha256
 * @property int $version_number
 * @property int $size_bytes
 */
#[Fillable([
    'institution_id', 'document_id', 'version_number', 'storage_disk', 'storage_key', 'original_filename',
    'file_extension', 'mime_type', 'size_bytes', 'checksum_sha256', 'uploaded_by', 'uploaded_at', 'extraction_status',
    'scan_status', 'page_count', 'extracted_character_count', 'metadata_json',
])]
class DocumentVersion extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->isDirty([
                'institution_id', 'document_id', 'version_number', 'storage_disk', 'storage_key',
                'original_filename', 'mime_type', 'size_bytes', 'checksum_sha256', 'uploaded_by', 'uploaded_at',
            ])) {
                throw new LogicException('Document version file metadata is immutable.');
            }
        });
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<DocumentChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
