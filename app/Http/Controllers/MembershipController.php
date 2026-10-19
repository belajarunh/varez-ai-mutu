<?php

namespace App\Http\Controllers;

use App\Models\InstitutionUser;
use App\Models\Role;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MembershipController extends Controller
{
    public function index(TenantContext $tenantContext): JsonResponse
    {
        $institution = $tenantContext->institution();
        Gate::authorize('manageMembers', $institution);

        $members = $institution->memberships()
            ->with(['user:id,name,email,status,email_verified_at', 'role:id,name,slug'])
            ->orderBy('id')
            ->get(['id', 'user_id', 'role_id', 'status', 'joined_at']);

        return response()->json(['data' => $members]);
    }

    public function update(
        Request $request,
        string $institution,
        int $membership,
        TenantContext $tenantContext,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $institution = $tenantContext->institution();
        Gate::authorize('manageMembers', $institution);

        $record = $institution->memberships()->whereKey($membership)->firstOrFail();
        $data = $request->validate([
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'status' => ['sometimes', 'required', 'in:active,suspended'],
        ]);

        if ($data === []) {
            abort(422, 'Tidak ada perubahan yang diberikan.');
        }

        $roleId = $data['role_id'] ?? $record->role_id;
        $role = Role::query()->whereKey($roleId)->where('scope', 'tenant')->firstOrFail();
        if (! $request->user()->canAssignRole($role, $institution)) {
            abort(403);
        }

        DB::transaction(function () use ($record, $role, $data, $institution, $auditLogger): void {
            $lockedMembership = InstitutionUser::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $before = ['role_id' => $lockedMembership->role_id, 'status' => $lockedMembership->status];

            $isActiveOwner = $lockedMembership->status === InstitutionUser::STATUS_ACTIVE
                && $lockedMembership->role->slug === 'tenant.owner';
            $wouldStopBeingOwner = ($data['status'] ?? $lockedMembership->status) !== InstitutionUser::STATUS_ACTIVE
                || $role->slug !== 'tenant.owner';

            if (
                $isActiveOwner
                && $wouldStopBeingOwner
                && $institution->memberships()
                    ->where('status', InstitutionUser::STATUS_ACTIVE)
                    ->whereHas('role', fn ($query) => $query->where('slug', 'tenant.owner'))
                    ->count() <= 1
            ) {
                abort(409, 'Institusi harus memiliki setidaknya satu pemilik aktif.');
            }

            $lockedMembership->fill([
                'role_id' => $role->getKey(),
                'role_scope' => 'tenant',
                'status' => $data['status'] ?? $lockedMembership->status,
            ])->save();

            $auditLogger->record(
                'membership.updated',
                $lockedMembership,
                $institution,
                $before,
                ['role_id' => $lockedMembership->role_id, 'status' => $lockedMembership->status],
            );
        });

        return response()->json(['data' => $record->fresh()->load('role:id,name,slug')]);
    }
}
