<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;

class InstitutionPolicy
{
    public function view(User $user, Institution $institution): bool
    {
        return $user->hasPermission('institutions.view', $institution);
    }

    public function update(User $user, Institution $institution): bool
    {
        return $user->hasPermission('institutions.update', $institution);
    }

    public function archive(User $user, Institution $institution): bool
    {
        return $user->hasPermission('institutions.archive', $institution);
    }

    public function managePlatform(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }
}
