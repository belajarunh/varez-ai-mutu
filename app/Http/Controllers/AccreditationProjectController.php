<?php

namespace App\Http\Controllers;

use App\Models\AccreditationProject;
use App\Models\Institution;
use App\Models\InstrumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\ProjectIndicator;
use App\Models\User;
use App\Services\Projects\AccreditationProjectCreator;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccreditationProjectController extends Controller
{
    public function index(string $institution, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        Gate::authorize('viewAny', [AccreditationProject::class, $tenant]);

        $projects = AccreditationProject::query()
            ->where('institution_id', $tenant->getKey())
            ->with(['instrumentVersion.instrument', 'unit', 'owner'])
            ->latest()
            ->paginate(15);

        return view('projects.index', compact('tenant', 'projects'));
    }

    public function create(string $institution, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        Gate::authorize('create', [AccreditationProject::class, $tenant]);

        return view('projects.create', $this->formData($tenant));
    }

    public function store(
        Request $request,
        string $institution,
        TenantContext $tenantContext,
        AccreditationProjectCreator $projectCreator,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        Gate::authorize('create', [AccreditationProject::class, $tenant]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'unit_id' => ['nullable', 'integer'],
            'instrument_version_id' => ['required', 'integer', 'exists:instrument_versions,id'],
            'period_label' => ['required', 'string', 'max:100'],
            'target_date' => ['nullable', 'date', 'after_or_equal:today'],
            'owner_user_id' => ['required', 'integer'],
            'team_user_ids' => ['nullable', 'array', 'max:100'],
            'team_user_ids.*' => ['integer', 'distinct'],
            'confirmation' => ['accepted'],
        ]);

        $version = InstrumentVersion::query()
            ->whereKey($validated['instrument_version_id'])
            ->where('status', InstrumentVersion::STATUS_PUBLISHED)
            ->where('verification_status', InstrumentVersion::VERIFICATION_VERIFIED)
            ->whereHas('instrument', fn ($query) => $query
                ->where('domain_type', $tenant->institution_type)
                ->where('status', 'published')
                ->whereHas('accreditationBody', fn ($bodyQuery) => $bodyQuery
                    ->where('status', 'verified')
                    ->whereNotNull('official_url')))
            ->firstOrFail();

        $unitId = isset($validated['unit_id']) ? (int) $validated['unit_id'] : null;
        if ($unitId !== null && ! $request->user()->canAccessUnit(
            $tenant,
            $tenant->organizationalUnits()->whereKey($unitId)->firstOrFail(),
        )) {
            abort(404);
        }

        $project = $projectCreator->create(
            institution: $tenant,
            actor: $request->user(),
            version: $version,
            unitId: $unitId,
            title: $validated['title'],
            periodLabel: $validated['period_label'],
            targetDate: $validated['target_date'] ?? null,
            ownerUserId: (int) $validated['owner_user_id'],
            teamUserIds: array_map('intval', $validated['team_user_ids'] ?? []),
        );

        return redirect()
            ->route('institutions.projects.show', ['institution' => $tenant->slug, 'project' => $project->getKey()])
            ->with('status', 'Proyek berhasil dibuat dengan snapshot instrumen yang tidak berubah.');
    }

    public function show(string $institution, int $project, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        Gate::authorize('viewAny', [AccreditationProject::class, $tenant]);

        $record = AccreditationProject::query()
            ->where('institution_id', $tenant->getKey())
            ->whereKey($project)
            ->with(['instrumentVersion.instrument', 'unit', 'owner', 'members', 'indicators.owner'])
            ->firstOrFail();
        Gate::authorize('view', $record);

        $progress = $record->indicators->count() === 0
            ? 0
            : (int) round($record->indicators->where('status', 'approved')->count() / $record->indicators->count() * 100);

        return view('projects.show', compact('tenant', 'record', 'progress'));
    }

    public function updateIndicator(
        Request $request,
        string $institution,
        int $project,
        int $indicator,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        abort_unless($request->user()->hasPermission('projects.manage', $tenant), 403);

        $projectRecord = AccreditationProject::query()
            ->where('institution_id', $tenant->getKey())
            ->whereKey($project)
            ->firstOrFail();
        $projectIndicator = ProjectIndicator::query()
            ->where('institution_id', $tenant->getKey())
            ->where('project_id', $projectRecord->getKey())
            ->whereKey($indicator)
            ->firstOrFail();
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', ProjectIndicator::STATUSES)],
            'owner_user_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
        ]);

        $ownerUserId = isset($data['owner_user_id']) ? (int) $data['owner_user_id'] : null;
        if ($ownerUserId !== null && ! $tenant->memberships()
            ->where('user_id', $ownerUserId)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->exists()) {
            abort(422, 'PIC indikator harus menjadi anggota aktif institusi ini.');
        }
        if ($ownerUserId !== null && ! $projectRecord->members()->whereKey($ownerUserId)->exists()) {
            abort(422, 'PIC indikator harus termasuk tim proyek.');
        }

        $before = [
            'status' => $projectIndicator->status,
            'owner_user_id' => $projectIndicator->owner_user_id,
            'due_date' => $projectIndicator->due_date?->toDateString(),
        ];

        DB::transaction(function () use ($projectIndicator, $tenant, $data, $ownerUserId, $before): void {
            $projectIndicator->fill([
                'status' => $data['status'],
                'owner_user_id' => $ownerUserId,
                'due_date' => $data['due_date'] ?? null,
            ])->save();

            app(AuditLogger::class)->record(
                'project_indicator.updated',
                $projectIndicator,
                $tenant,
                before: $before,
                after: [
                    'status' => $projectIndicator->status,
                    'owner_user_id' => $projectIndicator->owner_user_id,
                    'due_date' => $projectIndicator->due_date?->toDateString(),
                ],
            );
        });

        return redirect()
            ->route('institutions.projects.show', [$tenant->slug, $projectRecord->getKey()])
            ->with('status', 'Status, PIC, dan tenggat indikator diperbarui.');
    }

    /**
     * @return array{
     *     tenant: Institution,
     *     domainType: string,
     *     units: Collection<int, OrganizationalUnit>,
     *     members: Collection<int, User>,
     *     instrumentVersions: Collection<int, InstrumentVersion>
     * }
     */
    private function formData(Institution $tenant): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $unitQuery = $tenant->organizationalUnits()->where('status', 'active')->orderBy('name');
        if (! $user->hasPermission('units.access_all', $tenant)) {
            $unitQuery->whereIn('id', $user->accessibleUnitIds($tenant));
        }

        return [
            'tenant' => $tenant,
            'domainType' => $tenant->institution_type,
            'units' => $unitQuery->get(),
            'members' => User::query()
                ->where('status', 'active')
                ->whereHas('institutionMemberships', fn ($query) => $query
                    ->where('institution_id', $tenant->getKey())
                    ->where('status', 'active')
                    ->whereHas('user', fn ($userQuery) => $userQuery->where('status', 'active')))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'instrumentVersions' => InstrumentVersion::query()
                ->where('status', InstrumentVersion::STATUS_PUBLISHED)
                ->where('verification_status', InstrumentVersion::VERIFICATION_VERIFIED)
                ->where(fn ($query) => $query
                    ->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', today()))
                ->where(fn ($query) => $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', today()))
                ->whereHas('instrument', fn ($query) => $query
                    ->where('domain_type', $tenant->institution_type)
                    ->where('status', 'published')
                    ->whereHas('accreditationBody', fn ($bodyQuery) => $bodyQuery
                        ->where('status', 'verified')
                        ->whereNotNull('official_url')))
                ->with('instrument.accreditationBody')
                ->orderByDesc('effective_from')
                ->get(),
        ];
    }
}
