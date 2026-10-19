<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'institution_id', 'unit_id', 'code', 'title', 'description', 'standard_type',
    'version', 'effective_from', 'effective_to', 'status', 'owner_user_id',
])]
class QualityStandard extends Model
{
    public const STATUSES = ['draft', 'published', 'retired'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $standard): void {
            if ($standard->getOriginal('status') !== 'draft'
                && $standard->isDirty([
                    'unit_id', 'code', 'title', 'description', 'standard_type',
                    'version', 'effective_from', 'effective_to', 'owner_user_id',
                ])) {
                throw new LogicException('Published quality standards are immutable; create a new version.');
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

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<QualityIndicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(QualityIndicator::class, 'standard_id');
    }

    /** @return HasMany<PpeppActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(PpeppActivity::class, 'standard_id');
    }
}
