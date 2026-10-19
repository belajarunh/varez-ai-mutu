<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'institution_id', 'unit_id', 'academic_period_id', 'title', 'scope_json',
    'lead_auditor_id', 'starts_at', 'ends_at', 'status',
])]
class AuditSession extends Model
{
    public const STATUSES = ['planned', 'in_progress', 'completed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'scope_json' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'unit_id');
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /** @return BelongsTo<User, $this> */
    public function leadAuditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_auditor_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function auditors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'audit_session_auditors', 'audit_session_id', 'user_id')
            ->withPivot(['institution_id', 'assigned_at']);
    }

    /** @return HasMany<AuditChecklistItem, $this> */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(AuditChecklistItem::class);
    }

    /** @return HasMany<AuditFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }
}
