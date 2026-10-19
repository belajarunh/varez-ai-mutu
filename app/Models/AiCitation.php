<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_generation_id', 'citation_key', 'source_type', 'source_id', 'display_label',
    'page_or_section', 'quoted_text', 'verified_status',
])]
class AiCitation extends Model
{
    /** @return BelongsTo<AiGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }
}
