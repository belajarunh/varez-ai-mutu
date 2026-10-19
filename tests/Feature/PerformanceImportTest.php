<?php

namespace Tests\Feature;

use App\Jobs\InspectPerformanceImport;
use App\Jobs\ProcessPerformanceImport;
use App\Jobs\ValidatePerformanceImport;
use App\Models\AcademicPeriod;
use App\Models\ImportBatch;
use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\PerformanceDatasetSchema;
use App\Models\PerformanceRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\Performance\PerformanceImportFileReader;
use App\Services\Performance\PerformanceImportProcessor;
use App\Services\Performance\PerformanceImportValidator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PerformanceImportTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private Institution $otherInstitution;

    private User $owner;

    private User $otherOwner;

    private AcademicPeriod $period;

    private PerformanceDatasetSchema $schema;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->seed(RolePermissionSeeder::class);

        $ownerRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->institution = $this->createInstitution('import-tenant-a');
        $this->otherInstitution = $this->createInstitution('import-tenant-b');
        $this->owner = User::factory()->create();
        $this->otherOwner = User::factory()->create();
        $this->attachMembership($this->institution, $this->owner, $ownerRole);
        $this->attachMembership($this->otherInstitution, $this->otherOwner, $ownerRole);
        $this->period = $this->createPeriod($this->institution, '2026');
        $this->schema = PerformanceDatasetSchema::query()->create([
            'dataset_type' => 'general_metrics',
            'schema_version' => 1,
            'name' => 'Generic data schema',
            'fields_json' => [
                ['key' => 'record_key', 'label' => 'Record key', 'type' => 'string', 'required' => true, 'unique' => true],
                ['key' => 'value', 'label' => 'Value', 'type' => 'decimal', 'required' => true, 'unique' => false],
            ],
            'status' => PerformanceDatasetSchema::STATUS_PUBLISHED,
        ]);
    }

    public function test_csv_upload_is_private_validated_asynchronously_and_committed_idempotently(): void
    {
        $batch = $this->uploadAndInspect($this->owner, $this->institution, $this->period, $this->schema, 'record_key,value'."\n".'A1,10.5'."\n".'A2,2');
        $this->assertSame(ImportBatch::STATUS_AWAITING_MAPPING, $batch->status);
        $this->assertTrue(Storage::disk('local')->exists($batch->storage_key));
        $this->assertCount(2, $batch->headers_json);
        Queue::assertPushed(InspectPerformanceImport::class);

        $this->postMappingAndValidate($batch, $this->owner, [
            'record_key' => 'record_key',
            'value' => 'value',
        ]);
        $batch->refresh();
        $this->assertSame(ImportBatch::STATUS_PREVIEW_READY, $batch->status);
        $this->assertSame(2, $batch->valid_count);
        $this->assertCount(2, $batch->preview_json);
        $this->assertSame('10.5', $batch->preview_json[0]['value']);

        $this->actingAs($this->owner)
            ->post("/institutions/{$this->institution->slug}/performance/imports/{$batch->getKey()}/confirm", [
                'confirmation' => '1',
            ])
            ->assertRedirect();
        Queue::assertPushed(ProcessPerformanceImport::class);

        $processor = app(PerformanceImportProcessor::class);
        $processor->process((int) $this->institution->getKey(), (int) $batch->getKey());
        $processor->process((int) $this->institution->getKey(), (int) $batch->getKey());

        $batch->refresh();
        $this->assertSame(ImportBatch::STATUS_COMPLETED, $batch->status);
        $this->assertDatabaseCount('performance_datasets', 1);
        $this->assertDatabaseCount('performance_records', 2);
        $this->assertSame(2, PerformanceRecord::query()->where('institution_id', $this->institution->getKey())->count());
        $this->assertDatabaseHas('audit_logs', [
            'institution_id' => $this->institution->getKey(),
            'action' => 'performance_dataset.imported',
        ]);
    }

    public function test_xlsx_upload_is_supported_and_broken_spreadsheet_is_rejected(): void
    {
        $xlsxContents = $this->makeXlsx(['record_key', 'value'], ['XLSX-1', 4.25]);
        $batch = $this->uploadFileAndInspect(
            $this->owner,
            $this->institution,
            $this->period,
            $this->schema,
            UploadedFile::fake()->createWithContent('metrics.xlsx', $xlsxContents),
        );
        $this->assertSame(ImportBatch::STATUS_AWAITING_MAPPING, $batch->status);

        $invalid = $this->uploadFileAndInspect(
            $this->owner,
            $this->institution,
            $this->period,
            $this->schema,
            UploadedFile::fake()->createWithContent('invalid.xlsx', 'not a zip file'),
        );
        $this->assertSame(ImportBatch::STATUS_FAILED, $invalid->status);
        $this->assertSame(1, $invalid->fatal_error_count);
        $this->assertDatabaseHas('import_errors', [
            'import_batch_id' => $invalid->getKey(),
            'is_fatal' => true,
        ]);
    }

    public function test_duplicate_rows_are_reported_and_only_explicitly_accepted_valid_rows_import(): void
    {
        $batch = $this->uploadAndInspect(
            $this->owner,
            $this->institution,
            $this->period,
            $this->schema,
            "record_key,value\nDUP,1\nDUP,2\nUNIQUE,3",
        );
        $this->postMappingAndValidate($batch, $this->owner, [
            'record_key' => 'record_key',
            'value' => 'value',
        ]);
        $batch->refresh();
        $this->assertSame(ImportBatch::STATUS_PREVIEW_WITH_ERRORS, $batch->status);
        $this->assertSame(2, $batch->valid_count);
        $this->assertSame(1, $batch->error_count);
        $this->assertDatabaseHas('import_errors', [
            'import_batch_id' => $batch->getKey(),
            'error_code' => 'duplicate_record_key',
            'row_number' => 3,
        ]);

        $url = "/institutions/{$this->institution->slug}/performance/imports/{$batch->getKey()}/confirm";
        $this->actingAs($this->owner)
            ->post($url, ['confirmation' => '1'])
            ->assertSessionHasErrors('partial_confirmation');
        $this->actingAs($this->owner)
            ->post($url, ['confirmation' => '1', 'partial_confirmation' => '1'])
            ->assertRedirect();

        app(PerformanceImportProcessor::class)->process((int) $this->institution->getKey(), (int) $batch->getKey());
        $this->assertDatabaseCount('performance_records', 2);
    }

    public function test_replace_requires_explicit_target_confirmation_and_replaces_atomically(): void
    {
        $first = $this->uploadAndInspect(
            $this->owner,
            $this->institution,
            $this->period,
            $this->schema,
            "record_key,value\nOLD,1",
        );
        $this->postMappingAndValidate($first, $this->owner, ['record_key' => 'record_key', 'value' => 'value']);
        $this->confirmBatch($first, $this->owner);
        app(PerformanceImportProcessor::class)->process((int) $this->institution->getKey(), (int) $first->getKey());
        $datasetId = (int) $first->fresh()->target_dataset_id;

        $replace = $this->uploadAndInspect(
            $this->owner,
            $this->institution,
            $this->period,
            $this->schema,
            "record_key,value\nNEW,9",
        );
        $this->actingAs($this->owner)
            ->post("/institutions/{$this->institution->slug}/performance/imports/{$replace->getKey()}/validate", [
                'mapping' => ['record_key' => 'record_key', 'value' => 'value'],
                'strategy' => 'replace',
                'target_dataset_id' => $datasetId,
            ])
            ->assertSessionHasErrors('replace_confirmation');
        $this->actingAs($this->owner)
            ->post("/institutions/{$this->institution->slug}/performance/imports/{$replace->getKey()}/validate", [
                'mapping' => ['record_key' => 'record_key', 'value' => 'value'],
                'strategy' => 'replace',
                'target_dataset_id' => $datasetId,
                'replace_confirmation' => '1',
            ])
            ->assertRedirect();
        app(ValidatePerformanceImport::class, [
            'institutionId' => (int) $this->institution->getKey(),
            'batchId' => (int) $replace->getKey(),
        ])->handle(app(PerformanceImportValidator::class));
        $replace->refresh();
        $this->assertSame(ImportBatch::STATUS_PREVIEW_READY, $replace->status);

        $this->actingAs($this->owner)
            ->post("/institutions/{$this->institution->slug}/performance/imports/{$replace->getKey()}/confirm", [
                'confirmation' => '1',
            ])
            ->assertSessionHasErrors('replace_confirmation');
        $this->actingAs($this->owner)
            ->post("/institutions/{$this->institution->slug}/performance/imports/{$replace->getKey()}/confirm", [
                'confirmation' => '1',
                'replace_confirmation' => '1',
            ])
            ->assertRedirect();

        app(PerformanceImportProcessor::class)->process((int) $this->institution->getKey(), (int) $replace->getKey());
        $this->assertDatabaseCount('performance_records', 1);
        $this->assertDatabaseHas('performance_records', [
            'institution_id' => $this->institution->getKey(),
            'dataset_id' => $datasetId,
            'record_key' => 'NEW',
        ]);
        $this->assertDatabaseMissing('performance_records', ['dataset_id' => $datasetId, 'record_key' => 'OLD']);
    }

    public function test_tenant_cannot_view_or_download_another_tenants_import(): void
    {
        $otherPeriod = $this->createPeriod($this->otherInstitution, '2026');
        $batch = $this->uploadAndInspect($this->otherOwner, $this->otherInstitution, $otherPeriod, $this->schema, "record_key,value\nB-1,1");
        $this->assertSame(ImportBatch::STATUS_AWAITING_MAPPING, $batch->status);

        $this->actingAs($this->owner)
            ->get("/institutions/{$this->institution->slug}/performance/imports/{$batch->getKey()}")
            ->assertNotFound();
        $this->actingAs($this->owner)
            ->get("/institutions/{$this->institution->slug}/performance/imports/{$batch->getKey()}/errors.csv")
            ->assertNotFound();
        $this->actingAs($this->owner)
            ->get("/institutions/{$this->otherInstitution->slug}/performance")
            ->assertNotFound();
    }

    public function test_import_without_permission_is_denied_and_oversized_upload_is_rejected(): void
    {
        $member = User::factory()->create();
        $memberRole = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.member')->firstOrFail();
        $this->attachMembership($this->institution, $member, $memberRole);
        $this->actingAs($member)
            ->get("/institutions/{$this->institution->slug}/performance")
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post("/institutions/{$this->institution->slug}/performance/imports", [
                'file' => UploadedFile::fake()->create('too-large.csv', 10_241, 'text/csv'),
                'performance_dataset_schema_id' => $this->schema->getKey(),
                'academic_period_id' => $this->period->getKey(),
            ])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_published_schema_cannot_be_modified_and_template_files_are_available(): void
    {
        try {
            $this->schema->update(['name' => 'Tampered schema']);
            $this->fail('A published dataset schema must be immutable.');
        } catch (LogicException) {
            $this->assertSame('Generic data schema', $this->schema->fresh()->name);
        }

        $this->actingAs($this->owner)
            ->get("/institutions/{$this->institution->slug}/performance/schemas/{$this->schema->getKey()}/template/csv")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function uploadAndInspect(
        User $user,
        Institution $institution,
        AcademicPeriod $period,
        PerformanceDatasetSchema $schema,
        string $contents,
    ): ImportBatch {
        return $this->uploadFileAndInspect(
            $user,
            $institution,
            $period,
            $schema,
            UploadedFile::fake()->createWithContent('metrics.csv', $contents),
        );
    }

    private function uploadFileAndInspect(
        User $user,
        Institution $institution,
        AcademicPeriod $period,
        PerformanceDatasetSchema $schema,
        UploadedFile $file,
    ): ImportBatch {
        $this->actingAs($user)
            ->post("/institutions/{$institution->slug}/performance/imports", [
                'file' => $file,
                'performance_dataset_schema_id' => $schema->getKey(),
                'academic_period_id' => $period->getKey(),
            ])
            ->assertRedirect();

        $batch = ImportBatch::query()->where('institution_id', $institution->getKey())->latest('id')->firstOrFail();
        app(InspectPerformanceImport::class, [
            'institutionId' => (int) $institution->getKey(),
            'batchId' => (int) $batch->getKey(),
        ])->handle(app(PerformanceImportFileReader::class));

        return $batch->fresh();
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function postMappingAndValidate(ImportBatch $batch, User $user, array $mapping): void
    {
        $institution = Institution::query()->findOrFail($batch->institution_id);
        $this->actingAs($user)
            ->post("/institutions/{$institution->slug}/performance/imports/{$batch->getKey()}/validate", [
                'mapping' => $mapping,
                'strategy' => 'append',
            ])
            ->assertRedirect();
        app(ValidatePerformanceImport::class, [
            'institutionId' => (int) $institution->getKey(),
            'batchId' => (int) $batch->getKey(),
        ])->handle(app(PerformanceImportValidator::class));
    }

    private function confirmBatch(ImportBatch $batch, User $user): void
    {
        $institution = Institution::query()->findOrFail($batch->institution_id);
        $this->actingAs($user)
            ->post("/institutions/{$institution->slug}/performance/imports/{$batch->getKey()}/confirm", [
                'confirmation' => '1',
            ])
            ->assertRedirect();
    }

    /**
     * @param  list<string|int|float>  $headers
     * @param  list<string|int|float>  $values
     */
    private function makeXlsx(array $headers, array $values): string
    {
        $path = tempnam(sys_get_temp_dir(), 'varez-import-');
        if ($path === false) {
            $this->fail('Unable to allocate temporary XLSX test file.');
        }
        try {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            foreach ($headers as $index => $header) {
                $sheet->setCellValue([$index + 1, 1], $header);
                $sheet->setCellValue([$index + 1, 2], $values[$index]);
            }
            (new Xlsx($spreadsheet))->save($path);
            $spreadsheet->disconnectWorksheets();

            return file_get_contents($path) ?: '';
        } finally {
            unlink($path);
        }
    }

    private function createInstitution(string $slug): Institution
    {
        $institution = Institution::query()->create([
            'name' => "Institution {$slug}",
            'slug' => $slug,
            'institution_type' => 'higher_education',
            'timezone' => 'Asia/Jakarta',
        ]);
        $institution->forceFill(['status' => Institution::STATUS_ACTIVE])->save();

        return $institution;
    }

    private function createPeriod(Institution $institution, string $code): AcademicPeriod
    {
        return AcademicPeriod::query()->create([
            'institution_id' => $institution->getKey(),
            'code' => $code,
            'name' => "Academic period {$code} {$institution->slug}",
            'starts_on' => "{$code}-01-01",
            'ends_on' => "{$code}-12-31",
            'status' => 'active',
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
}
