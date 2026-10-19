<?php

namespace App\Services\Billing;

use App\Models\AccreditationProject;
use App\Models\Institution;
use App\Models\InstitutionSubscription;
use App\Models\InstitutionUser;
use App\Models\OrganizationalUnit;
use App\Models\SaasPlan;
use App\Models\UsageLedger;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
            'plan' => $plan->name ?? 'No active plan',
            'status' => $subscription->status ?? 'inactive',
            'ai_quota_limit' => $plan->ai_quota_limit ?? null,
            'storage_quota_mb' => $plan->storage_quota_mb ?? null,
            'users_quota_limit' => $plan->users_quota_limit ?? null,
            'projects_quota_limit' => $plan->projects_quota_limit ?? null,
            'unit_quota_limit' => $plan->unit_quota_limit ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkQuota(Institution $institution, string $usageType, float $requestedAmount = 0, ?string $periodKey = null): array
    {
        if ($requestedAmount < 0) {
            throw new InvalidArgumentException('Requested quota amount cannot be negative.');
        }

        $subscription = $this->currentSubscription($institution);
        $plan = $subscription?->plan;
        $canonicalUsageType = match ($usageType) {
            'ai_tokens', 'ai_tokens_used', 'ai' => 'ai_tokens',
            'storage_mb', 'users', 'projects', 'units' => $usageType,
            default => throw new InvalidArgumentException("Unsupported quota type: {$usageType}."),
        };
        $limit = match ($canonicalUsageType) {
            'ai_tokens' => $plan === null ? null : $plan->ai_quota_limit,
            'storage_mb' => $plan === null ? null : $plan->storage_quota_mb,
            'users' => $plan === null ? null : $plan->users_quota_limit,
            'projects' => $plan === null ? null : $plan->projects_quota_limit,
            'units' => $plan === null ? null : $plan->unit_quota_limit,
        };
        $used = $this->currentUsage($institution, $canonicalUsageType, $periodKey);
        $remaining = $limit === null ? null : max(0, (float) $limit - $used);
        $allowed = $plan !== null && ($limit === null || $requestedAmount <= $remaining);

        return [
            'usage_type' => $canonicalUsageType,
            'quota_limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'allowed' => $allowed,
            'status' => $plan === null
                ? 'no_active_subscription'
                : ($limit === null ? 'unlimited' : ($allowed ? 'within_quota' : 'quota_exhausted')),
            'plan' => $plan->code ?? null,
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
        ?string $idempotencyKey = null,
    ): UsageLedger {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Usage quantity cannot be negative.');
        }

        $periodKey ??= now()->format('Y-m');
        $idempotencyKey ??= ($relatedEntityType !== null && $relatedEntityId !== null)
            ? hash('sha256', implode(':', [
                $institution->getKey(),
                $usageType,
                $relatedEntityType,
                $relatedEntityId,
                $periodKey,
            ]))
            : (string) Str::uuid();

        return UsageLedger::query()->firstOrCreate(
            [
                'institution_id' => $institution->getKey(),
                'usage_type' => $usageType,
                'period_key' => $periodKey,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'user_id' => $user->getKey(),
                'quantity' => $quantity,
                'unit' => $unit,
                'related_entity_type' => $relatedEntityType,
                'related_entity_id' => $relatedEntityId,
                'metadata_json' => $metadata,
            ],
        );
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
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->with('plan')
            ->orderByDesc('created_at')
            ->first();
    }

    public function changePlan(Institution $institution, SaasPlan $plan, User $actor): InstitutionSubscription
    {
        if (! $actor->hasPlatformRole('platform.super_admin')
            && ! $actor->hasPermission('billing.manage', $institution)) {
            throw new AuthorizationException('User cannot change this institution subscription.');
        }

        if ($plan->status !== 'active') {
            throw new InvalidArgumentException('Only active plans can be assigned.');
        }

        return DB::transaction(function () use ($institution, $plan, $actor): InstitutionSubscription {
            $current = InstitutionSubscription::query()
                ->where('institution_id', $institution->getKey())
                ->whereIn('status', ['active', 'trial'])
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->forceFill([
                    'status' => 'ended',
                    'ends_at' => now(),
                ])->save();
            }

            $subscription = InstitutionSubscription::query()->create([
                'institution_id' => $institution->getKey(),
                'saas_plan_id' => $plan->getKey(),
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'manual_activation' => true,
                'gateway' => 'manual',
                'metadata_json' => ['changed_by' => $actor->getKey(), 'changed_at' => now()->toDateTimeString()],
            ]);

            return $subscription->load('plan');
        });
    }

    private function currentUsage(Institution $institution, string $usageType, ?string $periodKey): float
    {
        return match ($usageType) {
            'ai_tokens', 'storage_mb' => $this->usageForInstitution($institution, $usageType, $periodKey),
            'users' => (float) InstitutionUser::query()
                ->where('institution_id', $institution->getKey())
                ->where('status', InstitutionUser::STATUS_ACTIVE)
                ->count(),
            'projects' => (float) AccreditationProject::query()
                ->where('institution_id', $institution->getKey())
                ->count(),
            'units' => (float) OrganizationalUnit::query()
                ->where('institution_id', $institution->getKey())
                ->where('status', OrganizationalUnit::STATUS_ACTIVE)
                ->count(),
            default => throw new InvalidArgumentException("Unsupported quota type: {$usageType}."),
        };
    }
}
