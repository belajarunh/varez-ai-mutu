<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_batch_id', 'institution_id', 'row_number', 'column_name', 'error_code', 'message',
    'row_payload_json', 'is_fatal',
])]
class ImportError extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'row_payload_json' => 'array',
            'is_fatal' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
