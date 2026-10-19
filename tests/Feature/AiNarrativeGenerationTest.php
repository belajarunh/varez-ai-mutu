<?php

namespace Tests\Feature;

use App\Models\AccreditationBody;
use App\Models\AccreditationProject;
use App\Models\AiGeneration;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentChunk;
use App\Models\DocumentVersion;
use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\ProjectIndicator;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\NarrativeDraftGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiNarrativeGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->seed(RolePermissionSeeder::class);
        config()->set('ai.embedding.provider', 'mock');
        config()->set('ai.generation.provider', 'mock');
    }

    public function test_generator_creates_draft_and_citations_for_accessible_sources(): void
    {
        $institution = $this->createInstitution('narrative-a');
        $owner = User::factory()->create();
        $ownerRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->attachMembership($institution, $owner, $ownerRole);

        $category = DocumentCategory::query()->create([
            'institution_id' => $institution->getKey(),
            'code' => 'evidence',
            'name' => 'Bukti',
            'ppepp_stage' => 'evaluasi',
            'access_default' => Document::ACCESS_INSTITUTION,
            'is_active' => true,
        ]);

        $document = $this->createDocument($institution, $owner, $category);
        $version = $document->currentVersion()->firstOrFail();
        DocumentChunk::query()->create([
            'institution_id' => $institution->getKey(),
            'document_version_id' => $version->getKey(),
            'chunk_index' => 0,
            'content_text' => 'Program studi melaksanakan evaluasi mutu secara berkala dan memperbarui prosedur berdasarkan hasil pemantauan.',
            'page_start' => 1,
            'page_end' => 1,
            'section_title' => 'Bab 1',
            'token_estimate' => 22,
            'content_hash' => hash('sha256', 'Program studi melaksanakan evaluasi mutu secara berkala dan memperbarui prosedur berdasarkan hasil pemantauan.'),
            'index_status' => 'ready',
            'metadata_json' => ['parser' => 'baseline'],
        ]);

        $project = AccreditationProject::query()->create([
            'institution_id' => $institution->getKey(),
            'unit_id' => null,
            'instrument_version_id' => $this->createInstrumentVersion($institution)->getKey(),
            'project_type' => 'self_assessment',
            'title' => 'Proyek mutu 2026',
            'period_label' => '2026',
            'status' => 'drafting',
            'target_date' => now()->addMonth(),
            'owner_user_id' => $owner->getKey(),
            'created_by' => $owner->getKey(),
            'instrument_snapshot_json' => ['title' => 'Instrumen contoh'],
            'instrument_snapshot_checksum' => hash('sha256', 'instrumen'),
        ]);

        $indicator = ProjectIndicator::query()->create([
            'institution_id' => $institution->getKey(),
            'project_id' => $project->getKey(),
            'source_instrument_node_id' => $this->createInstrumentNode($project->instrument_version_id)->getKey(),
            'snapshot_node_json' => ['title' => 'Evaluasi mutu'],
            'status' => 'draft',
            'owner_user_id' => $owner->getKey(),
            'due_date' => now()->addWeek(),
            'progress_metadata_json' => ['progress' => 50],
        ]);

        $generator = app(NarrativeDraftGenerator::class);
        $result = $generator->generate($institution, $owner, $project, $indicator, ['query' => 'Evaluasi mutu program studi']);

        $this->assertSame('draft', $result['status']);
        $this->assertNotEmpty($result['citations']);
        $this->assertDatabaseHas('ai_generations', ['institution_id' => $institution->getKey(), 'status' => 'draft']);
        $this->assertDatabaseHas('ai_citations', ['source_type' => 'document']);
    }

    public function test_generator_requests_more_evidence_when_no_accessible_or_ready_sources_exist(): void
    {
        $institution = $this->createInstitution('narrative-b');
        $owner = User::factory()->create();
        $role = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->attachMembership($institution, $owner, $role);

        $project = AccreditationProject::query()->create([
            'institution_id' => $institution->getKey(),
            'unit_id' => null,
            'instrument_version_id' => $this->createInstrumentVersion($institution)->getKey(),
            'project_type' => 'self_assessment',
            'title' => 'Proyek mutu 2027',
            'period_label' => '2027',
            'status' => 'data_collection',
            'target_date' => now()->addMonth(),
            'owner_user_id' => $owner->getKey(),
            'created_by' => $owner->getKey(),
            'instrument_snapshot_json' => ['title' => 'Instrumen demo'],
            'instrument_snapshot_checksum' => hash('sha256', 'demo'),
        ]);

        $indicator = ProjectIndicator::query()->create([
            'institution_id' => $institution->getKey(),
            'project_id' => $project->getKey(),
            'source_instrument_node_id' => $this->createInstrumentNode($project->instrument_version_id)->getKey(),
            'snapshot_node_json' => ['title' => 'Kepuasan mahasiswa'],
            'status' => 'needs_data',
            'owner_user_id' => $owner->getKey(),
            'due_date' => now()->addWeek(),
            'progress_metadata_json' => ['progress' => 0],
        ]);

        $generator = app(NarrativeDraftGenerator::class);
        $result = $generator->generate($institution, $owner, $project, $indicator, ['query' => 'Kepuasan mahasiswa']);

        $this->assertSame('needs_more_evidence', $result['status']);
        $this->assertNotEmpty($result['missing_data']);
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

    private function createInstrumentVersion(Institution $institution): \App\Models\InstrumentVersion
    {
        $body = AccreditationBody::query()->firstOrCreate([
            'domain_type' => 'higher_education',
            'code' => 'demo-body',
        ], [
            'name' => 'Demo Accreditation Body',
            'status' => 'draft',
        ]);

        $instrument = \App\Models\Instrument::query()->firstOrCreate([
            'domain_type' => 'higher_education',
            'code' => 'demo-instrument',
        ], [
            'accreditation_body_id' => $body->getKey(),
            'name' => 'Demo Instrument',
            'instrument_type' => 'self_assessment',
            'description' => 'Demo instrument for prompt-8 tests.',
            'status' => 'internal',
        ]);

        return \App\Models\InstrumentVersion::query()->firstOrCreate([
            'instrument_id' => $instrument->getKey(),
            'version_label' => 'v1',
        ], [
            'status' => 'draft',
            'verification_status' => 'internal',
            'notes' => 'Demo definition.',
        ]);
    }

    private function createInstrumentNode(int $versionId): \App\Models\InstrumentNode
    {
        return \App\Models\InstrumentNode::query()->firstOrCreate([
            'instrument_version_id' => $versionId,
            'code' => 'demo-node',
        ], [
            'parent_id' => null,
            'node_type' => 'indicator',
            'title' => 'Demo indicator node',
            'answer_type' => 'text',
            'sort_order' => 1,
            'required' => true,
        ]);
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

    private function createDocument(Institution $institution, User $owner, DocumentCategory $category): Document
    {
        $document = Document::query()->create([
            'institution_id' => $institution->getKey(),
            'unit_id' => null,
            'category_id' => $category->getKey(),
            'title' => 'Dokumen evaluasi mutu',
            'document_type' => 'evidence',
            'owner_user_id' => $owner->getKey(),
            'access_level' => Document::ACCESS_INSTITUTION,
            'verification_status' => Document::VERIFICATION_VERIFIED,
            'status' => Document::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
        ]);

        $version = DocumentVersion::query()->create([
            'institution_id' => $institution->getKey(),
            'document_id' => $document->getKey(),
            'version_number' => 1,
            'storage_disk' => 'local',
            'storage_key' => 'documents/evaluasi.csv',
            'original_filename' => 'evaluasi.csv',
            'file_extension' => 'csv',
            'mime_type' => 'text/csv',
            'size_bytes' => 100,
            'checksum_sha256' => hash('sha256', 'doc'),
            'uploaded_by' => $owner->getKey(),
            'uploaded_at' => now(),
            'extraction_status' => DocumentVersion::STATUS_READY,
            'scan_status' => 'not_scanned',
            'metadata_json' => ['source' => 'test'],
        ]);

        $document->forceFill(['current_version_id' => $version->getKey()])->save();

        return $document;
    }
}
