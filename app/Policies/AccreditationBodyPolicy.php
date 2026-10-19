<?php

namespace App\Policies;

use App\Models\AccreditationBody;
use App\Models\User;

class AccreditationBodyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function view(User $user, AccreditationBody $body): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function update(User $user, AccreditationBody $body): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function delete(User $user, AccreditationBody $body): bool
    {
        return $user->hasPlatformRole('platform.super_admin')
            && ! $body->instruments()->exists();
    }
}
