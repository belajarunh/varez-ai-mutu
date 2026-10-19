<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id', 'entity_type', 'entity_id', 'event', 'changes_json', 'actor_user_id', 'created_at',
])]
class QualityHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'changes_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
