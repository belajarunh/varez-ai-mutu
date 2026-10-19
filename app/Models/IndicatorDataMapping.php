<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id', 'instrument_node_id', 'dataset_type', 'version', 'mapping_json',
])]
class IndicatorDataMapping extends Model
{
    protected function casts(): array
    {
        return ['mapping_json' => 'array'];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<InstrumentNode, $this> */
    public function instrumentNode(): BelongsTo
    {
        return $this->belongsTo(InstrumentNode::class);
    }
}
