<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private User $owner;

    private Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->ownerRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->institution = Institution::query()->create([
            'name' => 'Institution invitation test',
            'slug' => 'invitation-test',
            'institution_type' => 'other',
        ]);
        $this->institution->forceFill(['status' => Institution::STATUS_ACTIVE])->save();
        $this->owner = User::factory()->create();
        $this->institution->memberships()->create([
            'user_id' => $this->owner->getKey(),
            'role_id' => $this->ownerRole->getKey(),
            'status' => InstitutionUser::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
    }

    public function test_invitation_token_is_only_returned_once_and_only_its_hash_is_persisted(): void
    {
        $memberRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.member')->firstOrFail();

        $response = $this->actingAs($this->owner)->postJson('/institutions/invitation-test/invitations', [
            'email' => 'new-member@example.test',
            'role_id' => $memberRole->getKey(),
        ])->assertCreated();

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('invitations', [
            'email' => 'new-member@example.test',
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('invitations', ['token_hash' => $token]);

        $log = AuditLog::query()->where('action', 'invitation.created')->firstOrFail();
        $this->assertStringNotContainsString($token, json_encode($log->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_invitation_can_only_be_accepted_by_the_invited_verified_user(): void
    {
        $memberRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.member')->firstOrFail();
        $token = str_repeat('a', 64);
        $invitation = $this->institution->invitations()->create([
            'role_id' => $memberRole->getKey(),
            'invited_by' => $this->owner->getKey(),
            'email' => 'invitee@example.test',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $otherUser = User::factory()->create(['email' => 'other@example.test']);
        $this->actingAs($otherUser)->postJson('/invitations/accept', ['token' => $token])
            ->assertUnprocessable();
        $this->assertNull($invitation->fresh()->accepted_at);

        $invitee = User::factory()->create(['email' => 'invitee@example.test']);
        $this->actingAs($invitee)->postJson('/invitations/accept', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.institution_id', $this->institution->getKey());

        $this->assertDatabaseHas('institution_users', [
            'institution_id' => $this->institution->getKey(),
            'user_id' => $invitee->getKey(),
            'role_id' => $memberRole->getKey(),
            'status' => InstitutionUser::STATUS_ACTIVE,
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_admin_cannot_promote_themselves_or_another_user_to_a_more_privileged_role(): void
    {
        $adminRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.admin')->firstOrFail();
        $admin = User::factory()->create();
        $adminMembership = $this->institution->memberships()->create([
            'user_id' => $admin->getKey(),
            'role_id' => $adminRole->getKey(),
            'status' => InstitutionUser::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patchJson("/institutions/invitation-test/members/{$adminMembership->getKey()}", [
                'role_id' => $this->ownerRole->getKey(),
            ])
            ->assertForbidden();

        $this->assertSame($adminRole->getKey(), $adminMembership->fresh()->role_id);
    }

    public function test_membership_change_is_audited_without_sensitive_fields(): void
    {
        $memberRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.member')->firstOrFail();
        $member = User::factory()->create();
        $membership = $this->institution->memberships()->create([
            'user_id' => $member->getKey(),
            'role_id' => $memberRole->getKey(),
            'status' => InstitutionUser::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->patchJson("/institutions/invitation-test/members/{$membership->getKey()}", [
                'status' => 'suspended',
                'password' => 'must-not-be-logged',
            ])
            ->assertOk();

        $log = AuditLog::query()->where('action', 'membership.updated')->firstOrFail();
        $payload = json_encode([$log->before_json, $log->after_json], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('password', $payload);
        $this->assertStringNotContainsString('must-not-be-logged', $payload);
    }

    public function test_the_last_active_owner_cannot_be_suspended(): void
    {
        $membership = $this->owner->institutionMemberships()->firstOrFail();

        $this->actingAs($this->owner)
            ->patchJson("/institutions/invitation-test/members/{$membership->getKey()}", [
                'status' => InstitutionUser::STATUS_SUSPENDED,
            ])
            ->assertStatus(409);

        $this->assertSame(InstitutionUser::STATUS_ACTIVE, $membership->fresh()->status);
    }
}
