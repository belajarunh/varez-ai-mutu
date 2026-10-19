<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'institution_id', 'audit_session_id', 'standard_id', 'indicator_id',
    'question', 'sort_order', 'status', 'notes',
])]
class AuditChecklistItem extends Model
{
    public const STATUSES = ['pending', 'compliant', 'non_compliant', 'not_applicable'];

    /** @return BelongsTo<AuditSession, $this> */
    public function auditSession(): BelongsTo
    {
        return $this->belongsTo(AuditSession::class);
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

    /** @return HasMany<AuditFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class, 'checklist_item_id');
    }
}
