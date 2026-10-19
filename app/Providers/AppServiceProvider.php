<?php

namespace App\Providers;

use App\Models\Institution;
use App\Models\OrganizationalUnit;
use App\Policies\InstitutionPolicy;
use App\Policies\OrganizationalUnitPolicy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Institution::class, InstitutionPolicy::class);
        Gate::policy(OrganizationalUnit::class, OrganizationalUnitPolicy::class);

        Gate::define(
            'manageMembers',
            fn ($user, Institution $institution): bool => $user->hasPermission('members.manage', $institution),
        );

        Gate::define(
            'viewAuditLogs',
            fn ($user, Institution $institution): bool => $user->hasPermission('audit_logs.view', $institution),
        );
    }
}
