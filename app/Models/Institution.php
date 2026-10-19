<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'institution_type', 'legal_identifier', 'timezone', 'settings_json'])]
#[Guarded(['status'])]
class Institution extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    protected function casts(): array
    {
        return ['settings_json' => 'array'];
    }

    /** @return HasMany<InstitutionUser, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(InstitutionUser::class);
    }

    /** @return HasMany<OrganizationalUnit, $this> */
    public function organizationalUnits(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class);
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
