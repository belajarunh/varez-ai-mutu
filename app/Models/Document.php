<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $access_level
 * @property string $verification_status
 * @property string $status
 * @property Carbon|null $retention_until
 */
#[Fillable([
    'institution_id', 'unit_id', 'category_id', 'title', 'description', 'document_type',
    'academic_period_id', 'owner_user_id', 'access_level', 'verification_status', 'status', 'current_version_id',
    'retention_until',
    'created_by',
])]
class Document extends Model
{
    public const ACCESS_PRIVATE = 'private';

    public const ACCESS_UNIT = 'unit';

    public const ACCESS_INSTITUTION = 'institution';

    public const VERIFICATION_UNVERIFIED = 'unverified';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_REJECTED = 'rejected';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return ['retention_until' => 'date'];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Documents are versioned records and cannot be deleted.');
        });
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'unit_id');
    }

    /** @return BelongsTo<DocumentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    /** @return HasMany<DocumentRelation, $this> */
    public function relations(): HasMany
    {
        return $this->hasMany(DocumentRelation::class);
    }
}
