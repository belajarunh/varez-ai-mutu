<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'institution_id', 'subscription_id', 'invoice_number', 'status', 'amount', 'currency',
    'issued_at', 'due_at', 'paid_at', 'line_items_json',
])]
class BillingInvoice extends Model
{
    protected $table = 'billing_invoices';

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'line_items_json' => 'array',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<InstitutionSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(InstitutionSubscription::class, 'subscription_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
}
