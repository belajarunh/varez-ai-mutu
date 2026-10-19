<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $metadata_json
 */
#[Fillable([
    'institution_id', 'document_version_id', 'chunk_index', 'content_text', 'page_start',
    'page_end', 'section_title', 'token_estimate', 'content_hash', 'metadata_json', 'index_status',
])]
class DocumentChunk extends Model
{
    protected function casts(): array
    {
        return ['metadata_json' => 'array'];
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
