<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'institution_id', 'standard_id', 'code', 'name', 'target_json',
    'measurement_method', 'frequency', 'sort_order',
])]
class QualityIndicator extends Model
{
    protected function casts(): array
    {
        return ['target_json' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $indicator): void {
            $standard = QualityStandard::query()
                ->where('institution_id', $indicator->institution_id)
                ->whereKey($indicator->standard_id)
                ->first();
            if ($standard !== null && $standard->status !== 'draft') {
                throw new LogicException('Indicators on published standards are immutable; create a new standard version.');
            }
        });
    }

    /** @return BelongsTo<QualityStandard, $this> */
    public function standard(): BelongsTo
    {
        return $this->belongsTo(QualityStandard::class, 'standard_id');
    }

    /** @return HasMany<PpeppActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(PpeppActivity::class, 'indicator_id');
    }
}
