<?php

namespace App\Policies;

use App\Models\InstrumentNodeRule;
use App\Models\InstrumentVersion;
use App\Models\User;

class InstrumentNodeRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function view(User $user, InstrumentNodeRule $rule): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function update(User $user, InstrumentNodeRule $rule): bool
    {
        return $user->hasPlatformRole('platform.super_admin')
            && $rule->node()->first()?->version()->value('status') === InstrumentVersion::STATUS_DRAFT;
    }

    public function delete(User $user, InstrumentNodeRule $rule): bool
    {
        return $this->update($user, $rule);
    }
}
