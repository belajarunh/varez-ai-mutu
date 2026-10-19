<?php

namespace App\Policies;

use App\Models\AccreditationProject;
use App\Models\Institution;
use App\Models\User;

class AccreditationProjectPolicy
{
    public function viewAny(User $user, Institution $institution): bool
    {
        return $user->hasPermission('projects.view', $institution);
    }

    public function view(User $user, AccreditationProject $project): bool
    {
        return $user->hasPermission('projects.view', $project->institution);
    }

    public function create(User $user, Institution $institution): bool
    {
        return $user->hasPermission('projects.create', $institution);
    }

    public function update(User $user, AccreditationProject $project): bool
    {
        return $user->hasPermission('projects.manage', $project->institution);
    }
}
