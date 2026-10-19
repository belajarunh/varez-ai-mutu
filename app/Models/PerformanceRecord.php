<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id', 'dataset_id', 'record_key', 'record_payload_json', 'source_row', 'validation_status',
])]
class PerformanceRecord extends Model
{
    protected function casts(): array
    {
        return ['record_payload_json' => 'array'];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<PerformanceDataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(PerformanceDataset::class, 'dataset_id');
    }
}
