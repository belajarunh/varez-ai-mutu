<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['domain_type', 'code', 'name', 'official_url', 'status'])]
class AccreditationBody extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_INTERNAL = 'internal';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_INACTIVE = 'inactive';

    protected static function booted(): void
    {
        static::saving(function (self $body): void {
            if ($body->status === self::STATUS_VERIFIED && filter_var($body->official_url, FILTER_VALIDATE_URL) === false) {
                throw new LogicException('A verified accreditation body must have a verifiable official URL.');
            }
        });

        static::updated(function (self $body): void {
            if ($body->wasChanged('status')) {
                DB::table('audit_logs')->insert([
                    'actor_user_id' => auth()->id(),
                    'action' => 'accreditation_body.status_changed',
                    'subject_type' => self::class,
                    'subject_id' => (string) $body->getKey(),
                    'after_json' => json_encode([
                        'code' => $body->code,
                        'name' => $body->name,
                        'status' => $body->status,
                    ], JSON_THROW_ON_ERROR),
                    'correlation_id' => (string) Str::uuid(),
                    'created_at' => now(),
                ]);
            }
        });
    }

    /** @return HasMany<Instrument, $this> */
    public function instruments(): HasMany
    {
        return $this->hasMany(Instrument::class);
    }
}
