<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id', 'require_effectiveness_verification',
    'require_independent_verifier', 'activity_completion_requires_approval', 'settings_json',
])]
class QualityPolicy extends Model
{
    protected function casts(): array
    {
        return [
            'require_effectiveness_verification' => 'boolean',
            'require_independent_verifier' => 'boolean',
            'activity_completion_requires_approval' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
