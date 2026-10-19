<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'description', 'billing_interval', 'price', 'currency', 'status',
    'ai_quota_limit', 'storage_quota_mb', 'users_quota_limit', 'projects_quota_limit',
    'unit_quota_limit', 'feature_flags_json',
])]
class SaasPlan extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'feature_flags_json' => 'array',
            'ai_quota_limit' => 'integer',
            'storage_quota_mb' => 'integer',
            'users_quota_limit' => 'integer',
            'projects_quota_limit' => 'integer',
            'unit_quota_limit' => 'integer',
        ];
    }

    /** @return HasMany<InstitutionSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(InstitutionSubscription::class, 'saas_plan_id');
    }
}
