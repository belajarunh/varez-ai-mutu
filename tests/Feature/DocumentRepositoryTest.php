<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentVersion;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentChunk;
use App\Models\DocumentVersion;
use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Documents\DocumentChunker;
use App\Services\Documents\DocumentFileInspector;
use App\Services\Documents\DocumentProcessor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class DocumentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private Institution $otherInstitution;

    private User $owner;

    private User $otherOwner;

    private DocumentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->seed(RolePermissionSeeder::class);
        $this->institution = $this->createInstitution('documents-a');
        $this->otherInstitution = $this->createInstitution('documents-b');
        $this->owner = User::factory()->create();
        $this->otherOwner = User::factory()->create();
        $ownerRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->attachMembership($this->institution, $this->owner, $ownerRole);
        $this->attachMembership($this->otherInstitution, $this->otherOwner, $ownerRole);
        $this->category = DocumentCategory::query()->create([
            'institution_id' => $this->institution->getKey(),
            'code' => 'evidence',
            'name' => 'Bukti institusi',
            'ppepp_stage' => 'evaluasi',
            'access_default' => Document::ACCESS_INSTITUTION,
            'is_active' => true,
        ]);
    }

    public function test_upload_is_private_audited_and_extraction_creates_ready_chunks(): void
    {
        $this->actingAs($this->owner)
            ->post($this->url('/documents'), [
                'title' => 'Laporan evaluasi mutu',
                'category_id' => $this->category->getKey(),
                'file' => $this->csvFile(),
            ])
            ->assertRedirect();

        $document = Document::query()->where('institution_id', $this->institution->getKey())->firstOrFail();
        $version = $document->currentVersion()->firstOrFail();
        $this->assertSame(DocumentVersion::STATUS_QUEUED, $version->extraction_status);
        $this->assertSame('csv', $version->file_extension);
        $this->assertTrue(Storage::disk('local')->exists($version->storage_key));
        $this->assertFalse(Storage::disk('public')->exists($version->storage_key));
        $this->assertDatabaseHas('audit_logs', [
            'institution_id' => $this->institution->getKey(),
            'action' => 'document.uploaded',
            'subject_id' => (string) $document->getKey(),
        ]);
        Queue::assertPushed(ProcessDocumentVersion::class);

        app(DocumentProcessor::class)->process((int) $this->institution->getKey(), (int) $version->getKey());
        $version->refresh();
        $this->assertSame(DocumentVersion::STATUS_READY, $version->extraction_status);
        $this->assertSame('not_scanned', $version->scan_status);
        $this->assertGreaterThan(0, $version->extracted_character_count);
        $this->assertGreaterThan(0, DocumentChunk::query()->where('document_version_id', $version->getKey())->count());
        $this->assertSame('ready', DocumentChunk::query()->where('document_version_id', $version->getKey())->firstOrFail()->index_status);
    }

    public function test_new_version_keeps_old_file_and_updates_current_version(): void
    {
        $document = $this->uploadDocument();
        $firstVersion = $document->currentVersion()->firstOrFail();
        $oldKey = $firstVersion->storage_key;

        $this->actingAs($this->owner)
            ->post($this->url("/documents/{$document->getKey()}/versions"), ['file' => $this->csvFile()])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame(2, $document->currentVersion->version_number);
        $this->assertCount(2, $document->versions);
        $this->assertTrue(Storage::disk('local')->exists($oldKey));
        $this->assertTrue(Storage::disk('local')->exists($document->currentVersion->storage_key));
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.version_uploaded']);
    }

    public function test_cross_tenant_ids_private_documents_and_unauthorized_downloads_are_denied(): void
    {
        $foreignDocument = $this->uploadDocumentFor($this->otherInstitution, $this->otherOwner, 'other-evidence');

        $this->actingAs($this->owner)
            ->get($this->url("/documents/{$foreignDocument->getKey()}"))
            ->assertNotFound();
        $this->actingAs($this->owner)
            ->get($this->url("/documents/{$foreignDocument->getKey()}/versions/{$foreignDocument->current_version_id}/download"))
            ->assertNotFound();

        $privateCategory = DocumentCategory::query()->create([
            'institution_id' => $this->institution->getKey(),
            'code' => 'private',
            'name' => 'Bukti terbatas',
            'access_default' => Document::ACCESS_PRIVATE,
            'is_active' => true,
        ]);
        $privateDocument = $this->uploadDocument($privateCategory);
        $member = User::factory()->create();
        $readerRole = $this->createDocumentReaderRole();
        $this->attachMembership($this->institution, $member, $readerRole);

        $this->actingAs($member)->get($this->url("/documents/{$privateDocument->getKey()}"))->assertNotFound();
        $this->actingAs($member)
            ->get($this->url("/documents/{$privateDocument->getKey()}/versions/{$privateDocument->current_version_id}/download"))
            ->assertNotFound();
    }

    public function test_unit_restricted_member_cannot_view_documents_from_another_unit(): void
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
        $documentA = $this->uploadDocument();
        $documentA->forceFill(['unit_id' => $unitA->getKey(), 'access_level' => Document::ACCESS_UNIT])->save();
        $documentB = $this->uploadDocumentFor($this->institution, $this->owner, 'unit-b');
        $documentB->forceFill(['unit_id' => $unitB->getKey(), 'access_level' => Document::ACCESS_UNIT])->save();

        $member = User::factory()->create();
        $role = $this->createDocumentReaderRole(['documents.view', 'documents.download', 'units.view']);
        $this->attachMembership($this->institution, $member, $role);
        $member->unitAssignments()->create([
            'institution_id' => $this->institution->getKey(),
            'organizational_unit_id' => $unitA->getKey(),
            'access_level' => 'member',
        ]);

        $this->actingAs($member)
            ->get($this->url('/documents'))
            ->assertOk()
            ->assertSee('Evidence evidence')
            ->assertDontSee('Evidence unit-b');
        $this->actingAs($member)
            ->get($this->url("/documents/{$documentB->getKey()}"))
            ->assertNotFound();
        $this->actingAs($member)
            ->get($this->url("/documents/{$documentB->getKey()}/versions/{$documentB->current_version_id}/download"))
            ->assertNotFound();
        $this->assertNotSame($unitA->getKey(), $unitB->getKey());
    }

    public function test_download_requires_permission_and_is_audited(): void
    {
        $document = $this->uploadDocument();
        $member = User::factory()->create();
        $readerRole = $this->createDocumentReaderRole(['documents.view']);
        $this->attachMembership($this->institution, $member, $readerRole);

        $this->actingAs($member)
            ->get($this->url("/documents/{$document->getKey()}/versions/{$document->current_version_id}/download"))
            ->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'document.downloaded']);

        $this->actingAs($this->owner)
            ->get($this->url("/documents/{$document->getKey()}/versions/{$document->current_version_id}/download"))
            ->assertNotFound();
        app(DocumentProcessor::class)->process(
            (int) $this->institution->getKey(),
            (int) $document->current_version_id,
        );
        $this->actingAs($this->owner)
            ->get($this->url("/documents/{$document->getKey()}/versions/{$document->current_version_id}/download"))
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-disposition', 'attachment; filename=evidence.csv');
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.downloaded']);
    }

    public function test_configured_scanner_failure_blocks_downloads_until_reviewed(): void
    {
        $document = $this->uploadDocument();
        $versionId = (int) $document->current_version_id;
        config(['documents.scanner_binary' => 'missing-varez-antivirus.exe']);

        try {
            app(DocumentProcessor::class)->process((int) $this->institution->getKey(), $versionId);
            $this->fail('A configured but unavailable scanner must fail processing.');
        } catch (\RuntimeException $exception) {
            (new ProcessDocumentVersion((int) $this->institution->getKey(), $versionId))->failed($exception);
        }

        $version = DocumentVersion::query()->findOrFail($versionId);
        $this->assertSame('error', $version->scan_status);
        $this->assertSame(DocumentVersion::STATUS_FAILED, $version->extraction_status);
        $this->actingAs($this->owner)
            ->get($this->url("/documents/{$document->getKey()}/versions/{$versionId}/download"))
            ->assertNotFound();
    }

    public function test_tenant_portal_document_pages_render_for_an_authorized_member(): void
    {
        $document = $this->uploadDocument();

        $this->actingAs($this->owner)
            ->get($this->url('/documents'))
            ->assertOk()
            ->assertSee('Repositori dokumen');
        $this->actingAs($this->owner)
            ->get($this->url('/documents/create'))
            ->assertOk()
            ->assertSee('Unggah bukti');
        $this->actingAs($this->owner)
            ->get($this->url('/documents/categories'))
            ->assertOk()
            ->assertSee('Kategori dan siklus PPEPP');
        $this->actingAs($this->owner)
            ->get($this->url("/documents/{$document->getKey()}"))
            ->assertOk()
            ->assertSee('Riwayat versi');
    }

    public function test_forged_signature_macro_archives_and_oversized_uploads_are_rejected(): void
    {
        $forged = UploadedFile::fake()->createWithContent('fake.pdf', 'not actually a PDF');
        $this->expectUploadFailure($forged);

        $macro = $this->officeFile('macro.docx', 'word/document.xml', '<w:document/>', true);
        $this->expectUploadFailure($macro);

        $oversized = UploadedFile::fake()->create('large.csv', 25_601, 'text/csv');
        $this->actingAs($this->owner)
            ->post($this->url('/documents'), [
                'title' => 'Terlalu besar',
                'category_id' => $this->category->getKey(),
                'file' => $oversized,
            ])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_docx_extraction_preserves_heading_and_image_without_ocr_needs_review(): void
    {
        $docx = $this->officeFile(
            'evidence.docx',
            'word/document.xml',
            '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Evaluasi Mutu</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Temuan bukti audit internal</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>',
        );
        $this->actingAs($this->owner)
            ->post($this->url('/documents'), [
                'title' => 'Dokumen DOCX',
                'category_id' => $this->category->getKey(),
                'file' => $docx,
            ])
            ->assertRedirect();
        $document = Document::query()->latest('id')->firstOrFail();
        $version = $document->currentVersion()->firstOrFail();
        app(DocumentProcessor::class)->process((int) $this->institution->getKey(), (int) $version->getKey());

        $version->refresh();
        $this->assertSame(DocumentVersion::STATUS_READY, $version->extraction_status);
        $docxChunks = DocumentChunk::query()->where('document_version_id', $version->getKey())->get();
        $this->assertSame('Evaluasi Mutu', $docxChunks->firstOrFail()->section_title);
        $this->assertStringContainsString('Temuan bukti audit internal', $docxChunks->pluck('content_text')->implode("\n"));

        $image = UploadedFile::fake()->createWithContent(
            'scan.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/n9sAAAAASUVORK5CYII=', true) ?: '',
        );
        $this->actingAs($this->owner)
            ->post($this->url('/documents'), [
                'title' => 'Gambar bukti',
                'category_id' => $this->category->getKey(),
                'file' => $image,
            ])
            ->assertRedirect();
        $imageVersion = DocumentVersion::query()->latest('id')->firstOrFail();
        app(DocumentProcessor::class)->process((int) $this->institution->getKey(), (int) $imageVersion->getKey());
        $this->assertSame(DocumentVersion::STATUS_NEEDS_REVIEW, $imageVersion->fresh()->extraction_status);
        $this->assertSame('ocr-unavailable', $imageVersion->fresh()->metadata_json['parser']);
        $this->assertDatabaseMissing('document_chunks', ['document_version_id' => $imageVersion->getKey()]);
    }

    public function test_pdf_xlsx_and_pptx_are_extracted_with_page_or_sheet_context(): void
    {
        $pdf = UploadedFile::fake()->createWithContent('evidence.pdf', $this->minimalPdf());
        $this->uploadAndProcess($pdf, 'Evidence PDF');
        $pdfVersion = DocumentVersion::query()->latest('id')->firstOrFail();
        $this->assertSame(DocumentVersion::STATUS_READY, $pdfVersion->extraction_status);
        $this->assertSame(1, $pdfVersion->page_count);
        $this->assertSame(1, DocumentChunk::query()->where('document_version_id', $pdfVersion->getKey())->firstOrFail()->page_start);

        $xlsx = UploadedFile::fake()->createWithContent('metrics.xlsx', $this->minimalXlsx());
        $this->uploadAndProcess($xlsx, 'Evidence XLSX');
        $xlsxVersion = DocumentVersion::query()->latest('id')->firstOrFail();
        $this->assertSame(DocumentVersion::STATUS_READY, $xlsxVersion->extraction_status);
        $this->assertSame('Quality metrics', DocumentChunk::query()->where('document_version_id', $xlsxVersion->getKey())->firstOrFail()->section_title);

        $pptx = $this->officePackage('evidence.pptx', [
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'ppt/presentation.xml' => '<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"/>',
            'ppt/slides/slide1.xml' => '<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:p><a:r><a:t>Evidence for continuous quality improvement and institutional audit review</a:t></a:r></a:p></p:sld>',
        ]);
        $this->uploadAndProcess($pptx, 'Evidence PPTX');
        $pptxVersion = DocumentVersion::query()->latest('id')->firstOrFail();
        $this->assertSame(DocumentVersion::STATUS_READY, $pptxVersion->extraction_status);
        $this->assertSame(1, $pptxVersion->page_count);
        $this->assertStringContainsString(
            'Evidence for continuous quality improvement and institutional audit review',
            DocumentChunk::query()->where('document_version_id', $pptxVersion->getKey())->firstOrFail()->content_text,
        );
    }

    public function test_invalid_office_xml_is_marked_failed_after_job_retries(): void
    {
        $invalid = $this->officeFile('broken.docx', 'word/document.xml', '<w:document><invalid>');
        $this->uploadAndProcess($invalid, 'Broken DOCX', false);
        $version = DocumentVersion::query()->latest('id')->firstOrFail();

        try {
            app(DocumentProcessor::class)->process((int) $this->institution->getKey(), (int) $version->getKey());
            $this->fail('Malformed office XML must fail extraction.');
        } catch (ValidationException $exception) {
            (new ProcessDocumentVersion((int) $this->institution->getKey(), (int) $version->getKey()))
                ->failed($exception);
        }
        $this->assertSame(DocumentVersion::STATUS_FAILED, $version->fresh()->extraction_status);
        $this->assertSame(['error_code' => 'processing_failed'], $version->fresh()->metadata_json);
    }

    public function test_category_and_relation_targets_are_scoped_to_the_tenant(): void
    {
        $this->actingAs($this->owner)
            ->post($this->url('/documents/categories'), [
                'code' => 'peningkatan',
                'name' => 'Peningkatan',
                'ppepp_stage' => 'peningkatan',
                'access_default' => 'institution',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('document_categories', [
            'institution_id' => $this->institution->getKey(),
            'code' => 'peningkatan',
        ]);

        $document = $this->uploadDocument();
        $this->actingAs($this->owner)
            ->post($this->url("/documents/{$document->getKey()}/relations"), [
                'related_target' => 'project:999999',
                'relation_type' => 'supports',
            ])
            ->assertNotFound();
        $this->assertDatabaseCount('document_relations', 0);
    }

    public function test_chunking_does_not_create_a_duplicate_chunk_at_the_end_of_input(): void
    {
        $chunker = app(DocumentChunker::class);
        $exactLimitChunks = $chunker->chunk([
            ['page' => 1, 'section' => 'Section', 'text' => str_repeat('x', 3000)],
        ], 'test');
        $longChunks = $chunker->chunk([
            ['page' => 1, 'section' => 'Section', 'text' => str_repeat('evidence quality improvement ', 120)],
        ], 'test');

        $this->assertCount(1, $exactLimitChunks);
        $this->assertCount(2, $longChunks);
    }

    private function uploadDocument(?DocumentCategory $category = null): Document
    {
        return $this->uploadDocumentFor($this->institution, $this->owner, 'evidence', $category);
    }

    private function uploadDocumentFor(
        Institution $institution,
        User $user,
        string $code,
        ?DocumentCategory $category = null,
    ): Document {
        $category ??= DocumentCategory::query()->firstOrCreate(
            ['institution_id' => $institution->getKey(), 'code' => $code],
            ['name' => 'Evidence', 'access_default' => Document::ACCESS_INSTITUTION, 'is_active' => true],
        );
        $this->actingAs($user)
            ->post("/institutions/{$institution->slug}/documents", [
                'title' => 'Evidence '.$code,
                'category_id' => $category->getKey(),
                'file' => $this->csvFile(),
            ])
            ->assertRedirect();

        return Document::query()
            ->where('institution_id', $institution->getKey())
            ->latest('id')
            ->firstOrFail();
    }

    private function csvFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'evidence.csv',
            "Judul,Keterangan\nEvaluasi mutu,Temuan audit internal dan tindak lanjut terdokumentasi dengan bukti yang telah ditinjau",
        );
    }

    private function officeFile(string $name, string $documentPath, string $documentXml, bool $macro = false): UploadedFile
    {
        return $this->officePackage($name, [
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            $documentPath => $documentXml,
        ], $macro ? ['word/vbaProject.bin' => 'macro bytes'] : []);
    }

    /**
     * @param  array<string, string>  $files
     * @param  array<string, string>  $extraFiles
     */
    private function officePackage(string $name, array $files, array|bool $extraFiles = []): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'varez-docx-');
        if ($path === false) {
            $this->fail('Could not create a temporary Office archive.');
        }
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($files as $entry => $contents) {
            $archive->addFromString($entry, $contents);
        }
        if (is_bool($extraFiles)) {
            $extraFiles = $extraFiles ? ['word/vbaProject.bin' => 'macro bytes'] : [];
        }
        foreach ($extraFiles as $entry => $contents) {
            $archive->addFromString($entry, $contents);
        }
        $archive->close();
        $contents = file_get_contents($path);
        unlink($path);
        $this->assertIsString($contents);

        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function uploadAndProcess(UploadedFile $file, string $title, bool $process = true): void
    {
        $this->actingAs($this->owner)
            ->post($this->url('/documents'), [
                'title' => $title,
                'category_id' => $this->category->getKey(),
                'file' => $file,
            ])
            ->assertRedirect();
        if ($process) {
            $version = DocumentVersion::query()->latest('id')->firstOrFail();
            app(DocumentProcessor::class)->process((int) $this->institution->getKey(), (int) $version->getKey());
        }
    }

    private function minimalXlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'varez-xlsx-');
        if ($path === false) {
            $this->fail('Could not create a temporary XLSX workbook.');
        }
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Quality metrics');
        $sheet->fromArray([['Metric', 'Value'], ['Reviewed evidence', 'Approved']], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $contents = file_get_contents($path);
        unlink($path);
        $this->assertIsString($contents);

        return $contents;
    }

    private function minimalPdf(): string
    {
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];
        $stream = 'BT /F1 12 Tf 72 720 Td (This PDF contains enough evidence text for document processing.) Tj ET';
        $objects[] = '5 0 obj << /Length '.strlen($stream)." >> stream\n{$stream}\nendstream endobj";
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function expectUploadFailure(UploadedFile $file): void
    {
        try {
            app(DocumentFileInspector::class)->inspect($file);
            $this->fail('Expected malicious or forged file validation to fail.');
        } catch (ValidationException) {
            $this->assertSame(0, Document::query()->count());
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function createDocumentReaderRole(array $permissions = ['documents.view', 'documents.download']): Role
    {
        $role = Role::query()->create([
            'scope' => 'tenant',
            'name' => 'Document reader',
            'slug' => 'test.document_reader.'.strtolower(str()->random(6)),
        ]);
        $role->permissions()->sync(
            Permission::query()->where('scope', 'tenant')->whereIn('slug', $permissions)->pluck('id'),
        );

        return $role;
    }

    private function createInstitution(string $slug): Institution
    {
        $institution = Institution::query()->create([
            'name' => 'Institution '.$slug,
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

    private function url(string $path): string
    {
        return "/institutions/{$this->institution->slug}{$path}";
    }
}
