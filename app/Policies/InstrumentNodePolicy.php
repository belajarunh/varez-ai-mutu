<?php

namespace App\Policies;

use App\Models\InstrumentNode;
use App\Models\InstrumentVersion;
use App\Models\User;

class InstrumentNodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function view(User $user, InstrumentNode $node): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function update(User $user, InstrumentNode $node): bool
    {
        return $user->hasPlatformRole('platform.super_admin')
            && $node->version()->value('status') === InstrumentVersion::STATUS_DRAFT;
    }

    public function delete(User $user, InstrumentNode $node): bool
    {
        return $user->hasPlatformRole('platform.super_admin')
            && $node->version()->value('status') === InstrumentVersion::STATUS_DRAFT;
    }
}
