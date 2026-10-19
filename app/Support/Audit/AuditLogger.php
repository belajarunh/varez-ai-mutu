<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    private const SAFE_FIELDS = [
        'name',
        'slug',
        'institution_type',
        'legal_identifier',
        'status',
        'timezone',
        'parent_id',
        'unit_type',
        'code',
        'role_id',
        'expires_at',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        Model $subject,
        ?Institution $institution = null,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'institution_id' => $institution?->getKey(),
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => (string) $subject->getKey(),
            'user_agent_summary' => null,
            'before_json' => $this->filterFields($before),
            'after_json' => $this->filterFields($after),
            'correlation_id' => (string) Str::uuid(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function filterFields(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return array_intersect_key($values, array_flip(self::SAFE_FIELDS));
    }
}
