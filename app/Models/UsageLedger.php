<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id', 'user_id', 'usage_type', 'quantity', 'unit', 'related_entity_type',
    'related_entity_id', 'period_key', 'idempotency_key', 'metadata_json',
])]
class UsageLedger extends Model
{
    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'quantity' => 'float',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
