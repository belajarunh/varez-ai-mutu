<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_an_active_platform_super_admin_can_create_and_change_institution_status(): void
    {
        $tenantUser = User::factory()->create();

        $this->actingAs($tenantUser)
            ->postJson('/platform/institutions', $this->institutionPayload($tenantUser))
            ->assertForbidden();

        $superAdmin = User::factory()->create();
        $superAdminRole = Role::query()
            ->where('scope', 'platform')
            ->where('slug', 'platform.super_admin')
            ->firstOrFail();
        $superAdmin->platformRoles()->syncWithoutDetaching([
            $superAdminRole->getKey() => ['role_scope' => 'platform'],
        ]);

        $created = $this->actingAs($superAdmin)
            ->postJson('/platform/institutions', $this->institutionPayload($tenantUser))
            ->assertCreated()
            ->assertJsonPath('data.status', Institution::STATUS_PENDING);

        $institutionId = $created->json('data.id');
        $this->patchJson("/platform/institutions/{$institutionId}/status", ['status' => Institution::STATUS_ACTIVE])
            ->assertOk()
            ->assertJsonPath('data.status', Institution::STATUS_ACTIVE);

        $this->assertDatabaseHas('institution_users', [
            'institution_id' => $institutionId,
            'user_id' => $tenantUser->getKey(),
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'institution.created',
            'actor_user_id' => $superAdmin->getKey(),
            'institution_id' => $institutionId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'institution.status_changed',
            'actor_user_id' => $superAdmin->getKey(),
            'institution_id' => $institutionId,
        ]);

        $superAdmin->forceFill(['status' => 'suspended'])->save();

        $this->patchJson("/platform/institutions/{$institutionId}/status", ['status' => Institution::STATUS_SUSPENDED])
            ->assertForbidden();
    }

    /**
     * @return array{name: string, slug: string, institution_type: string, timezone: string, owner_email: string}
     */
    private function institutionPayload(User $owner): array
    {
        return [
            'name' => 'Fictional Demo Institution',
            'slug' => 'fictional-demo-institution',
            'institution_type' => 'higher_education',
            'timezone' => 'Asia/Jakarta',
            'owner_email' => $owner->email,
        ];
    }
}
