<?php

namespace App\Providers;

use App\Models\AccreditationBody;
use App\Models\AccreditationProject;
use App\Models\Institution;
use App\Models\Instrument;
use App\Models\InstrumentNode;
use App\Models\InstrumentNodeRule;
use App\Models\InstrumentVersion;
use App\Models\OrganizationalUnit;
use App\Policies\AccreditationBodyPolicy;
use App\Policies\AccreditationProjectPolicy;
use App\Policies\InstitutionPolicy;
use App\Policies\InstrumentNodePolicy;
use App\Policies\InstrumentNodeRulePolicy;
use App\Policies\InstrumentPolicy;
use App\Policies\InstrumentVersionPolicy;
use App\Policies\OrganizationalUnitPolicy;
use App\Support\Tenancy\TenantContext;
use App\Services\Ai\AiServiceProvider;
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
        $this->app->register(AiServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Institution::class, InstitutionPolicy::class);
        Gate::policy(OrganizationalUnit::class, OrganizationalUnitPolicy::class);
        Gate::policy(AccreditationBody::class, AccreditationBodyPolicy::class);
        Gate::policy(AccreditationProject::class, AccreditationProjectPolicy::class);
        Gate::policy(Instrument::class, InstrumentPolicy::class);
        Gate::policy(InstrumentVersion::class, InstrumentVersionPolicy::class);
        Gate::policy(InstrumentNode::class, InstrumentNodePolicy::class);
        Gate::policy(InstrumentNodeRule::class, InstrumentNodeRulePolicy::class);

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
