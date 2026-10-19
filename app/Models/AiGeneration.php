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

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EXPORTED = 'exported';

    public const STATUS_FINALIZED = 'finalized';

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

    /** @return HasMany<AiGenerationReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(AiGenerationReview::class, 'ai_generation_id');
    }

    /** @return HasMany<AiGenerationExport, $this> */
    public function exports(): HasMany
    {
        return $this->hasMany(AiGenerationExport::class, 'ai_generation_id');
    }

    /** @return HasMany<AiGenerationVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(AiGenerationVersion::class, 'ai_generation_id');
    }

    public function canBeReviewedBy(User $user): bool
    {
        $institution = $this->institution()->first();

        if ($user->hasPlatformRole('platform.super_admin')) {
            return true;
        }

        if ($institution === null) {
            return false;
        }

        return $user->hasPermission('ai.review', $institution)
            || $user->hasPermission('ai.approve', $institution);
    }

    public function canBeApprovedBy(User $user): bool
    {
        $institution = $this->institution()->first();

        if ($institution === null) {
            return false;
        }

        return $user->hasPlatformRole('platform.super_admin')
            || $user->hasPermission('ai.approve', $institution)
            || $user->hasPermission('ai.review', $institution);
    }

    public function canBeExportedBy(User $user): bool
    {
        $institution = $this->institution()->first();

        if ($institution === null) {
            return false;
        }

        return $user->hasPlatformRole('platform.super_admin')
            || $user->hasPermission('ai.export', $institution)
            || $user->hasPermission('ai.approve', $institution);
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
