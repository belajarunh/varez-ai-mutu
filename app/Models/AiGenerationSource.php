<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_generation_id', 'document_chunk_id', 'document_version_id', 'instrument_node_id',
    'dataset_record_ref', 'rank', 'relevance_score', 'quoted_text', 'citation_metadata_json',
])]
class AiGenerationSource extends Model
{
    protected function casts(): array
    {
        return [
            'citation_metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<AiGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }

    /** @return BelongsTo<DocumentChunk, $this> */
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'document_chunk_id');
    }
}
