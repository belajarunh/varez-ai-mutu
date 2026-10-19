<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\Invitations\InvitationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    public function store(
        Request $request,
        TenantContext $tenantContext,
        InvitationService $invitationService,
    ): JsonResponse {
        $institution = $tenantContext->institution();
        Gate::authorize('manageMembers', $institution);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $role = Role::query()->whereKey($data['role_id'])->where('scope', 'tenant')->firstOrFail();
        if (! $request->user()->canAssignRole($role, $institution)) {
            abort(403);
        }

        $issued = $invitationService->issue($institution, $role, $request->user(), $data['email']);

        return response()->json([
            'data' => [
                'id' => $issued['invitation']->getKey(),
                'expires_at' => $issued['invitation']->expires_at,
                'token' => $issued['token'],
            ],
            'message' => 'Simpan token undangan dengan aman dan kirim kepada penerima melalui kanal tepercaya. Token hanya ditampilkan sekali.',
        ], 201)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function accept(Request $request, InvitationService $invitationService): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $membership = $invitationService->accept($request->user(), $data['token']);
        $membership->load(['institution:id,name,slug', 'role:id,name,slug']);

        return response()->json([
            'data' => $membership->only(['id', 'institution_id', 'role_id', 'status', 'joined_at'])
                + ['institution' => $membership->institution, 'role' => $membership->role],
        ]);
    }
}
