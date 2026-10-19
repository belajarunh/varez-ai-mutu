<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'institution_id', 'audit_session_id', 'checklist_item_id', 'finding_code',
    'severity', 'description', 'root_cause', 'recommendation', 'owner_user_id',
    'due_date', 'status',
])]
class AuditFinding extends Model
{
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['open', 'action_planned', 'in_progress', 'pending_verification', 'closed'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    /** @return BelongsTo<AuditSession, $this> */
    public function auditSession(): BelongsTo
    {
        return $this->belongsTo(AuditSession::class);
    }

    /** @return BelongsTo<AuditChecklistItem, $this> */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(AuditChecklistItem::class, 'checklist_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<CorrectiveAction, $this> */
    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class, 'finding_id');
    }

    /** @return HasMany<AuditFindingEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(AuditFindingEvidence::class, 'finding_id');
    }
}
