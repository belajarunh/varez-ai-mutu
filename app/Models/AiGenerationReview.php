<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_generation_id', 'reviewer_user_id', 'decision', 'notes', 'reviewed_at',
])]
class AiGenerationReview extends Model
{
    public const DECISION_SUBMITTED = 'submitted';

    public const DECISION_APPROVED = 'approved';

    public const DECISION_REVISION_REQUESTED = 'revision_requested';

    public const DECISION_REJECTED = 'rejected';

    protected $table = 'ai_generation_reviews';

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AiGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
