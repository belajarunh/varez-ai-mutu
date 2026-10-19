<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'institution_id', 'project_id', 'user_id', 'prompt_template_id', 'task_type', 'input_hash',
    'provider', 'model', 'status', 'input_tokens', 'output_tokens', 'estimated_cost', 'result_text',
    'result_json', 'safety_flags_json', 'correlation_id', 'started_at', 'finished_at',
])]
class AiGeneration extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NEEDS_MORE_EVIDENCE = 'needs_more_evidence';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'result_json' => 'array',
            'safety_flags_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AiPromptTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AiPromptTemplate::class, 'prompt_template_id');
    }

    /** @return HasMany<AiGenerationSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(AiGenerationSource::class, 'ai_generation_id');
    }

    /** @return HasMany<AiCitation, $this> */
    public function citations(): HasMany
    {
        return $this->hasMany(AiCitation::class, 'ai_generation_id');
    }
}
