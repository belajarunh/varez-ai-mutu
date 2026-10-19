<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_generation_id', 'exported_by_user_id', 'format', 'file_name', 'manifest_json', 'watermarked', 'exported_at',
])]
class AiGenerationExport extends Model
{
    protected $table = 'ai_generation_exports';

    protected function casts(): array
    {
        return [
            'manifest_json' => 'array',
            'watermarked' => 'boolean',
            'exported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AiGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by_user_id');
    }
}
