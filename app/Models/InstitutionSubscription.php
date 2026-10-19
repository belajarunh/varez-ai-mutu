<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'institution_id', 'saas_plan_id', 'status', 'starts_at', 'ends_at', 'manual_activation',
    'gateway', 'external_customer_id', 'metadata_json',
])]
class InstitutionSubscription extends Model
{
    protected function casts(): array
    {
        return [
            'manual_activation' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<SaasPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    /** @return HasMany<BillingInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class, 'subscription_id');
    }
}
