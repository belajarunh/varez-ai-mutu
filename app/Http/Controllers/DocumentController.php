<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentVersion;
use App\Models\AcademicPeriod;
use App\Models\AccreditationProject;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRelation;
use App\Models\DocumentVersion;
use App\Models\Institution;
use App\Models\OrganizationalUnit;
use App\Models\ProjectIndicator;
use App\Models\User;
use App\Services\Documents\DocumentFileInspector;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentController extends Controller
{
    public function index(string $institution, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission(request(), $tenant, 'documents.view');
        $documents = $this->visibleDocuments($tenant, $user)
            ->with(['category', 'unit', 'period', 'owner', 'currentVersion'])
            ->latest('updated_at')
            ->paginate(15);

        return view('documents.index', compact('tenant', 'documents'));
    }

    public function create(string $institution, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission(request(), $tenant, 'documents.upload');
        $categories = DocumentCategory::query()
            ->where('institution_id', $tenant->getKey())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $units = $this->accessibleUnits($tenant, $user);
        $periods = AcademicPeriod::query()
            ->where('institution_id', $tenant->getKey())
            ->orderByDesc('starts_on')
            ->get();

        return view('documents.create', compact('tenant', 'categories', 'units', 'periods'));
    }

    public function store(
        Request $request,
        string $institution,
        TenantContext $tenantContext,
        DocumentFileInspector $inspector,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'documents.upload');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:25600'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'academic_period_id' => ['nullable', 'integer'],
            'retention_until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $file = $request->file('file');
        $fileInfo = $inspector->inspect($file);
        $checksum = $this->uploadedChecksum($file);
        $category = $this->categoryQuery($tenant)->whereKey($data['category_id'])->where('is_active', true)->firstOrFail();
        $unit = $this->validatedUnit($tenant, $user, $data['unit_id'] ?? null);
        $period = $this->validatedPeriod($tenant, $data['academic_period_id'] ?? null);
        $accessLevel = $category->access_default;
        if ($accessLevel === Document::ACCESS_UNIT && $unit === null) {
            return back()->withErrors(['unit_id' => 'Kategori ini memerlukan unit pemilik.'])->withInput();
        }

        $disk = $this->privateDisk();
        $storageKey = 'documents/'.$tenant->getKey().'/'.Str::uuid().'.'.$fileInfo['extension'];
        $storedKey = Storage::disk($disk)->putFileAs(
            'documents/'.$tenant->getKey(),
            $file,
            basename($storageKey),
            ['visibility' => 'private'],
        );
        if (! is_string($storedKey)) {
            throw new RuntimeException('Dokumen gagal disimpan pada private storage.');
        }

        try {
            $filename = $this->safeFilename($file->getClientOriginalName(), $fileInfo['extension']);
            $document = DB::transaction(function () use (
                $tenant,
                $user,
                $data,
                $category,
                $unit,
                $period,
                $fileInfo,
                $disk,
                $storedKey,
                $filename,
                $accessLevel,
                $checksum,
            ): Document {
                $document = Document::query()->create([
                    'institution_id' => $tenant->getKey(),
                    'unit_id' => $unit?->getKey(),
                    'category_id' => $category->getKey(),
                    'academic_period_id' => $period?->getKey(),
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'document_type' => $fileInfo['extension'],
                    'owner_user_id' => $user->getKey(),
                    'access_level' => $accessLevel,
                    'verification_status' => Document::VERIFICATION_UNVERIFIED,
                    'status' => Document::STATUS_ACTIVE,
                    'created_by' => $user->getKey(),
                ]);
                $version = $document->versions()->create([
                    'institution_id' => $tenant->getKey(),
                    'version_number' => 1,
                    'storage_disk' => $disk,
                    'storage_key' => $storedKey,
                    'original_filename' => $filename,
                    'file_extension' => $fileInfo['extension'],
                    'mime_type' => $fileInfo['mime_type'],
                    'size_bytes' => $fileInfo['size_bytes'],
                    'checksum_sha256' => $checksum,
                    'uploaded_by' => $user->getKey(),
                    'uploaded_at' => now(),
                    'extraction_status' => DocumentVersion::STATUS_QUEUED,
                    'scan_status' => 'pending',
                    'metadata_json' => [],
                ]);
                $document->forceFill(['current_version_id' => $version->getKey()])->save();
                app(AuditLogger::class)->record(
                    'document.uploaded',
                    $document,
                    $tenant,
                    after: $this->auditSnapshot($document, $version->version_number, $version->checksum_sha256),
                );
                DB::afterCommit(fn () => ProcessDocumentVersion::dispatch((int) $tenant->getKey(), (int) $version->getKey()));

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storedKey);
            throw $exception;
        }

        return redirect()
            ->route('institutions.documents.show', [$tenant->slug, $document->getKey()])
            ->with('status', 'Dokumen tersimpan privat. Ekstraksi berjalan di antrean.');
    }

    public function show(
        string $institution,
        int $document,
        TenantContext $tenantContext,
    ): View {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission(request(), $tenant, 'documents.view');
        $record = $this->visibleDocuments($tenant, $user)
            ->with(['category', 'unit', 'period', 'owner', 'currentVersion', 'versions.uploader', 'relations'])
            ->whereKey($document)
            ->firstOrFail();
        $canManage = $user->hasPermission('documents.manage', $tenant);
        $canVerify = $user->hasPermission('documents.verify', $tenant);
        $categories = $canManage
            ? $this->categoryQuery($tenant)->where('is_active', true)->orderBy('name')->get()
            : collect();
        $units = $this->accessibleUnits($tenant, $user);
        $periods = $canManage
            ? AcademicPeriod::query()->where('institution_id', $tenant->getKey())->orderByDesc('starts_on')->get()
            : collect();
        $projectOptions = $canManage
            ? AccreditationProject::query()
                ->where('institution_id', $tenant->getKey())
                ->with('indicators:id,project_id,snapshot_node_json')
                ->latest()
                ->limit(100)
                ->get()
            : collect();

        return view('documents.show', compact(
            'tenant',
            'record',
            'user',
            'canManage',
            'canVerify',
            'categories',
            'units',
            'periods',
            'projectOptions',
        ));
    }

    public function storeVersion(
        Request $request,
        string $institution,
        int $document,
        TenantContext $tenantContext,
        DocumentFileInspector $inspector,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'documents.upload');
        $record = $this->documentQuery($tenant)->whereKey($document)->firstOrFail();
        $this->authorizeVisible($record, $tenant, $user);
        abort_unless($record->status === Document::STATUS_ACTIVE, 404);
        $data = $request->validate(['file' => ['required', 'file', 'max:25600']]);
        $file = $request->file('file');
        $fileInfo = $inspector->inspect($file);
        $checksum = $this->uploadedChecksum($file);
        $disk = $this->privateDisk();
        $storedKey = Storage::disk($disk)->putFileAs(
            'documents/'.$tenant->getKey(),
            $file,
            (string) Str::uuid().'.'.$fileInfo['extension'],
            ['visibility' => 'private'],
        );
        if (! is_string($storedKey)) {
            throw new RuntimeException('Versi dokumen gagal disimpan pada private storage.');
        }

        try {
            $filename = $this->safeFilename($file->getClientOriginalName(), $fileInfo['extension']);
            $version = DB::transaction(function () use (
                $record,
                $tenant,
                $user,
                $disk,
                $storedKey,
                $filename,
                $fileInfo,
                $checksum,
            ): DocumentVersion {
                $lockedDocument = $this->documentQuery($tenant)->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
                $versionNumber = (int) $lockedDocument->versions()->max('version_number') + 1;
                $version = $lockedDocument->versions()->create([
                    'institution_id' => $tenant->getKey(),
                    'version_number' => $versionNumber,
                    'storage_disk' => $disk,
                    'storage_key' => $storedKey,
                    'original_filename' => $filename,
                    'file_extension' => $fileInfo['extension'],
                    'mime_type' => $fileInfo['mime_type'],
                    'size_bytes' => $fileInfo['size_bytes'],
                    'checksum_sha256' => $checksum,
                    'uploaded_by' => $user->getKey(),
                    'uploaded_at' => now(),
                    'extraction_status' => DocumentVersion::STATUS_QUEUED,
                    'scan_status' => 'pending',
                    'metadata_json' => [],
                ]);
                $lockedDocument->forceFill([
                    'current_version_id' => $version->getKey(),
                    'document_type' => $fileInfo['extension'],
                    'verification_status' => Document::VERIFICATION_UNVERIFIED,
                ])->save();
                app(AuditLogger::class)->record(
                    'document.version_uploaded',
                    $lockedDocument,
                    $tenant,
                    after: $this->auditSnapshot($lockedDocument, $version->version_number, $version->checksum_sha256),
                );
                DB::afterCommit(fn () => ProcessDocumentVersion::dispatch((int) $tenant->getKey(), (int) $version->getKey()));

                return $version;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storedKey);
            throw $exception;
        }

        return redirect()
            ->route('institutions.documents.show', [$tenant->slug, $document])
            ->with('status', "Versi {$version->version_number} tersimpan. Versi sebelumnya tetap tersedia.");
    }

    public function update(
        Request $request,
        string $institution,
        int $document,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'documents.manage');
        $record = $this->documentQuery($tenant)->whereKey($document)->firstOrFail();
        $this->authorizeVisible($record, $tenant, $user);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'academic_period_id' => ['nullable', 'integer'],
            'access_level' => ['required', Rule::in(DocumentCategory::ACCESS_LEVELS)],
            'retention_until' => ['nullable', 'date', 'after_or_equal:today'],
            'status' => ['required', Rule::in([Document::STATUS_ACTIVE, Document::STATUS_ARCHIVED])],
        ]);
        $category = $this->categoryQuery($tenant)->whereKey($data['category_id'])->firstOrFail();
        $unit = $this->validatedUnit($tenant, $user, $data['unit_id'] ?? null);
        $period = $this->validatedPeriod($tenant, $data['academic_period_id'] ?? null);
        if ($data['access_level'] === Document::ACCESS_UNIT && $unit === null) {
            return back()->withErrors(['unit_id' => 'Akses unit memerlukan unit pemilik.'])->withInput();
        }
        $before = $this->auditSnapshot($record, $record->currentVersion?->version_number, $record->currentVersion?->checksum_sha256);

        DB::transaction(function () use ($record, $tenant, $data, $category, $unit, $period, $before): void {
            $record->fill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category_id' => $category->getKey(),
                'unit_id' => $unit?->getKey(),
                'academic_period_id' => $period?->getKey(),
                'access_level' => $data['access_level'],
                'retention_until' => $data['retention_until'] ?? null,
                'status' => $data['status'],
            ])->save();
            $after = $this->auditSnapshot(
                $record,
                $record->currentVersion?->version_number,
                $record->currentVersion?->checksum_sha256,
            );
            $action = $before['status'] !== $after['status']
                ? ($after['status'] === Document::STATUS_ARCHIVED ? 'document.archived' : 'document.restored')
                : (array_intersect_key($before, array_flip(['access_level', 'category_id', 'unit_id']))
                    === array_intersect_key($after, array_flip(['access_level', 'category_id', 'unit_id']))
                    ? 'document.metadata_updated'
                    : 'document.access_changed');
            app(AuditLogger::class)->record(
                $action,
                $record,
                $tenant,
                before: $before,
                after: $after,
            );
        });

        return redirect()
            ->route('institutions.documents.show', [$tenant->slug, $document])
            ->with('status', 'Metadata dan pengaturan akses dokumen diperbarui.');
    }

    public function verify(
        Request $request,
        string $institution,
        int $document,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'documents.verify');
        $record = $this->documentQuery($tenant)->whereKey($document)->firstOrFail();
        $this->authorizeVisible($record, $tenant, $user);
        $data = $request->validate([
            'verification_status' => ['required', Rule::in([
                Document::VERIFICATION_VERIFIED,
                Document::VERIFICATION_REJECTED,
                Document::VERIFICATION_UNVERIFIED,
            ])],
        ]);
        $before = ['verification_status' => $record->verification_status];
        $record->forceFill(['verification_status' => $data['verification_status']])->save();
        app(AuditLogger::class)->record(
            'document.verification_changed',
            $record,
            $tenant,
            before: $before,
            after: ['verification_status' => $record->verification_status],
        );

        return redirect()
            ->route('institutions.documents.show', [$tenant->slug, $document])
            ->with('status', 'Status verifikasi dokumen diperbarui.');
    }

    public function download(
        string $institution,
        int $document,
        int $version,
        TenantContext $tenantContext,
    ): StreamedResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission(request(), $tenant, 'documents.download');
        $record = $this->documentQuery($tenant)->whereKey($document)->firstOrFail();
        $this->authorizeVisible($record, $tenant, $user);
        $versionRecord = DocumentVersion::query()
            ->where('institution_id', $tenant->getKey())
            ->where('document_id', $record->getKey())
            ->whereKey($version)
            ->firstOrFail();
        abort_unless(in_array($versionRecord->scan_status, ['not_scanned', 'clean'], true), 404);

        app(AuditLogger::class)->record(
            'document.downloaded',
            $record,
            $tenant,
            after: $this->auditSnapshot($record, $versionRecord->version_number, $versionRecord->checksum_sha256),
        );

        return response()->streamDownload(function () use ($versionRecord): void {
            $stream = Storage::disk($versionRecord->storage_disk)->readStream($versionRecord->storage_key);
            if (! is_resource($stream)) {
                throw new RuntimeException('Private document file could not be read for download.');
            }
            fpassthru($stream);
            fclose($stream);
        }, $versionRecord->original_filename, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeRelation(
        Request $request,
        string $institution,
        int $document,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'documents.manage');
        $record = $this->documentQuery($tenant)->whereKey($document)->firstOrFail();
        $this->authorizeVisible($record, $tenant, $user);
        $data = $request->validate([
            'related_target' => ['required', 'string', 'regex:/^(project|project_indicator):[0-9]+$/'],
            'relation_type' => ['required', Rule::in(['supports', 'references'])],
        ]);
        [$relatedType, $relatedId] = explode(':', $data['related_target']);
        $this->assertRelatedTargetVisible($tenant, $user, $relatedType, (int) $relatedId);

        $relation = DB::transaction(function () use ($tenant, $user, $record, $relatedType, $relatedId, $data): DocumentRelation {
            $relation = $record->relations()->create([
                'institution_id' => $tenant->getKey(),
                'related_type' => $relatedType,
                'related_id' => (int) $relatedId,
                'relation_type' => $data['relation_type'],
                'created_by' => $user->getKey(),
            ]);
            app(AuditLogger::class)->record(
                'document.related',
                $record,
                $tenant,
                after: ['related_type' => $relatedType, 'related_id' => (int) $relatedId],
            );

            return $relation;
        });

        return redirect()
            ->route('institutions.documents.show', [$tenant->slug, $document])
            ->with('status', 'Relasi dokumen berhasil disimpan.');
    }

    public function deleteRelation(
        Request $request,
        string $institution,
        int $document,
        int $relation,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'documents.manage');
        $record = $this->documentQuery($tenant)->whereKey($document)->firstOrFail();
        $this->authorizeVisible($record, $tenant, $user);
        $link = DocumentRelation::query()
            ->where('institution_id', $tenant->getKey())
            ->where('document_id', $record->getKey())
            ->whereKey($relation)
            ->firstOrFail();
        $link->delete();
        app(AuditLogger::class)->record(
            'document.relation_removed',
            $record,
            $tenant,
            before: ['related_type' => $link->related_type, 'related_id' => $link->related_id],
        );

        return redirect()
            ->route('institutions.documents.show', [$tenant->slug, $document])
            ->with('status', 'Relasi dokumen dihapus.');
    }

    public function categories(string $institution, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        $this->authorizePermission(request(), $tenant, 'documents.categories.manage');
        $categories = $this->categoryQuery($tenant)->withCount('documents')->orderBy('name')->paginate(30);

        return view('documents.categories', compact('tenant', 'categories'));
    }

    public function storeCategory(
        Request $request,
        string $institution,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'documents.categories.manage');
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('document_categories', 'code')->where('institution_id', $tenant->getKey()),
            ],
            'name' => ['required', 'string', 'max:160'],
            'ppepp_stage' => ['nullable', Rule::in(DocumentCategory::PPEPP_STAGES)],
            'access_default' => ['required', Rule::in(DocumentCategory::ACCESS_LEVELS)],
        ]);

        $category = DocumentCategory::query()->create([
            'institution_id' => $tenant->getKey(),
            ...$data,
            'is_active' => true,
        ]);
        app(AuditLogger::class)->record(
            'document_category.created',
            $category,
            $tenant,
            after: $this->categorySnapshot($category),
        );

        return redirect()
            ->route('institutions.documents.categories', $tenant->slug)
            ->with('status', 'Kategori dokumen berhasil dibuat.');
    }

    public function updateCategory(
        Request $request,
        string $institution,
        int $category,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $this->authorizePermission($request, $tenant, 'documents.categories.manage');
        $record = $this->categoryQuery($tenant)->whereKey($category)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'ppepp_stage' => ['nullable', Rule::in(DocumentCategory::PPEPP_STAGES)],
            'access_default' => ['required', Rule::in(DocumentCategory::ACCESS_LEVELS)],
            'is_active' => ['required', 'boolean'],
        ]);
        $before = $this->categorySnapshot($record);
        $record->fill($data)->save();
        app(AuditLogger::class)->record(
            'document_category.updated',
            $record,
            $tenant,
            before: $before,
            after: $this->categorySnapshot($record),
        );

        return redirect()
            ->route('institutions.documents.categories', $tenant->slug)
            ->with('status', 'Kategori dokumen diperbarui.');
    }

    private function authorizePermission(Request $request, Institution $tenant, string $permission): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasPermission($permission, $tenant), 403);

        return $user;
    }

    private function authorizeVisible(Document $document, Institution $tenant, User $user): void
    {
        $unitAllowed = $document->unit_id === null
            || in_array((int) $document->unit_id, $user->accessibleUnitIds($tenant), true);
        $accessAllowed = match ($document->access_level) {
            Document::ACCESS_INSTITUTION => true,
            Document::ACCESS_UNIT => $document->unit_id !== null
                && in_array((int) $document->unit_id, $user->accessibleUnitIds($tenant), true),
            Document::ACCESS_PRIVATE => (int) $document->owner_user_id === (int) $user->getKey()
                || $user->hasPermission('documents.manage', $tenant),
            default => false,
        };
        abort_unless(
            $unitAllowed
            && $accessAllowed
            && ($document->status === Document::STATUS_ACTIVE
                || $user->hasPermission('documents.manage', $tenant)),
            404,
        );
    }

    /**
     * @return Builder<Document>
     */
    private function visibleDocuments(Institution $tenant, User $user): Builder
    {
        $units = $user->accessibleUnitIds($tenant);

        return $this->documentQuery($tenant)
            ->where(function (Builder $query) use ($tenant, $user, $units): void {
                if ($user->hasPermission('documents.manage', $tenant)) {
                    $query->where(function (Builder $scope) use ($units): void {
                        $scope->whereNull('unit_id');
                        if ($units !== []) {
                            $scope->orWhereIn('unit_id', $units);
                        }
                    });

                    return;
                }

                $query->where(function (Builder $scope) use ($units, $user): void {
                    $scope->where('access_level', Document::ACCESS_INSTITUTION);
                    if ($units !== []) {
                        $scope->orWhere(function (Builder $unitScope) use ($units): void {
                            $unitScope->where('access_level', Document::ACCESS_UNIT)
                                ->whereIn('unit_id', $units);
                        });
                    }
                    $scope->orWhere(function (Builder $privateScope) use ($user): void {
                        $privateScope->where('access_level', Document::ACCESS_PRIVATE)
                            ->where('owner_user_id', $user->getKey());
                    });
                })->where(function (Builder $unitScope) use ($units): void {
                    $unitScope->whereNull('unit_id');
                    if ($units !== []) {
                        $unitScope->orWhereIn('unit_id', $units);
                    }
                });
            })
            ->where(function (Builder $query) use ($tenant, $user): void {
                if (! $user->hasPermission('documents.manage', $tenant)) {
                    $query->where('status', Document::STATUS_ACTIVE);
                }
            });
    }

    /**
     * @return Builder<Document>
     */
    private function documentQuery(Institution $tenant): Builder
    {
        return Document::query()->where('institution_id', $tenant->getKey());
    }

    /**
     * @return Builder<DocumentCategory>
     */
    private function categoryQuery(Institution $tenant): Builder
    {
        return DocumentCategory::query()->where('institution_id', $tenant->getKey());
    }

    private function validatedUnit(Institution $tenant, User $user, mixed $unitId): ?OrganizationalUnit
    {
        if ($unitId === null || $unitId === '') {
            return null;
        }
        $unit = $tenant->organizationalUnits()->whereKey($unitId)->firstOrFail();
        abort_unless($user->canAccessUnit($tenant, $unit), 404);

        return $unit;
    }

    private function validatedPeriod(Institution $tenant, mixed $periodId): ?AcademicPeriod
    {
        if ($periodId === null || $periodId === '') {
            return null;
        }

        return AcademicPeriod::query()
            ->where('institution_id', $tenant->getKey())
            ->whereKey($periodId)
            ->firstOrFail();
    }

    private function privateDisk(): string
    {
        $disk = config('documents.disk');
        if (! is_string($disk) || $disk === '' || $disk === 'public'
            || config("filesystems.disks.{$disk}.visibility") === 'public') {
            throw new RuntimeException('Document storage must use a configured private filesystem disk.');
        }

        return $disk;
    }

    private function safeFilename(string $filename, string $extension): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';
        $filename = trim(mb_substr($filename, 0, 255));

        return $filename === '' ? 'document.'.$extension : $filename;
    }

    private function uploadedChecksum(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $checksum = is_string($path) ? hash_file('sha256', $path) : false;
        if (! is_string($checksum)) {
            throw new RuntimeException('Document checksum could not be calculated.');
        }

        return $checksum;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(Document $document, ?int $versionNumber, ?string $checksum): array
    {
        return [
            'title' => $document->title,
            'category_id' => $document->category_id,
            'academic_period_id' => $document->academic_period_id,
            'unit_id' => $document->unit_id,
            'owner_user_id' => $document->owner_user_id,
            'access_level' => $document->access_level,
            'verification_status' => $document->verification_status,
            'status' => $document->status,
            'retention_until' => $document->retention_until?->toDateString(),
            'version_number' => $versionNumber,
            'checksum_sha256' => $checksum,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categorySnapshot(DocumentCategory $category): array
    {
        return [
            'name' => $category->name,
            'category_id' => $category->getKey(),
            'ppepp_stage' => $category->ppepp_stage,
            'access_default' => $category->access_default,
            'is_active' => $category->is_active,
        ];
    }

    private function assertRelatedTargetVisible(
        Institution $tenant,
        User $user,
        string $relatedType,
        int $relatedId,
    ): void {
        if ($relatedType === DocumentRelation::RELATED_PROJECT) {
            $project = AccreditationProject::query()
                ->where('institution_id', $tenant->getKey())
                ->whereKey($relatedId)
                ->firstOrFail();
            if ($project->unit_id !== null) {
                abort_unless(in_array((int) $project->unit_id, $user->accessibleUnitIds($tenant), true), 404);
            }

            return;
        }

        abort_unless($relatedType === DocumentRelation::RELATED_PROJECT_INDICATOR, 404);
        $indicator = ProjectIndicator::query()
            ->where('institution_id', $tenant->getKey())
            ->whereKey($relatedId)
            ->firstOrFail();
        $project = AccreditationProject::query()
            ->where('institution_id', $tenant->getKey())
            ->whereKey($indicator->project_id)
            ->firstOrFail();
        if ($project->unit_id !== null) {
            abort_unless(in_array((int) $project->unit_id, $user->accessibleUnitIds($tenant), true), 404);
        }
    }

    /**
     * @return Collection<int, OrganizationalUnit>
     */
    private function accessibleUnits(Institution $tenant, User $user): Collection
    {
        $ids = $user->accessibleUnitIds($tenant);

        return $tenant->organizationalUnits()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }
}
