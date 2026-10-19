<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id', 'invoice_id', 'gateway', 'external_reference', 'idempotency_key', 'status',
    'amount', 'currency', 'provider_status', 'payload_json', 'metadata_json', 'processed_at',
])]
class Payment extends Model
{
    protected $table = 'payment_transactions';

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'payload_json' => 'array',
            'metadata_json' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<BillingInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }
}
