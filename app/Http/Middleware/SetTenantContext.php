<?php

namespace App\Http\Middleware;

use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $institutionParameter = $request->route('institution');
        $slug = $institutionParameter instanceof Institution
            ? $institutionParameter->slug
            : $institutionParameter;

        if (! is_string($slug)) {
            abort(404);
        }

        $institution = Institution::query()
            ->where('slug', $slug)
            ->where('status', Institution::STATUS_ACTIVE)
            ->first();

        if ($institution === null || $request->user()?->status !== 'active') {
            abort(404);
        }

        $membership = InstitutionUser::query()
            ->where('institution_id', $institution->getKey())
            ->where('user_id', $request->user()->getKey())
            ->where('status', InstitutionUser::STATUS_ACTIVE)
            ->first();

        if ($membership === null) {
            abort(404);
        }

        $this->tenantContext->set($institution, $membership);
        $request->attributes->set(Institution::class, $institution);
        $request->attributes->set(InstitutionUser::class, $membership);

        return $next($request);
    }
}
