<?php

namespace App\Http\Controllers;

use App\Jobs\InspectPerformanceImport;
use App\Jobs\ProcessPerformanceImport;
use App\Jobs\ValidatePerformanceImport;
use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\Institution;
use App\Models\OrganizationalUnit;
use App\Models\PerformanceDataset;
use App\Models\PerformanceDatasetSchema;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PerformanceImportController extends Controller
{
    public function index(string $institution, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        $this->authorizePermission(request(), $tenant, 'performance_data.view');

        $batches = ImportBatch::query()
            ->where('institution_id', $tenant->getKey())
            ->with(['schema', 'period', 'uploader'])
            ->latest()
            ->paginate(15);
        $datasets = PerformanceDataset::query()
            ->where('institution_id', $tenant->getKey())
            ->with(['schema', 'period', 'unit'])
            ->withCount('records')
            ->latest()
            ->paginate(15, ['*'], 'datasets_page');

        return view('performance.index', compact('tenant', 'batches', 'datasets'));
    }

    public function create(string $institution, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        $this->authorizePermission(request(), $tenant, 'performance_data.import');

        $schemas = PerformanceDatasetSchema::query()
            ->where('status', PerformanceDatasetSchema::STATUS_PUBLISHED)
            ->orderBy('dataset_type')
            ->orderByDesc('schema_version')
            ->get();
        $periods = AcademicPeriod::query()
            ->where('institution_id', $tenant->getKey())
            ->where('status', 'active')
            ->orderByDesc('starts_on')
            ->get();
        $units = $this->accessibleUnits(request(), $tenant);

        return view('performance.create', compact('tenant', 'schemas', 'periods', 'units'));
    }

    public function createPeriod(
        Request $request,
        string $institution,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $this->authorizePermission($request, $tenant, 'performance_data.import');

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:80',
                Rule::unique('academic_periods', 'code')->where('institution_id', $tenant->getKey()),
            ],
            'name' => ['required', 'string', 'max:160'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $period = DB::transaction(fn (): AcademicPeriod => AcademicPeriod::query()->create([
            'institution_id' => $tenant->getKey(),
            ...$data,
            'status' => 'active',
        ]));

        AuditLog::query()->create([
            'institution_id' => $tenant->getKey(),
            'actor_user_id' => $request->user()->getKey(),
            'action' => 'academic_period.created',
            'subject_type' => AcademicPeriod::class,
            'subject_id' => (string) $period->getKey(),
            'after_json' => [
                'code' => $period->code,
                'name' => $period->name,
                'starts_on' => $period->starts_on?->toDateString(),
                'ends_on' => $period->ends_on?->toDateString(),
            ],
            'correlation_id' => (string) Str::uuid(),
        ]);

        return redirect()
            ->route('institutions.performance.imports.create', $tenant->slug)
            ->with('status', 'Periode akademik berhasil ditambahkan.');
    }

    public function store(
        Request $request,
        string $institution,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $this->authorizePermission($request, $tenant, 'performance_data.import');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'performance_dataset_schema_id' => ['required', 'integer'],
            'academic_period_id' => ['required', 'integer'],
            'unit_id' => ['nullable', 'integer'],
        ]);

        $schema = PerformanceDatasetSchema::query()
            ->whereKey($data['performance_dataset_schema_id'])
            ->where('status', PerformanceDatasetSchema::STATUS_PUBLISHED)
            ->firstOrFail();
        $period = AcademicPeriod::query()
            ->where('institution_id', $tenant->getKey())
            ->whereKey($data['academic_period_id'])
            ->where('status', 'active')
            ->firstOrFail();
        $unitId = isset($data['unit_id']) ? (int) $data['unit_id'] : null;
        if ($unitId !== null && ! $this->accessibleUnits($request, $tenant)->contains('id', $unitId)) {
            abort(404);
        }

        $upload = $request->file('file');
        $extension = strtolower($upload->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            return back()->withErrors(['file' => 'Hanya file CSV UTF-8 atau XLSX yang didukung.'])->withInput();
        }
        $sourceType = $extension;
        $storedName = (string) Str::uuid().'.'.$extension;
        $storageKey = $upload->storeAs("performance-imports/{$tenant->getKey()}", $storedName, 'local');
        if (! is_string($storageKey)) {
            throw new \RuntimeException('File impor gagal disimpan pada private storage.');
        }

        try {
            $path = Storage::disk('local')->path($storageKey);
            $checksum = hash_file('sha256', $path);
            if (! is_string($checksum)) {
                throw new \RuntimeException('Checksum file impor tidak dapat dibuat.');
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storageKey);
            throw $exception;
        }

        $filename = str_replace('\\', '/', $upload->getClientOriginalName());
        $filename = basename($filename);
        $filename = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? 'upload.'.$extension, 0, 255);
        $batch = ImportBatch::query()->create([
            'institution_id' => $tenant->getKey(),
            'uploaded_by' => $request->user()->getKey(),
            'performance_dataset_schema_id' => $schema->getKey(),
            'academic_period_id' => $period->getKey(),
            'unit_id' => $unitId,
            'source_type' => $sourceType,
            'original_filename' => $filename,
            'storage_key' => $storageKey,
            'checksum_sha256' => $checksum,
            'status' => ImportBatch::STATUS_INSPECTING,
        ]);

        AuditLog::query()->create([
            'institution_id' => $tenant->getKey(),
            'actor_user_id' => $request->user()->getKey(),
            'action' => 'performance_import.uploaded',
            'subject_type' => ImportBatch::class,
            'subject_id' => (string) $batch->getKey(),
            'after_json' => [
                'filename' => $filename,
                'checksum_sha256' => $checksum,
                'schema_type' => $schema->dataset_type,
                'schema_version' => $schema->schema_version,
            ],
            'correlation_id' => (string) Str::uuid(),
        ]);

        InspectPerformanceImport::dispatch((int) $tenant->getKey(), (int) $batch->getKey())->afterCommit();

        return redirect()
            ->route('institutions.performance.imports.show', [$tenant->slug, $batch->getKey()])
            ->with('status', 'File disimpan privat dan pemeriksaan awal berjalan di queue.');
    }

    public function mapping(
        string $institution,
        int $batch,
        TenantContext $tenantContext,
    ): View {
        $tenant = $tenantContext->institution();
        $this->authorizePermission(request(), $tenant, 'performance_data.import');
        $record = $this->batchQuery($tenant)->with(['schema', 'period', 'unit'])->findOrFail($batch);
        abort_unless($record->status === ImportBatch::STATUS_AWAITING_MAPPING, 404);

        $headers = $record->headers_json ?? [];
        $datasets = PerformanceDataset::query()
            ->where('institution_id', $tenant->getKey())
            ->where('academic_period_id', $record->academic_period_id)
            ->where('unit_id', $record->unit_id)
            ->where('performance_dataset_schema_id', $record->performance_dataset_schema_id)
            ->where('status', 'active')
            ->withCount('records')
            ->orderByDesc('created_at')
            ->get();

        return view('performance.mapping', compact('tenant', 'record', 'headers', 'datasets'));
    }

    public function startValidation(
        Request $request,
        string $institution,
        int $batch,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $this->authorizePermission($request, $tenant, 'performance_data.import');
        $record = $this->batchQuery($tenant)->with('schema')->findOrFail($batch);
        abort_unless($record->status === ImportBatch::STATUS_AWAITING_MAPPING, 404);

        $data = $request->validate([
            'mapping' => ['required', 'array', 'max:100'],
            'mapping.*' => ['nullable', 'string', 'max:150'],
            'strategy' => ['required', Rule::in([ImportBatch::STRATEGY_APPEND, ImportBatch::STRATEGY_REPLACE])],
            'target_dataset_id' => ['nullable', 'integer'],
        ]);
        $headers = $record->headers_json ?? [];
        $fieldMap = collect($record->schema->fields_json)->keyBy('key');
        $unknownFields = array_diff(array_keys($data['mapping']), $fieldMap->keys()->all());
        if ($unknownFields !== []) {
            return back()->withErrors(['mapping' => 'Pemetaan memuat field yang tidak ada pada schema.'])->withInput();
        }
        foreach ($fieldMap as $key => $field) {
            $mappedHeader = $data['mapping'][$key] ?? null;
            if (($field['required'] ?? false) && ($mappedHeader === null || $mappedHeader === '')) {
                return back()->withErrors(["mapping.{$key}" => "Kolom untuk field {$field['label']} wajib dipetakan."])->withInput();
            }
            if ($mappedHeader !== null && ! in_array($mappedHeader, $headers, true)) {
                return back()->withErrors(["mapping.{$key}" => 'Kolom pilihan tidak ada pada file.'])->withInput();
            }
        }
        $mappedHeaders = array_values(array_filter($data['mapping'], fn ($value): bool => is_string($value) && $value !== ''));
        if (count($mappedHeaders) !== count(array_unique($mappedHeaders))) {
            return back()->withErrors(['mapping' => 'Setiap kolom file hanya dapat dipetakan sekali.'])->withInput();
        }

        $targetDatasetId = isset($data['target_dataset_id']) ? (int) $data['target_dataset_id'] : null;
        if ($data['strategy'] === ImportBatch::STRATEGY_REPLACE && $targetDatasetId === null) {
            return back()->withErrors(['target_dataset_id' => 'Pilih dataset tujuan untuk strategi replace.'])->withInput();
        }
        if ($targetDatasetId !== null) {
            $target = PerformanceDataset::query()
                ->where('institution_id', $tenant->getKey())
                ->whereKey($targetDatasetId)
                ->where('academic_period_id', $record->academic_period_id)
                ->where('unit_id', $record->unit_id)
                ->where('performance_dataset_schema_id', $record->performance_dataset_schema_id)
                ->where('status', 'active')
                ->firstOrFail();
            if ($data['strategy'] === ImportBatch::STRATEGY_REPLACE && ! $request->boolean('replace_confirmation')) {
                return back()->withErrors(['replace_confirmation' => 'Konfirmasi bahwa seluruh records dataset tujuan akan diganti.'])->withInput();
            }
        }

        $record->forceFill([
            'mapping_json' => $data['mapping'],
            'strategy' => $data['strategy'],
            'target_dataset_id' => $targetDatasetId,
            'status' => ImportBatch::STATUS_VALIDATING,
            'finished_at' => null,
        ])->save();
        ValidatePerformanceImport::dispatch((int) $tenant->getKey(), (int) $record->getKey())->afterCommit();

        return redirect()
            ->route('institutions.performance.imports.show', [$tenant->slug, $record->getKey()])
            ->with('status', 'Validasi berjalan di antrean. Muat ulang halaman untuk melihat hasil pratinjau.');
    }

    public function show(string $institution, int $batch, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        $this->authorizePermission(request(), $tenant, 'performance_data.view');
        $record = $this->batchQuery($tenant)
            ->with(['schema', 'period', 'unit', 'uploader', 'confirmer'])
            ->findOrFail($batch);
        $importErrors = $record->errors()->orderBy('row_number')->orderBy('id')->paginate(100);

        return view('performance.show', compact('tenant', 'record', 'importErrors'));
    }

    public function confirm(
        Request $request,
        string $institution,
        int $batch,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $this->authorizePermission($request, $tenant, 'performance_data.import');
        $record = $this->batchQuery($tenant)->findOrFail($batch);
        abort_unless(
            in_array($record->status, [ImportBatch::STATUS_PREVIEW_READY, ImportBatch::STATUS_PREVIEW_WITH_ERRORS], true)
            && $record->fatal_error_count === 0
            && $record->valid_count > 0,
            404,
        );
        $request->validate([
            'confirmation' => ['accepted'],
            'partial_confirmation' => $record->error_count > 0 ? ['required', 'accepted'] : ['nullable'],
            'replace_confirmation' => $record->strategy === ImportBatch::STRATEGY_REPLACE
                ? ['required', 'accepted']
                : ['nullable'],
        ]);

        DB::transaction(function () use ($tenant, $record, $request): void {
            $lockedBatch = $this->batchQuery($tenant)
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                in_array(
                    $lockedBatch->status,
                    [ImportBatch::STATUS_PREVIEW_READY, ImportBatch::STATUS_PREVIEW_WITH_ERRORS],
                    true,
                ),
                404,
            );
            $lockedBatch->forceFill([
                'status' => ImportBatch::STATUS_QUEUED,
                'confirmed_by' => $request->user()->getKey(),
                'confirmed_at' => now(),
            ])->save();
            ProcessPerformanceImport::dispatch((int) $tenant->getKey(), (int) $lockedBatch->getKey())->afterCommit();
        });

        return redirect()
            ->route('institutions.performance.imports.show', [$tenant->slug, $record->getKey()])
            ->with('status', 'Impor masuk antrean. Worker queue akan memprosesnya secara asinkron.');
    }

    public function errorReport(string $institution, int $batch, TenantContext $tenantContext): StreamedResponse
    {
        $tenant = $tenantContext->institution();
        $this->authorizePermission(request(), $tenant, 'performance_data.view');
        $record = $this->batchQuery($tenant)->findOrFail($batch);

        return response()->streamDownload(function () use ($record): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('Error report stream could not be opened.');
            }
            fputcsv($output, ['row_number', 'column_name', 'error_code', 'message', 'row_payload_json']);
            $record->errors()->orderBy('row_number')->orderBy('id')->chunk(500, function ($errors) use ($output): void {
                foreach ($errors as $error) {
                    fputcsv($output, [
                        $error->row_number,
                        $error->column_name,
                        $error->error_code,
                        $error->message,
                        json_encode($error->row_payload_json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ]);
                }
            });
            fclose($output);
        }, "import-errors-{$record->getKey()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(
        string $institution,
        int $schema,
        string $format,
        TenantContext $tenantContext,
    ): Response|StreamedResponse {
        $tenant = $tenantContext->institution();
        $this->authorizePermission(request(), $tenant, 'performance_data.view');
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);
        $record = PerformanceDatasetSchema::query()
            ->whereKey($schema)
            ->where('status', PerformanceDatasetSchema::STATUS_PUBLISHED)
            ->firstOrFail();
        $headers = collect($record->fields_json)->pluck('key')->all();
        $filename = Str::slug($record->dataset_type).'-v'.$record->schema_version.'-template.'.$format;

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers): void {
                $output = fopen('php://output', 'wb');
                if ($output === false) {
                    throw new \RuntimeException('Template stream could not be opened.');
                }
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $headers);
                fclose($output);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($headers): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            foreach (array_values($headers) as $index => $header) {
                $sheet->setCellValue([$index + 1, 1], $header);
            }
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @return Builder<ImportBatch>
     */
    private function batchQuery(Institution $tenant): Builder
    {
        return ImportBatch::query()->where('institution_id', $tenant->getKey());
    }

    /**
     * @return Collection<int, OrganizationalUnit>
     */
    private function accessibleUnits(Request $request, Institution $tenant): Collection
    {
        return $tenant->organizationalUnits()
            ->where('status', OrganizationalUnit::STATUS_ACTIVE)
            ->get()
            ->filter(fn (OrganizationalUnit $unit): bool => $request->user()->canAccessUnit($tenant, $unit))
            ->values();
    }

    private function authorizePermission(Request $request, Institution $tenant, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, $tenant), 403);
    }
}
