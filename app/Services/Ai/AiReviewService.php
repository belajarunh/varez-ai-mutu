<?php

namespace App\Services\Ai;

use App\Models\AiGeneration;
use App\Models\AiGenerationReview;
use App\Models\AiGenerationVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class AiReviewService
{
    public function submitForReview(AiGeneration $generation, ?User $reviewer = null): AiGeneration
    {
        $generation->status = AiGeneration::STATUS_IN_REVIEW;
        $generation->save();

        AiGenerationReview::query()->create([
            'ai_generation_id' => $generation->getKey(),
            'reviewer_user_id' => $reviewer?->getKey(),
            'decision' => AiGenerationReview::DECISION_SUBMITTED,
            'notes' => 'Submitted for review.',
            'reviewed_at' => now(),
        ]);

        $this->recordVersion($generation, $reviewer);

        return $generation;
    }

    public function approve(AiGeneration $generation, User $reviewer, ?string $notes = null): AiGeneration
    {
        if (! $generation->canBeApprovedBy($reviewer)) {
            throw new AuthorizationException('User cannot approve this AI generation.');
        }

        $generation->status = AiGeneration::STATUS_APPROVED;
        $generation->save();

        AiGenerationReview::query()->create([
            'ai_generation_id' => $generation->getKey(),
            'reviewer_user_id' => $reviewer->getKey(),
            'decision' => AiGenerationReview::DECISION_APPROVED,
            'notes' => $notes ?? 'Approved by reviewer.',
            'reviewed_at' => now(),
        ]);

        return $generation;
    }

    public function requestRevision(AiGeneration $generation, User $reviewer, ?string $notes = null): AiGeneration
    {
        if (! $generation->canBeReviewedBy($reviewer)) {
            throw new AuthorizationException('User cannot request revision for this AI generation.');
        }

        $generation->status = AiGeneration::STATUS_REVISION_REQUESTED;
        $generation->save();

        AiGenerationReview::query()->create([
            'ai_generation_id' => $generation->getKey(),
            'reviewer_user_id' => $reviewer->getKey(),
            'decision' => AiGenerationReview::DECISION_REVISION_REQUESTED,
            'notes' => $notes ?? 'Revision requested by reviewer.',
            'reviewed_at' => now(),
        ]);

        return $generation;
    }

    public function reject(AiGeneration $generation, User $reviewer, ?string $notes = null): AiGeneration
    {
        if (! $generation->canBeReviewedBy($reviewer)) {
            throw new AuthorizationException('User cannot reject this AI generation.');
        }

        $generation->status = AiGeneration::STATUS_REJECTED;
        $generation->save();

        AiGenerationReview::query()->create([
            'ai_generation_id' => $generation->getKey(),
            'reviewer_user_id' => $reviewer->getKey(),
            'decision' => AiGenerationReview::DECISION_REJECTED,
            'notes' => $notes ?? 'Rejected by reviewer.',
            'reviewed_at' => now(),
        ]);

        return $generation;
    }

    protected function recordVersion(AiGeneration $generation, ?User $reviewer = null): void
    {
        $version = AiGenerationVersion::query()->where('ai_generation_id', $generation->getKey())->count();

        AiGenerationVersion::query()->create([
            'ai_generation_id' => $generation->getKey(),
            'version_number' => $version + 1,
            'result_json' => $generation->result_json ?? [],
            'snapshot_checksum' => hash('sha256', (string) json_encode($generation->result_json ?? [], JSON_THROW_ON_ERROR)),
            'created_by_user_id' => $reviewer?->getKey() ?? $generation->user_id,
        ]);
    }
}
