<?php

namespace App\Services\Billing;

use App\Models\Institution;
use App\Models\InstitutionSubscription;
use App\Models\SaasPlan;
use App\Models\UsageLedger;
use App\Models\User;

class UsageQuotaService
{
    /**
     * @return array<string, mixed>
     */
    public function getQuotaSummary(Institution $institution): array
    {
        $subscription = $this->currentSubscription($institution);
        $plan = $subscription?->plan;

        return [
            'plan' => $plan?->name ?? 'No active plan',
            'status' => $subscription?->status ?? 'inactive',
            'ai_quota_limit' => $plan?->ai_quota_limit ?? 0,
            'storage_quota_mb' => $plan?->storage_quota_mb ?? 0,
            'users_quota_limit' => $plan?->users_quota_limit ?? 0,
            'projects_quota_limit' => $plan?->projects_quota_limit ?? 0,
            'unit_quota_limit' => $plan?->unit_quota_limit ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkQuota(Institution $institution, string $usageType, float $requestedAmount = 0, ?string $periodKey = null): array
    {
        $subscription = $this->currentSubscription($institution);
        $plan = $subscription?->plan;

        $limit = match ($usageType) {
            'ai_tokens', 'ai_tokens_used', 'ai' => (int) ($plan?->ai_quota_limit ?? 0),
            'storage_mb' => (int) ($plan?->storage_quota_mb ?? 0),
            'users' => (int) ($plan?->users_quota_limit ?? 0),
            'projects' => (int) ($plan?->projects_quota_limit ?? 0),
            'units' => (int) ($plan?->unit_quota_limit ?? 0),
            default => 0,
        };

        $used = (float) $this->usageForInstitution($institution, $usageType, $periodKey);
        $remaining = max(0, $limit - $used);

        return [
            'usage_type' => $usageType,
            'quota_limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'allowed' => $requestedAmount <= $remaining || $limit === 0,
            'status' => $limit === 0 ? 'unlimited' : ($remaining > 0 ? 'within_quota' : 'quota_exhausted'),
            'plan' => $plan?->code ?? null,
        ];
    }

    public function recordUsage(
        Institution $institution,
        User $user,
        string $usageType,
        float $quantity,
        string $unit = 'tokens',
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?string $periodKey = null,
        array $metadata = [],
    ): UsageLedger {
        $periodKey ??= now()->format('Y-m');

        $existing = UsageLedger::query()
            ->where('institution_id', $institution->getKey())
            ->where('usage_type', $usageType)
            ->where('period_key', $periodKey)
            ->when($relatedEntityType !== null, fn ($query) => $query->where('related_entity_type', $relatedEntityType))
            ->when($relatedEntityId !== null, fn ($query) => $query->where('related_entity_id', $relatedEntityId))
            ->first();

        if ($existing) {
            $existing->quantity = (float) $existing->quantity + $quantity;
            $existing->metadata_json = array_merge((array) $existing->metadata_json, $metadata);
            $existing->save();

            return $existing;
        }

        return UsageLedger::query()->create([
            'institution_id' => $institution->getKey(),
            'user_id' => $user->getKey(),
            'usage_type' => $usageType,
            'quantity' => $quantity,
            'unit' => $unit,
            'related_entity_type' => $relatedEntityType,
            'related_entity_id' => $relatedEntityId,
            'period_key' => $periodKey,
            'metadata_json' => $metadata,
        ]);
    }

    public function usageForInstitution(Institution $institution, string $usageType, ?string $periodKey = null): float
    {
        $periodKey ??= now()->format('Y-m');

        return (float) UsageLedger::query()
            ->where('institution_id', $institution->getKey())
            ->where('usage_type', $usageType)
            ->where('period_key', $periodKey)
            ->sum('quantity');
    }

    public function currentSubscription(Institution $institution): ?InstitutionSubscription
    {
        return InstitutionSubscription::query()
            ->where('institution_id', $institution->getKey())
            ->whereIn('status', ['active', 'trial'])
            ->with('plan')
            ->orderByDesc('created_at')
            ->first();
    }

    public function changePlan(Institution $institution, SaasPlan $plan, ?User $actor = null): InstitutionSubscription
    {
        $subscription = $this->currentSubscription($institution) ?? new InstitutionSubscription();
        $subscription->fill([
            'institution_id' => $institution->getKey(),
            'saas_plan_id' => $plan->getKey(),
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'manual_activation' => true,
            'gateway' => 'manual',
            'metadata_json' => ['changed_by' => $actor?->getKey(), 'changed_at' => now()->toDateTimeString()],
        ]);
        $subscription->save();

        return $subscription->fresh(['plan']);
    }
}
