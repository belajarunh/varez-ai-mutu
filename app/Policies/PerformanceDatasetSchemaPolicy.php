<?php

namespace App\Policies;

use App\Models\PerformanceDatasetSchema;
use App\Models\User;

class PerformanceDatasetSchemaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function view(User $user, PerformanceDatasetSchema $schema): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPlatformRole('platform.super_admin');
    }

    public function update(User $user, PerformanceDatasetSchema $schema): bool
    {
        return $user->hasPlatformRole('platform.super_admin')
            && $schema->status === PerformanceDatasetSchema::STATUS_DRAFT;
    }

    public function delete(User $user, PerformanceDatasetSchema $schema): bool
    {
        return $user->hasPlatformRole('platform.super_admin')
            && $schema->status === PerformanceDatasetSchema::STATUS_DRAFT
            && ! $schema->importBatches()->exists();
    }
}
