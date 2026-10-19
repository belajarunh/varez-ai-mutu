<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['domain_type', 'accreditation_body_id', 'code', 'name', 'instrument_type', 'description', 'status'])]
class Instrument extends Model
{
    public const STATUS_INTERNAL = 'internal';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_RETIRED = 'retired';

    /** @return BelongsTo<AccreditationBody, $this> */
    public function accreditationBody(): BelongsTo
    {
        return $this->belongsTo(AccreditationBody::class);
    }

    /** @return HasMany<InstrumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(InstrumentVersion::class);
    }
}
