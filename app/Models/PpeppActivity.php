<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'institution_id', 'standard_id', 'indicator_id', 'unit_id', 'academic_period_id',
    'stage', 'title', 'description', 'owner_user_id', 'due_date', 'status', 'completed_at',
    'approved_by', 'approved_at', 'approval_notes',
])]
class PpeppActivity extends Model
{
    public const STAGES = ['penetapan', 'pelaksanaan', 'evaluasi', 'pengendalian', 'peningkatan'];

    public const STATUSES = ['planned', 'in_progress', 'pending_approval', 'completed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<QualityStandard, $this> */
    public function standard(): BelongsTo
    {
        return $this->belongsTo(QualityStandard::class, 'standard_id');
    }

    /** @return BelongsTo<QualityIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(QualityIndicator::class, 'indicator_id');
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<PpeppEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(PpeppEvidence::class, 'activity_id');
    }
}
