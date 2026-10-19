<?php

namespace App\Services\Invitations;

use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array{invitation: Invitation, token: string}
     */
    public function issue(Institution $institution, Role $role, User $inviter, string $email): array
    {
        if ($role->scope !== 'tenant') {
            throw ValidationException::withMessages(['role_id' => 'Pilih peran institusi yang valid.']);
        }

        $email = mb_strtolower(trim($email));
        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($institution, $role, $inviter, $email, $token): Invitation {
            $invitation = $institution->invitations()->create([
                'role_id' => $role->getKey(),
                'invited_by' => $inviter->getKey(),
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);

            $this->auditLogger->record(
                'invitation.created',
                $invitation,
                $institution,
                after: ['role_id' => $role->getKey(), 'expires_at' => $invitation->expires_at->toISOString()],
            );

            return $invitation;
        });

        return ['invitation' => $invitation, 'token' => $token];
    }

    public function accept(User $user, string $token): InstitutionUser
    {
        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['token' => 'Akun ini tidak dapat menerima undangan.']);
        }

        return DB::transaction(function () use ($user, $token): InstitutionUser {
            $invitation = Invitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (
                $invitation === null
                || $invitation->accepted_at !== null
                || $invitation->revoked_at !== null
                || $invitation->expires_at->isPast()
                || ! hash_equals($invitation->email, mb_strtolower($user->email))
            ) {
                throw ValidationException::withMessages(['token' => 'Undangan tidak valid atau sudah kedaluwarsa.']);
            }

            $institution = Institution::query()
                ->whereKey($invitation->institution_id)
                ->where('status', Institution::STATUS_ACTIVE)
                ->first();

            if ($institution === null || $invitation->role->scope !== 'tenant') {
                throw ValidationException::withMessages(['token' => 'Undangan tidak dapat diterima.']);
            }

            $existingMembership = InstitutionUser::query()
                ->where('institution_id', $institution->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingMembership !== null) {
                throw ValidationException::withMessages(['token' => 'Akun ini sudah memiliki keanggotaan institusi.']);
            }

            $membership = InstitutionUser::query()->create([
                'institution_id' => $institution->getKey(),
                'user_id' => $user->getKey(),
                'role_id' => $invitation->role_id,
                'role_scope' => 'tenant',
                'status' => InstitutionUser::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            $this->auditLogger->record(
                'membership.created',
                $membership,
                $institution,
                after: ['role_id' => $membership->role_id, 'status' => $membership->status],
            );
            $this->auditLogger->record(
                'invitation.accepted',
                $invitation,
                $institution,
                after: ['role_id' => $invitation->role_id, 'status' => 'accepted'],
            );

            return $membership;
        });
    }
}
