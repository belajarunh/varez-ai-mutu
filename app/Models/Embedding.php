<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id', 'document_chunk_id', 'provider', 'model', 'dimensions', 'vector_json', 'vector_checksum',
])]
class Embedding extends Model
{
    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'vector_json' => 'array',
        ];
    }

    /** @return BelongsTo<DocumentChunk, $this> */
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'document_chunk_id');
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
