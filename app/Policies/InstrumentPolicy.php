<?php

namespace App\Policies;

use App\Models\Instrument;
use App\Models\User;

class InstrumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function view(User $user, Instrument $instrument): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function update(User $user, Instrument $instrument): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function delete(User $user, Instrument $instrument): bool
    {
        return $user->hasPlatformRole('platform.super_admin')
            && ! $instrument->versions()->exists();
    }
}
