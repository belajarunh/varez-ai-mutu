<?php

namespace Tests\Feature;

use App\Models\AuditFinding;
use App\Models\AuditSession;
use App\Models\CorrectiveAction;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\Permission;
use App\Models\PpeppActivity;
use App\Models\QualityIndicator;
use App\Models\QualityStandard;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QualityAssuranceTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private Institution $otherInstitution;

    private User $owner;

    private User $otherOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->institution = $this->createInstitution('quality-a');
        $this->otherInstitution = $this->createInstitution('quality-b');
        $this->owner = User::factory()->create();
        $this->otherOwner = User::factory()->create();
        $ownerRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->attachMembership($this->institution, $this->owner, $ownerRole);
        $this->attachMembership($this->otherInstitution, $this->otherOwner, $ownerRole);
    }

    public function test_published_standards_ppepp_activity_statuses_and_history_are_tenant_scoped(): void
    {
        $this->actingAs($this->owner)
            ->post($this->url('/quality/standards'), [
                'code' => 'STD-01',
                'title' => 'Standar mutu internal',
                'version' => 1,
                'effective_from' => '2026-01-01',
            ])
            ->assertRedirect();
        $standard = QualityStandard::query()->where('institution_id', $this->institution->getKey())->firstOrFail();

        $this->actingAs($this->owner)
            ->post($this->url("/quality/standards/{$standard->getKey()}/indicators"), [
                'code' => 'IND-01',
                'name' => 'Indikator mutu',
                'target_json' => ['value' => 'Ditentukan institusi'],
                'frequency' => 'semester',
                'sort_order' => 1,
            ])
            ->assertRedirect();
        $indicator = QualityIndicator::query()->where('institution_id', $this->institution->getKey())->firstOrFail();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/standards/{$standard->getKey()}/indicators/{$indicator->getKey()}"), [
                'code' => 'IND-01',
                'name' => 'Indikator mutu diperbarui',
                'target_json' => ['value' => 'Target sesuai kebijakan institusi'],
                'frequency' => 'semester',
                'measurement_method' => 'Metode institusi',
                'sort_order' => 1,
            ])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/standards/{$standard->getKey()}"), [
                'code' => 'STD-01',
                'title' => 'Standar mutu internal diperbarui',
                'version' => 1,
                'effective_from' => '2026-01-01',
            ])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/standards/{$standard->getKey()}/status"), ['status' => 'published'])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/standards/{$standard->getKey()}"), [
                'code' => 'STD-01',
                'title' => 'Tidak boleh diubah',
                'version' => 1,
            ])
            ->assertUnprocessable();

        $this->actingAs($this->owner)
            ->post($this->url('/quality/activities'), [
                'standard_id' => $standard->getKey(),
                'indicator_id' => $indicator->getKey(),
                'stage' => 'evaluasi',
                'title' => 'Evaluasi capaian indikator',
                'due_date' => '2026-12-01',
            ])
            ->assertRedirect();
        $activity = PpeppActivity::query()->where('institution_id', $this->institution->getKey())->firstOrFail();
        $this->actingAs($this->owner)
            ->post($this->url('/quality/policies'), [
                'activity_completion_requires_approval' => '1',
            ])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/activities/{$activity->getKey()}/status"), ['status' => 'in_progress'])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/activities/{$activity->getKey()}/status"), ['status' => 'completed'])
            ->assertUnprocessable();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/activities/{$activity->getKey()}/status"), ['status' => 'pending_approval'])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->post($this->url("/quality/activities/{$activity->getKey()}/approval"), [
                'decision' => 'return',
                'approval_notes' => 'Lengkapi bukti terlebih dahulu.',
            ])
            ->assertRedirect();
        $this->assertSame('in_progress', $activity->fresh()->status);
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/activities/{$activity->getKey()}/status"), ['status' => 'pending_approval'])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->post($this->url("/quality/activities/{$activity->getKey()}/approval"), [
                'decision' => 'approve',
                'approval_notes' => 'Aktivitas dan bukti telah ditinjau.',
            ])
            ->assertRedirect();

        $this->assertSame('completed', $activity->fresh()->status);
        $this->assertSame($this->owner->getKey(), $activity->fresh()->approved_by);
        $this->assertDatabaseHas('quality_histories', [
            'institution_id' => $this->institution->getKey(),
            'entity_type' => 'activity',
            'entity_id' => $activity->getKey(),
            'event' => 'quality.activity.status_changed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'institution_id' => $this->institution->getKey(),
            'action' => 'quality.activity.status_changed',
            'subject_id' => (string) $activity->getKey(),
        ]);

        $this->actingAs($this->owner)->get($this->url('/quality'))->assertOk()->assertSee('Penjaminan mutu');
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/activities/{$activity->getKey()}/status"), ['status' => 'in_progress'])
            ->assertUnprocessable();
    }

    public function test_capa_requires_configured_effectiveness_and_independent_verification_before_close(): void
    {
        $audit = $this->createAudit();
        $finding = AuditFinding::query()->create([
            'institution_id' => $this->institution->getKey(),
            'audit_session_id' => $audit->getKey(),
            'severity' => 'medium',
            'description' => 'Kesenjangan dalam proses evaluasi.',
            'status' => 'open',
        ]);
        $this->actingAs($this->owner)
            ->post($this->url("/quality/findings/{$finding->getKey()}/actions"), [
                'action_plan' => 'Melakukan tindakan perbaikan dan menyiapkan bukti.',
                'owner_user_id' => $this->owner->getKey(),
                'due_date' => '2026-12-01',
            ])
            ->assertRedirect();
        $action = CorrectiveAction::query()->where('finding_id', $finding->getKey())->firstOrFail();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/actions/{$action->getKey()}/status"), ['status' => 'in_progress'])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/actions/{$action->getKey()}/status"), ['status' => 'closed'])
            ->assertUnprocessable();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/actions/{$action->getKey()}/status"), ['status' => 'pending_verification'])
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->post($this->url("/quality/actions/{$action->getKey()}/verify"), [
                'effectiveness_status' => 'ineffective',
                'verification_notes' => 'Bukti belum menunjukkan perubahan yang diharapkan.',
            ])
            ->assertRedirect();
        $this->assertSame('in_progress', $action->fresh()->status);

        $this->actingAs($this->owner)
            ->post($this->url('/quality/policies'), [
                'require_effectiveness_verification' => '1',
                'require_independent_verifier' => '1',
            ])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/actions/{$action->getKey()}/status"), ['status' => 'pending_verification'])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->post($this->url("/quality/actions/{$action->getKey()}/verify"), [
                'effectiveness_status' => 'effective',
                'verification_notes' => 'Tidak boleh diverifikasi PIC sendiri.',
            ])
            ->assertUnprocessable();

        $verifier = User::factory()->create();
        $verifyRole = Role::query()->create([
            'scope' => 'tenant',
            'name' => 'Verifier mutu',
            'slug' => 'tenant.quality_verifier_test',
            'description' => null,
            'is_system' => false,
        ]);
        $verifyRole->permissions()->sync([
            Permission::query()->where('scope', 'tenant')->where('slug', 'quality.view')->value('id'),
            Permission::query()->where('scope', 'tenant')->where('slug', 'quality.actions.verify')->value('id'),
        ]);
        $this->attachMembership($this->institution, $verifier, $verifyRole);
        $this->actingAs($verifier)
            ->post($this->url("/quality/actions/{$action->getKey()}/verify"), [
                'effectiveness_status' => 'effective',
                'verification_notes' => 'Bukti tindakan dan hasil verifikasi memenuhi kebijakan.',
            ])
            ->assertRedirect();

        $this->assertSame('closed', $action->fresh()->status);
        $this->assertSame('closed', $finding->fresh()->status);
        $this->assertSame($verifier->getKey(), $action->fresh()->verified_by);
        $this->assertNotNull($action->fresh()->closed_at);
        $this->assertDatabaseHas('quality_histories', [
            'entity_type' => 'corrective_action',
            'entity_id' => $action->getKey(),
            'event' => 'quality.action.verified',
        ]);
    }

    public function test_overdue_dashboard_uses_institution_local_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 17:30:00', 'UTC'));
        $standard = $this->createPublishedStandard();
        PpeppActivity::query()->create([
            'institution_id' => $this->institution->getKey(),
            'standard_id' => $standard->getKey(),
            'stage' => 'evaluasi',
            'title' => 'Tenggat telah lewat di zona institusi',
            'due_date' => '2026-10-19',
            'status' => 'planned',
        ]);
        PpeppActivity::query()->create([
            'institution_id' => $this->institution->getKey(),
            'standard_id' => $standard->getKey(),
            'stage' => 'evaluasi',
            'title' => 'Jatuh tempo hari ini di zona institusi',
            'due_date' => '2026-10-20',
            'status' => 'planned',
        ]);

        $this->actingAs($this->owner)
            ->get($this->url('/quality'))
            ->assertOk()
            ->assertViewHas('today', '2026-10-20')
            ->assertViewHas('overdueActivities', 1);
        Carbon::setTestNow();
    }

    public function test_evidence_links_are_versioned_and_cannot_cross_tenants(): void
    {
        $standard = $this->createPublishedStandard();
        $activity = PpeppActivity::query()->create([
            'institution_id' => $this->institution->getKey(),
            'standard_id' => $standard->getKey(),
            'stage' => 'pelaksanaan',
            'title' => 'Pelaksanaan standar',
            'status' => 'planned',
        ]);
        $document = $this->createDocument($this->institution, $this->owner);
        $foreignDocument = $this->createDocument($this->otherInstitution, $this->otherOwner);

        $this->actingAs($this->owner)
            ->post($this->url("/quality/activities/{$activity->getKey()}/evidence"), [
                'evidence_version' => $document->getKey().':'.$document->current_version_id,
                'note' => 'Versi yang digunakan dalam aktivitas.',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('ppepp_evidence', [
            'institution_id' => $this->institution->getKey(),
            'activity_id' => $activity->getKey(),
            'document_id' => $document->getKey(),
            'document_version_id' => $document->current_version_id,
        ]);
        $this->assertDatabaseCount('documents', 2);

        $this->actingAs($this->owner)
            ->post($this->url("/quality/activities/{$activity->getKey()}/evidence"), [
                'evidence_version' => $foreignDocument->getKey().':'.$foreignDocument->current_version_id,
            ])
            ->assertNotFound();
        $this->assertDatabaseCount('ppepp_evidence', 1);
    }

    public function test_audit_planning_checklist_finding_evidence_and_completion_are_linked(): void
    {
        $standard = $this->createPublishedStandard();
        $indicator = $standard->indicators()->firstOrFail();
        $auditor = User::factory()->create();
        $auditorRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.quality_auditor')->firstOrFail();
        $this->attachMembership($this->institution, $auditor, $auditorRole);
        $this->actingAs($this->owner)
            ->post($this->url('/quality/audits'), [
                'title' => 'AMI semester berjalan',
                'scope' => 'Evaluasi penerapan standar dan indikator yang ditetapkan institusi.',
                'lead_auditor_id' => $this->owner->getKey(),
                'auditor_ids' => [$auditor->getKey()],
                'starts_at' => '2026-11-01T09:00',
                'ends_at' => '2026-11-01T16:00',
            ])
            ->assertRedirect();
        $audit = AuditSession::query()->where('institution_id', $this->institution->getKey())->firstOrFail();
        $this->assertDatabaseHas('audit_session_auditors', [
            'institution_id' => $this->institution->getKey(),
            'audit_session_id' => $audit->getKey(),
            'user_id' => $this->owner->getKey(),
        ]);
        $this->actingAs($this->owner)
            ->post($this->url("/quality/audits/{$audit->getKey()}/checklist"), [
                'standard_id' => $standard->getKey(),
                'indicator_id' => $indicator->getKey(),
                'question' => 'Apakah target indikator telah dievaluasi?',
                'sort_order' => 1,
            ])
            ->assertRedirect();
        $item = $audit->checklistItems()->firstOrFail();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/audits/{$audit->getKey()}/status"), ['status' => 'in_progress'])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/audits/{$audit->getKey()}/status"), ['status' => 'completed'])
            ->assertUnprocessable();
        $this->actingAs($auditor)
            ->patch($this->url("/quality/checklist/{$item->getKey()}"), [
                'status' => 'non_compliant',
                'notes' => 'Institusi perlu menindaklanjuti hasil evaluasi.',
            ])
            ->assertRedirect();
        $this->actingAs($this->owner)
            ->post($this->url("/quality/audits/{$audit->getKey()}/findings"), [
                'checklist_item_id' => $item->getKey(),
                'severity' => 'medium',
                'description' => 'Evaluasi belum terdokumentasi lengkap.',
                'root_cause' => 'Proses pengumpulan belum memiliki penanggung jawab yang ditetapkan.',
                'recommendation' => 'Tetapkan PIC dan jadwal evaluasi.',
                'owner_user_id' => $this->owner->getKey(),
                'due_date' => '2026-12-01',
            ])
            ->assertRedirect();
        $finding = AuditFinding::query()->where('audit_session_id', $audit->getKey())->firstOrFail();
        $document = $this->createDocument($this->institution, $this->owner);
        $this->actingAs($this->owner)
            ->post($this->url("/quality/findings/{$finding->getKey()}/evidence"), [
                'evidence_version' => $document->getKey().':'.$document->current_version_id,
                'note' => 'Bukti yang ditinjau auditor.',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('audit_finding_evidence', [
            'institution_id' => $this->institution->getKey(),
            'finding_id' => $finding->getKey(),
            'document_id' => $document->getKey(),
            'document_version_id' => $document->current_version_id,
        ]);
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/audits/{$audit->getKey()}/status"), ['status' => 'completed'])
            ->assertRedirect();
        $this->assertSame('completed', $audit->fresh()->status);
        $this->assertDatabaseHas('quality_histories', [
            'entity_type' => 'audit_session',
            'entity_id' => $audit->getKey(),
            'event' => 'quality.audit.status_changed',
        ]);
    }

    public function test_unit_scoped_quality_member_cannot_view_or_change_another_units_activity(): void
    {
        $unitA = $this->institution->organizationalUnits()->create([
            'unit_type' => 'department',
            'name' => 'Unit A',
            'code' => 'UNIT-A',
        ]);
        $unitB = $this->institution->organizationalUnits()->create([
            'unit_type' => 'department',
            'name' => 'Unit B',
            'code' => 'UNIT-B',
        ]);
        $standard = $this->createPublishedStandard();
        $activityA = PpeppActivity::query()->create([
            'institution_id' => $this->institution->getKey(),
            'standard_id' => $standard->getKey(),
            'unit_id' => $unitA->getKey(),
            'stage' => 'evaluasi',
            'title' => 'Unit A activity',
            'status' => 'planned',
        ]);
        $activityB = PpeppActivity::query()->create([
            'institution_id' => $this->institution->getKey(),
            'standard_id' => $standard->getKey(),
            'unit_id' => $unitB->getKey(),
            'stage' => 'evaluasi',
            'title' => 'Unit B activity',
            'status' => 'planned',
        ]);
        $member = User::factory()->create();
        $role = Role::query()->create([
            'scope' => 'tenant',
            'name' => 'Unit quality member',
            'slug' => 'tenant.unit_quality_test',
            'description' => null,
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()
            ->where('scope', 'tenant')
            ->whereIn('slug', ['quality.view', 'quality.activities.manage', 'units.view'])
            ->pluck('id'));
        $this->attachMembership($this->institution, $member, $role);
        $member->unitAssignments()->create([
            'institution_id' => $this->institution->getKey(),
            'organizational_unit_id' => $unitA->getKey(),
            'access_level' => 'member',
        ]);

        $this->actingAs($member)->get($this->url('/quality'))->assertOk()
            ->assertSee('Unit A activity')->assertDontSee('Unit B activity');
        $this->actingAs($member)
            ->patch($this->url("/quality/activities/{$activityB->getKey()}/status"), ['status' => 'in_progress'])
            ->assertNotFound();
        $this->assertSame('planned', $activityA->fresh()->status);
        $this->assertSame('planned', $activityB->fresh()->status);
    }

    public function test_tenant_cannot_read_or_mutate_foreign_quality_records(): void
    {
        $foreignStandard = $this->createPublishedStandard($this->otherInstitution);
        $foreignActivity = PpeppActivity::query()->create([
            'institution_id' => $this->otherInstitution->getKey(),
            'standard_id' => $foreignStandard->getKey(),
            'stage' => 'evaluasi',
            'title' => 'Tenant lain',
            'status' => 'planned',
        ]);
        $member = User::factory()->create();
        $memberRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.member')->firstOrFail();
        $this->attachMembership($this->institution, $member, $memberRole);

        $this->actingAs($member)->get($this->url('/quality'))->assertOk()->assertDontSee('Tenant lain');
        $this->actingAs($member)
            ->post($this->url('/quality/standards'), [
                'code' => 'DENIED',
                'title' => 'No permission',
                'version' => 1,
            ])
            ->assertForbidden();
        $this->actingAs($member)
            ->patch($this->url("/quality/activities/{$foreignActivity->getKey()}/status"), ['status' => 'in_progress'])
            ->assertForbidden();
        $this->actingAs($this->owner)
            ->patch($this->url("/quality/activities/{$foreignActivity->getKey()}/status"), ['status' => 'in_progress'])
            ->assertNotFound();
        $this->assertSame('planned', $foreignActivity->fresh()->status);
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

    private function attachMembership(Institution $institution, User $user, Role $role): InstitutionUser
    {
        return $institution->memberships()->create([
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'status' => InstitutionUser::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
    }

    private function createPublishedStandard(?Institution $institution = null): QualityStandard
    {
        $institution ??= $this->institution;
        $standard = QualityStandard::query()->create([
            'institution_id' => $institution->getKey(),
            'code' => 'STD-'.str_replace('-', '', (string) $institution->slug),
            'title' => 'Standar mutu terbit',
            'version' => 1,
            'status' => 'draft',
        ]);
        QualityIndicator::query()->create([
            'institution_id' => $institution->getKey(),
            'standard_id' => $standard->getKey(),
            'code' => 'IND-01',
            'name' => 'Indikator mutu',
            'sort_order' => 1,
        ]);
        $standard->status = 'published';
        $standard->save();

        return $standard;
    }

    private function createAudit(): AuditSession
    {
        return AuditSession::query()->create([
            'institution_id' => $this->institution->getKey(),
            'title' => 'AMI internal',
            'scope_json' => ['description' => 'Ruang lingkup internal'],
            'lead_auditor_id' => $this->owner->getKey(),
            'starts_at' => now(),
            'status' => 'in_progress',
        ]);
    }

    private function createDocument(Institution $institution, User $owner): Document
    {
        $category = DocumentCategory::query()->create([
            'institution_id' => $institution->getKey(),
            'code' => 'cat-'.str_replace('-', '', (string) $institution->slug),
            'name' => 'Dokumen bukti',
            'access_default' => Document::ACCESS_INSTITUTION,
            'is_active' => true,
        ]);
        $document = Document::query()->create([
            'institution_id' => $institution->getKey(),
            'category_id' => $category->getKey(),
            'title' => 'Bukti mutu',
            'document_type' => 'csv',
            'owner_user_id' => $owner->getKey(),
            'access_level' => Document::ACCESS_INSTITUTION,
            'verification_status' => Document::VERIFICATION_UNVERIFIED,
            'status' => Document::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
        ]);
        $version = DocumentVersion::query()->create([
            'institution_id' => $institution->getKey(),
            'document_id' => $document->getKey(),
            'version_number' => 1,
            'storage_disk' => 'local',
            'storage_key' => 'documents/test.csv',
            'original_filename' => 'evidence.csv',
            'file_extension' => 'csv',
            'mime_type' => 'text/csv',
            'size_bytes' => 10,
            'checksum_sha256' => hash('sha256', 'evidence'),
            'uploaded_by' => $owner->getKey(),
            'uploaded_at' => now(),
            'extraction_status' => DocumentVersion::STATUS_READY,
            'scan_status' => 'not_scanned',
        ]);
        $document->forceFill(['current_version_id' => $version->getKey()])->save();

        return $document->fresh();
    }

    private function url(string $path): string
    {
        return "/institutions/{$this->institution->slug}{$path}";
    }
}
