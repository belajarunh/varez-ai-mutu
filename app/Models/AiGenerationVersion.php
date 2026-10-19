<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_generation_id', 'version_number', 'result_json', 'snapshot_checksum', 'created_by_user_id',
])]
class AiGenerationVersion extends Model
{
    protected $table = 'ai_generation_versions';

    protected function casts(): array
    {
        return [
            'result_json' => 'array',
        ];
    }

    /** @return BelongsTo<AiGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
