<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institutionA;

    private Institution $institutionB;

    private User $ownerA;

    private Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->ownerRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->institutionA = $this->createInstitution('tenant-a');
        $this->institutionB = $this->createInstitution('tenant-b');
        $this->ownerA = User::factory()->create();
        $this->attachMembership($this->ownerA, $this->institutionA, $this->ownerRole);
    }

    public function test_user_can_read_and_update_their_institution_but_cannot_read_or_change_another_tenant(): void
    {
        $this->actingAs($this->ownerA)
            ->getJson('/institutions/tenant-a')
            ->assertOk()
            ->assertJsonPath('data.slug', 'tenant-a');

        $this->actingAs($this->ownerA)
            ->getJson('/institutions/tenant-b')
            ->assertNotFound();

        $this->actingAs($this->ownerA)
            ->patchJson('/institutions/tenant-b', ['name' => 'Tampered'])
            ->assertNotFound();

        $this->assertDatabaseHas('institutions', [
            'id' => $this->institutionB->getKey(),
            'name' => 'Institution tenant-b',
        ]);
    }

    public function test_unit_ids_are_scoped_to_the_validated_tenant(): void
    {
        $unitB = $this->institutionB->organizationalUnits()->create([
            'unit_type' => 'other',
            'name' => 'Private unit B',
            'code' => 'UNIT-B',
        ]);

        $this->actingAs($this->ownerA)
            ->getJson("/institutions/tenant-a/units/{$unitB->getKey()}")
            ->assertNotFound();

        $this->actingAs($this->ownerA)
            ->getJson('/institutions/tenant-a/units')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Private unit B']);
    }

    public function test_membership_without_the_required_permission_is_denied_server_side(): void
    {
        $member = User::factory()->create();
        $memberRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.member')->firstOrFail();
        $this->attachMembership($member, $this->institutionA, $memberRole);

        $this->actingAs($member)
            ->patchJson('/institutions/tenant-a', ['name' => 'Unauthorized edit'])
            ->assertForbidden();
    }

    public function test_suspended_membership_cannot_access_the_tenant_context(): void
    {
        $this->ownerA->institutionMemberships()
            ->where('institution_id', $this->institutionA->getKey())
            ->update(['status' => InstitutionUser::STATUS_SUSPENDED]);

        $this->actingAs($this->ownerA)
            ->getJson('/institutions/tenant-a')
            ->assertNotFound();
    }

    public function test_unassigned_user_cannot_list_other_units_in_their_institution(): void
    {
        $unitOne = $this->institutionA->organizationalUnits()->create([
            'unit_type' => 'department',
            'name' => 'Assigned department',
            'code' => 'ASSIGNED',
        ]);
        $unitTwo = $this->institutionA->organizationalUnits()->create([
            'unit_type' => 'department',
            'name' => 'Unassigned department',
            'code' => 'UNASSIGNED',
        ]);

        $scopedRole = Role::query()->create([
            'scope' => 'tenant',
            'name' => 'Scoped unit reader',
            'slug' => 'test.scoped_reader',
        ]);
        $scopedRole->permissions()->sync(
            Permission::query()
                ->where('scope', 'tenant')
                ->whereIn('slug', ['institutions.view', 'units.view'])
                ->pluck('id'),
        );

        $scopedUser = User::factory()->create();
        $this->attachMembership($scopedUser, $this->institutionA, $scopedRole);
        $scopedUser->unitAssignments()->create([
            'institution_id' => $this->institutionA->getKey(),
            'organizational_unit_id' => $unitOne->getKey(),
            'access_level' => 'member',
        ]);

        $this->actingAs($scopedUser)
            ->getJson('/institutions/tenant-a/units')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Assigned department'])
            ->assertJsonMissing(['name' => 'Unassigned department']);

        $this->assertNotSame($unitOne->getKey(), $unitTwo->getKey());
    }

    private function createInstitution(string $slug): Institution
    {
        $institution = Institution::query()->create([
            'name' => "Institution {$slug}",
            'slug' => $slug,
            'institution_type' => 'other',
            'timezone' => 'Asia/Jakarta',
        ]);
        $institution->forceFill(['status' => Institution::STATUS_ACTIVE])->save();

        return $institution;
    }

    private function attachMembership(User $user, Institution $institution, Role $role): InstitutionUser
    {
        return $institution->memberships()->create([
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'status' => InstitutionUser::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
    }
}
