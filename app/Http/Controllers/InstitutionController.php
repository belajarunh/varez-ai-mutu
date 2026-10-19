<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InstitutionController extends Controller
{
    public function storePlatform(
        Request $request,
        AuditLogger $auditLogger,
    ): JsonResponse {
        Gate::authorize('managePlatform', Institution::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('institutions', 'slug')],
            'institution_type' => ['required', 'in:higher_education,school,other'],
            'legal_identifier' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'owner_email' => ['required', 'email', 'max:254'],
        ]);

        $owner = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['owner_email'])])
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->firstOrFail();
        $ownerRole = Role::query()
            ->where('scope', 'tenant')
            ->where('slug', 'tenant.owner')
            ->firstOrFail();

        $institution = DB::transaction(function () use ($data, $owner, $ownerRole, $auditLogger): Institution {
            $institution = Institution::query()->create([
                'name' => $data['name'],
                'slug' => Str::lower($data['slug']),
                'institution_type' => $data['institution_type'],
                'legal_identifier' => $data['legal_identifier'] ?? null,
                'timezone' => $data['timezone'],
            ]);
            $institution->forceFill(['status' => Institution::STATUS_PENDING])->save();

            $membership = $institution->memberships()->create([
                'user_id' => $owner->getKey(),
                'role_id' => $ownerRole->getKey(),
                'role_scope' => 'tenant',
                'status' => InstitutionUser::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            $auditLogger->record(
                'institution.created',
                $institution,
                $institution,
                after: ['name' => $institution->name, 'slug' => $institution->slug, 'status' => $institution->status],
            );
            $auditLogger->record(
                'membership.created',
                $membership,
                $institution,
                after: ['role_id' => $membership->role_id, 'status' => $membership->status],
            );

            return $institution;
        });

        return response()->json([
            'data' => $institution->only(['id', 'name', 'slug', 'institution_type', 'status', 'timezone']),
        ], 201);
    }

    public function show(TenantContext $tenantContext): JsonResponse
    {
        $institution = $tenantContext->institution();
        Gate::authorize('view', $institution);

        return response()->json([
            'data' => $institution->only(['id', 'name', 'slug', 'institution_type', 'legal_identifier', 'status', 'timezone']),
        ]);
    }

    public function update(Request $request, TenantContext $tenantContext, AuditLogger $auditLogger): JsonResponse
    {
        $institution = $tenantContext->institution();
        Gate::authorize('update', $institution);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'institution_type' => ['sometimes', 'required', 'in:higher_education,school,other'],
            'legal_identifier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'required', 'timezone'],
        ]);

        $before = $institution->only(array_keys($data));
        $institution->fill($data)->save();
        $auditLogger->record('institution.updated', $institution, $institution, $before, $institution->only(array_keys($data)));

        return response()->json([
            'data' => $institution->only(['id', 'name', 'slug', 'institution_type', 'legal_identifier', 'status', 'timezone']),
        ]);
    }

    public function updatePlatformStatus(
        Request $request,
        Institution $institution,
        AuditLogger $auditLogger,
    ): JsonResponse {
        Gate::authorize('managePlatform', Institution::class);

        $data = $request->validate([
            'status' => ['required', 'in:active,suspended,closed'],
        ]);

        DB::transaction(function () use ($institution, $data, $auditLogger): void {
            $before = ['status' => $institution->status];
            $institution->forceFill(['status' => $data['status']])->save();
            $auditLogger->record(
                'institution.status_changed',
                $institution,
                $institution,
                before: $before,
                after: ['status' => $data['status']],
            );
        });

        return response()->json([
            'data' => $institution->only(['id', 'name', 'slug', 'status']),
        ]);
    }
}
