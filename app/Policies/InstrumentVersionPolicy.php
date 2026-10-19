<?php

namespace App\Policies;

use App\Models\InstrumentVersion;
use App\Models\User;

class InstrumentVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function view(User $user, InstrumentVersion $version): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function update(User $user, InstrumentVersion $version): bool
    {
        return $user->hasPlatformRole('platform.super_admin')
            && $version->status === InstrumentVersion::STATUS_DRAFT;
    }

    public function delete(User $user, InstrumentVersion $version): bool
    {
        return $user->hasPlatformRole('platform.super_admin')
            && $version->status === InstrumentVersion::STATUS_DRAFT
            && ! $version->projects()->exists();
    }
}
