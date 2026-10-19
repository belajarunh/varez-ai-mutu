<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<InstitutionUser, $this> */
    public function institutionMemberships(): HasMany
    {
        return $this->hasMany(InstitutionUser::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('role_scope')
            ->wherePivot('role_scope', 'platform');
    }

    /** @return HasMany<UserUnitAssignment, $this> */
    public function unitAssignments(): HasMany
    {
        return $this->hasMany(UserUnitAssignment::class);
    }

    public function hasPlatformRole(string $roleSlug): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->platformRoles()
            ->where('scope', 'platform')
            ->where('slug', $roleSlug)
            ->exists();
    }

    public function hasPendingInstitutionInvitation(): bool
    {
        return Invitation::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($this->email)])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereHas('institution', fn ($query) => $query->where('status', Institution::STATUS_ACTIVE))
            ->exists();
    }

    public function hasPermission(string $permissionSlug, ?Institution $institution = null): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($institution === null) {
            return $this->platformRoles()
                ->whereHas('permissions', fn ($query) => $query
                    ->where('scope', 'platform')
                    ->where('slug', $permissionSlug))
                ->exists();
        }

        if ($institution->status !== Institution::STATUS_ACTIVE) {
            return false;
        }

        return $this->institutionMemberships()
            ->where('institution_id', $institution->getKey())
            ->where('status', InstitutionUser::STATUS_ACTIVE)
            ->whereHas('role.permissions', fn ($query) => $query
                ->where('scope', 'tenant')
                ->where('slug', $permissionSlug))
            ->exists();
    }

    public function canAccessUnit(Institution $institution, OrganizationalUnit $unit): bool
    {
        if ($unit->institution_id !== $institution->getKey()
            || ! $this->hasPermission('units.view', $institution)) {
            return false;
        }

        return in_array((int) $unit->getKey(), $this->accessibleUnitIds($institution), true);
    }

    /**
     * @return list<int>
     */
    public function accessibleUnitIds(Institution $institution): array
    {
        $units = $institution->organizationalUnits()
            ->where('status', OrganizationalUnit::STATUS_ACTIVE)
            ->get(['id', 'parent_id']);

        if ($this->hasPermission('units.access_all', $institution)) {
            return $units->modelKeys();
        }

        $allowed = $this->unitAssignments()
            ->where('institution_id', $institution->getKey())
            ->pluck('organizational_unit_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $allowed = array_fill_keys($allowed, true);

        do {
            $changed = false;

            foreach ($units as $unit) {
                if ($unit->parent_id !== null
                    && isset($allowed[(int) $unit->parent_id])
                    && ! isset($allowed[(int) $unit->getKey()])) {
                    $allowed[(int) $unit->getKey()] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        return array_map('intval', array_keys($allowed));
    }

    public function canAssignRole(Role $role, Institution $institution): bool
    {
        if ($role->scope !== 'tenant' || ! $this->hasPermission('members.manage', $institution)) {
            return false;
        }

        $actorRole = $this->institutionMemberships()
            ->where('institution_id', $institution->getKey())
            ->where('status', InstitutionUser::STATUS_ACTIVE)
            ->with('role.permissions:id,scope,slug')
            ->first()?->role;

        if ($actorRole === null) {
            return false;
        }

        $actorPermissionSlugs = $actorRole->permissions
            ->where('scope', 'tenant')
            ->pluck('slug')
            ->all();

        return $role->permissions()
            ->where('scope', 'tenant')
            ->whereNotIn('slug', $actorPermissionSlugs)
            ->doesntExist();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->hasPlatformRole('platform.super_admin')
            || $this->hasPendingInstitutionInvitation()
            || $this->institutionMemberships()
                ->where('status', InstitutionUser::STATUS_ACTIVE)
                ->whereHas('institution', fn ($query) => $query->where('status', Institution::STATUS_ACTIVE))
                ->whereHas('role.permissions', fn ($query) => $query
                    ->where('scope', 'tenant')
                    ->where('slug', 'panel.access'))
                ->exists();
    }
}
