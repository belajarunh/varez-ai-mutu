<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'instrument_id', 'version_label', 'effective_from', 'effective_to', 'status',
    'verification_status', 'source_url', 'source_checksum', 'approved_by', 'approved_at', 'notes',
])]
class InstrumentVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_RETIRED = 'retired';

    public const VERIFICATION_INTERNAL = 'internal';

    public const VERIFICATION_UNVERIFIED = 'unverified';

    public const VERIFICATION_VERIFIED = 'verified';

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if (
                in_array($version->getOriginal('status'), [self::STATUS_PUBLISHED, self::STATUS_RETIRED], true)
                && $version->isDirty(array_diff(array_keys($version->getDirty()), ['status']))
            ) {
                throw new LogicException('Published instrument versions are immutable.');
            }

            if ($version->getOriginal('status') === self::STATUS_RETIRED && $version->status !== self::STATUS_RETIRED) {
                throw new LogicException('Retired instrument versions cannot be reactivated.');
            }
        });

        static::deleting(function (self $version): void {
            if ($version->status !== self::STATUS_DRAFT) {
                throw new LogicException('Only draft instrument versions may be deleted.');
            }
        });
    }

    /** @return BelongsTo<Instrument, $this> */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    /** @return HasMany<InstrumentNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(InstrumentNode::class);
    }

    /** @return HasMany<AccreditationProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(AccreditationProject::class);
    }
}
