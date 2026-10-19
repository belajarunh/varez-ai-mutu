<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\OrganizationalUnit;
use App\Models\User;

class OrganizationalUnitPolicy
{
    public function viewAny(User $user, Institution $institution): bool
    {
        return $user->hasPermission('units.view', $institution);
    }

    public function view(User $user, OrganizationalUnit $unit): bool
    {
        return $user->canAccessUnit($unit->institution, $unit);
    }

    public function create(User $user, Institution $institution): bool
    {
        return $user->hasPermission('units.create', $institution)
            && $user->hasPermission('units.access_all', $institution);
    }

    public function update(User $user, OrganizationalUnit $unit): bool
    {
        return $user->hasPermission('units.update', $unit->institution)
            && $user->canAccessUnit($unit->institution, $unit);
    }

    public function archive(User $user, OrganizationalUnit $unit): bool
    {
        return $user->hasPermission('units.archive', $unit->institution)
            && $user->canAccessUnit($unit->institution, $unit);
    }
}
