<?php

namespace Tests\Feature;

use App\Models\AccreditationBody;
use App\Models\AccreditationProject;
use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\Instruments\InstrumentVersionPublisher;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class AccreditationProjectTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private Institution $otherInstitution;

    private User $owner;

    private InstrumentVersion $publishedVersion;

    private InstrumentVersion $internalVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->institution = $this->createInstitution('p3-tenant-a', 'higher_education');
        $this->otherInstitution = $this->createInstitution('p3-tenant-b', 'higher_education');
        $this->owner = User::factory()->create();
        $ownerRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->attachMembership($this->institution, $this->owner, $ownerRole);

        $this->publishedVersion = $this->makeVersion('VERIFIED-PT', 'higher_education', true);
        $this->internalVersion = $this->makeVersion('INTERNAL-PT', 'higher_education', false);

        $platformUser = User::factory()->create();
        $platformRole = Role::query()->where('scope', 'platform')->where('slug', 'platform.super_admin')->firstOrFail();
        $platformUser->platformRoles()->attach($platformRole->getKey(), ['role_scope' => 'platform']);
        app(InstrumentVersionPublisher::class)->publish($this->publishedVersion, $platformUser);
    }

    public function test_project_uses_an_immutable_instrument_snapshot_and_initializes_indicators(): void
    {
        $this->actingAs($this->owner)
            ->get('/institutions/p3-tenant-a/projects/create')
            ->assertOk()
            ->assertSee('VERIFIED-PT')
            ->assertDontSee('INTERNAL-PT')
            ->assertSee('versi terverifikasi');

        $this->actingAs($this->owner)
            ->post('/institutions/p3-tenant-a/projects', [
                'title' => 'Self-evaluation test project',
                'instrument_version_id' => $this->publishedVersion->getKey(),
                'period_label' => 'Academic period test',
                'owner_user_id' => $this->owner->getKey(),
                'team_user_ids' => [],
                'confirmation' => '1',
            ])
            ->assertRedirect();

        $project = AccreditationProject::query()->where('institution_id', $this->institution->getKey())->firstOrFail();
        $originalSnapshot = $project->instrument_snapshot_json;
        $originalChecksum = $project->instrument_snapshot_checksum;
        $this->assertSame(
            hash('sha256', json_encode($originalSnapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            $originalChecksum,
        );
        $this->assertDatabaseHas('project_indicators', [
            'institution_id' => $this->institution->getKey(),
            'project_id' => $project->getKey(),
            'status' => 'not_started',
        ]);

        $newVersion = $this->makeVersion('VERIFIED-PT-2', 'higher_education', true);
        $platformUser = User::factory()->create();
        $platformRole = Role::query()->where('scope', 'platform')->where('slug', 'platform.super_admin')->firstOrFail();
        $platformUser->platformRoles()->attach($platformRole->getKey(), ['role_scope' => 'platform']);
        app(InstrumentVersionPublisher::class)->publish($newVersion, $platformUser);

        $this->assertSame($originalSnapshot, $project->fresh()->instrument_snapshot_json);
        $this->assertSame($originalChecksum, $project->fresh()->instrument_snapshot_checksum);
        $this->expectException(LogicException::class);
        $this->publishedVersion->fresh()->update(['notes' => 'Attempt to edit published version']);
    }

    public function test_draft_and_internal_instruments_cannot_be_selected_for_official_projects(): void
    {
        $this->actingAs($this->owner)
            ->post('/institutions/p3-tenant-a/projects', [
                'title' => 'Must not be created',
                'instrument_version_id' => $this->internalVersion->getKey(),
                'period_label' => 'Test period',
                'owner_user_id' => $this->owner->getKey(),
                'confirmation' => '1',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('accreditation_projects', 0);
    }

    public function test_tenant_cannot_view_or_mutate_a_project_from_another_tenant(): void
    {
        $project = $this->createProject();
        $tenantBOwner = User::factory()->create();
        $ownerRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->attachMembership($this->otherInstitution, $tenantBOwner, $ownerRole);

        $this->actingAs($tenantBOwner)
            ->get("/institutions/p3-tenant-b/projects/{$project->getKey()}")
            ->assertNotFound();

        $indicator = $project->indicators()->firstOrFail();
        $this->actingAs($tenantBOwner)
            ->patch("/institutions/p3-tenant-b/projects/{$project->getKey()}/indicators/{$indicator->getKey()}", [
                'status' => 'approved',
            ])
            ->assertNotFound();

        $this->assertSame('not_started', $indicator->fresh()->status);
    }

    public function test_project_indicators_can_track_status_owner_and_due_date_within_the_tenant(): void
    {
        $project = $this->createProject();
        $indicator = $project->indicators()->firstOrFail();

        $this->actingAs($this->owner)
            ->patch("/institutions/p3-tenant-a/projects/{$project->getKey()}/indicators/{$indicator->getKey()}", [
                'status' => 'in_review',
                'owner_user_id' => $this->owner->getKey(),
                'due_date' => today()->addWeek()->toDateString(),
            ])
            ->assertRedirect();

        $indicator = $indicator->fresh();
        $this->assertSame('in_review', $indicator->status);
        $this->assertSame($this->owner->getKey(), $indicator->owner_user_id);
        $this->assertSame(today()->addWeek()->toDateString(), $indicator->due_date->toDateString());
        $this->assertDatabaseHas('audit_logs', [
            'institution_id' => $this->institution->getKey(),
            'action' => 'project_indicator.updated',
        ]);
    }

    public function test_publishing_a_verified_version_requires_verified_body_and_source(): void
    {
        $version = $this->makeVersion('NO-BODY-VERIFICATION', 'higher_education', true);
        $version->instrument->accreditationBody->forceFill(['status' => AccreditationBody::STATUS_INTERNAL])->save();
        $platformUser = User::factory()->create();
        $platformRole = Role::query()->where('scope', 'platform')->where('slug', 'platform.super_admin')->firstOrFail();
        $platformUser->platformRoles()->attach($platformRole->getKey(), ['role_scope' => 'platform']);

        $this->expectException(ValidationException::class);
        app(InstrumentVersionPublisher::class)->publish($version, $platformUser);
    }

    public function test_school_and_higher_education_catalogs_are_filtered_by_tenant_domain(): void
    {
        $schoolInstitution = $this->createInstitution('p3-school-tenant', 'school');
        $schoolOwner = User::factory()->create();
        $ownerRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->attachMembership($schoolInstitution, $schoolOwner, $ownerRole);

        $schoolVersion = $this->makeVersion('VERIFIED-SCHOOL', 'school', true);
        $platformUser = User::factory()->create();
        $platformRole = Role::query()->where('scope', 'platform')->where('slug', 'platform.super_admin')->firstOrFail();
        $platformUser->platformRoles()->attach($platformRole->getKey(), ['role_scope' => 'platform']);
        app(InstrumentVersionPublisher::class)->publish($schoolVersion, $platformUser);

        $this->actingAs($this->owner)
            ->get('/institutions/p3-tenant-a/projects/create')
            ->assertOk()
            ->assertSee('VERIFIED-PT')
            ->assertDontSee('VERIFIED-SCHOOL');

        $this->actingAs($schoolOwner)
            ->get('/institutions/p3-school-tenant/projects/create')
            ->assertOk()
            ->assertSee('VERIFIED-SCHOOL')
            ->assertDontSee('VERIFIED-PT');
    }

    public function test_catalog_json_import_creates_only_an_internal_draft(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'varez-catalog-');
        $this->assertNotFalse($file);

        $payload = [
            'body' => [
                'domain_type' => 'school',
                'code' => 'TEST-IMPORT-BODY',
                'name' => 'Test-only imported body',
                'official_url' => 'https://reference.example.test/catalog',
            ],
            'instrument' => [
                'code' => 'TEST-IMPORT-INSTRUMENT',
                'name' => 'Test-only imported instrument',
                'instrument_type' => 'test_template',
            ],
            'version' => [
                'version_label' => 'test-import-v1',
                'source_url' => 'https://reference.example.test/version',
            ],
            'nodes' => [
                [
                    'parent_code' => 'ROOT',
                    'node_type' => 'indicator',
                    'code' => 'CHILD',
                    'title' => 'Test child node',
                    'rules' => [[
                        'rule_type' => 'example',
                        'rule_json' => ['value' => 1],
                        'severity' => 'warning',
                    ]],
                ],
                [
                    'node_type' => 'standard',
                    'code' => 'ROOT',
                    'title' => 'Test root node',
                ],
            ],
        ];

        try {
            file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));
            $exitCode = Artisan::call('catalog:import', ['file' => $file]);

            $this->assertSame(0, $exitCode, Artisan::output());
            $version = InstrumentVersion::query()->where('version_label', 'test-import-v1')->firstOrFail();
            $this->assertSame(InstrumentVersion::STATUS_DRAFT, $version->status);
            $this->assertSame(InstrumentVersion::VERIFICATION_INTERNAL, $version->verification_status);
            $this->assertSame(Instrument::STATUS_INTERNAL, $version->instrument->status);
            $this->assertSame(AccreditationBody::STATUS_INTERNAL, $version->instrument->accreditationBody->status);
            $this->assertDatabaseHas('instrument_nodes', [
                'instrument_version_id' => $version->getKey(),
                'code' => 'CHILD',
            ]);
            $this->assertDatabaseHas('instrument_node_rules', [
                'rule_type' => 'example',
                'severity' => 'warning',
            ]);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function test_member_without_project_create_permission_is_denied(): void
    {
        $memberRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.member')->firstOrFail();
        $member = User::factory()->create();
        $this->attachMembership($this->institution, $member, $memberRole);

        $this->actingAs($member)
            ->post('/institutions/p3-tenant-a/projects', [
                'title' => 'Not allowed',
                'instrument_version_id' => $this->publishedVersion->getKey(),
                'period_label' => 'Test period',
                'owner_user_id' => $member->getKey(),
                'confirmation' => '1',
            ])
            ->assertForbidden();
    }

    private function createProject(): AccreditationProject
    {
        $this->actingAs($this->owner)
            ->post('/institutions/p3-tenant-a/projects', [
                'title' => 'Tenant-scoped project',
                'instrument_version_id' => $this->publishedVersion->getKey(),
                'period_label' => 'Test period',
                'owner_user_id' => $this->owner->getKey(),
                'confirmation' => '1',
            ])
            ->assertRedirect();

        return AccreditationProject::query()
            ->where('institution_id', $this->institution->getKey())
            ->firstOrFail();
    }

    private function makeVersion(string $code, string $domain, bool $verified): InstrumentVersion
    {
        $body = AccreditationBody::query()->create([
            'domain_type' => $domain,
            'code' => 'BODY-'.$code,
            'name' => 'Test accreditation body '.$code,
            'official_url' => $verified ? 'https://authority.example.test/reference' : null,
            'status' => $verified ? AccreditationBody::STATUS_VERIFIED : AccreditationBody::STATUS_INTERNAL,
        ]);
        $instrument = Instrument::query()->create([
            'domain_type' => $domain,
            'accreditation_body_id' => $body->getKey(),
            'code' => 'INSTRUMENT-'.$code,
            'name' => $code,
            'instrument_type' => 'test_template',
            'status' => $verified ? Instrument::STATUS_PUBLISHED : Instrument::STATUS_INTERNAL,
        ]);
        $version = $instrument->versions()->create([
            'version_label' => 'v1',
            'effective_from' => today()->subYear(),
            'status' => InstrumentVersion::STATUS_DRAFT,
            'verification_status' => $verified
                ? InstrumentVersion::VERIFICATION_VERIFIED
                : InstrumentVersion::VERIFICATION_INTERNAL,
            'source_url' => $verified ? 'https://authority.example.test/instrument' : null,
        ]);
        $root = $version->nodes()->create([
            'node_type' => 'criterion',
            'code' => 'PARENT-'.$code,
            'title' => 'Test parent node',
        ]);
        $version->nodes()->create([
            'parent_id' => $root->getKey(),
            'node_type' => 'indicator',
            'code' => 'INDICATOR-'.$code,
            'title' => 'Test indicator',
            'guidance_text' => 'Test-only guidance',
        ]);

        return $version;
    }

    private function createInstitution(string $slug, string $domain): Institution
    {
        $institution = Institution::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'institution_type' => $domain,
        ]);
        $institution->forceFill(['status' => Institution::STATUS_ACTIVE])->save();

        return $institution;
    }

    private function attachMembership(Institution $institution, User $user, Role $role): InstitutionUser
    {
        return $institution->memberships()->create([
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'status' => InstitutionUser::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
    }
}
