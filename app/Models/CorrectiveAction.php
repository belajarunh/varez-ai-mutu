<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'institution_id', 'finding_id', 'action_plan', 'owner_user_id', 'due_date',
    'verification_notes', 'effectiveness_status', 'verified_by', 'verified_at',
    'closed_at', 'status',
])]
class CorrectiveAction extends Model
{
    public const STATUSES = ['planned', 'in_progress', 'pending_verification', 'closed', 'rejected'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'verified_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $action): void {
            if ($action->status === 'closed' && $action->closed_at === null) {
                throw new LogicException('A corrective action cannot be closed without a recorded closure.');
            }
        });
    }

    /** @return BelongsTo<AuditFinding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'finding_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
