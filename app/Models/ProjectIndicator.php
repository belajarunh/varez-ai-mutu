<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property array<string, mixed> $snapshot_node_json
 * @property array<string, mixed>|null $progress_metadata_json
 * @property Carbon|null $due_date
 */
#[Fillable([
    'institution_id', 'project_id', 'source_instrument_node_id', 'snapshot_node_json',
    'status', 'owner_user_id', 'due_date', 'progress_metadata_json',
])]
class ProjectIndicator extends Model
{
    public const STATUSES = ['not_started', 'needs_data', 'needs_evidence', 'draft', 'in_review', 'approved'];

    protected function casts(): array
    {
        return [
            'snapshot_node_json' => 'array',
            'progress_metadata_json' => 'array',
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $indicator): void {
            if ($indicator->isDirty(['institution_id', 'project_id', 'source_instrument_node_id', 'snapshot_node_json'])) {
                throw new LogicException('Project indicator snapshot is immutable.');
            }
        });
    }

    /** @return BelongsTo<AccreditationProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(AccreditationProject::class, 'project_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
