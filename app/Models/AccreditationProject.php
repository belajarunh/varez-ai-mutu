<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property array<string, mixed> $instrument_snapshot_json
 */
#[Fillable([
    'institution_id', 'unit_id', 'instrument_version_id', 'project_type', 'title', 'period_label',
    'status', 'target_date', 'owner_user_id', 'created_by', 'instrument_snapshot_json',
    'instrument_snapshot_checksum',
])]
class AccreditationProject extends Model
{
    public const STATUSES = ['planning', 'data_collection', 'drafting', 'review', 'approved', 'archived'];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'instrument_snapshot_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $project): void {
            if ($project->isDirty([
                'institution_id', 'unit_id', 'instrument_version_id', 'project_type', 'period_label',
                'owner_user_id', 'created_by', 'instrument_snapshot_json', 'instrument_snapshot_checksum',
            ])) {
                throw new LogicException('Project instrument snapshot and source are immutable.');
            }
        });
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'unit_id');
    }

    /** @return BelongsTo<InstrumentVersion, $this> */
    public function instrumentVersion(): BelongsTo
    {
        return $this->belongsTo(InstrumentVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members', 'project_id', 'user_id')
            ->withPivot(['institution_id', 'project_role']);
    }

    /** @return HasMany<ProjectIndicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(ProjectIndicator::class, 'project_id');
    }
}
