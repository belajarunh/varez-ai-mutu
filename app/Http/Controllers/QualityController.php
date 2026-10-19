<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;
use App\Models\AuditFindingEvidence;
use App\Models\AuditSession;
use App\Models\CorrectiveAction;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Institution;
use App\Models\OrganizationalUnit;
use App\Models\PpeppActivity;
use App\Models\PpeppEvidence;
use App\Models\QualityHistory;
use App\Models\QualityIndicator;
use App\Models\QualityPolicy;
use App\Models\QualityStandard;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QualityController extends Controller
{
    public function index(string $institution, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission(request(), $tenant, 'quality.view');
        $today = Carbon::now($tenant->timezone)->startOfDay()->toDateString();
        $standards = $this->visibleStandards($tenant, $user)
            ->with(['unit', 'owner', 'indicators'])
            ->latest('updated_at')
            ->limit(50)
            ->get();
        $visibleDocumentIds = $user->hasPermission('documents.view', $tenant)
            ? $this->visibleDocuments($tenant, $user)->select('documents.id')
            : Document::query()->whereRaw('1 = 0')->select('documents.id');
        $activities = $this->visibleActivities($tenant, $user)
            ->with([
                'standard',
                'indicator',
                'unit',
                'owner',
                'approver',
                'evidence' => function (Relation $query) use ($visibleDocumentIds): void {
                    $query->whereIn('document_id', $visibleDocumentIds)
                        ->with(['document', 'documentVersion']);
                },
            ])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->limit(50)
            ->get();
        $audits = $this->visibleAudits($tenant, $user)
            ->with([
                'unit',
                'period',
                'leadAuditor',
                'auditors',
                'checklistItems',
                'findings.correctiveActions',
                'findings.evidence' => function (Relation $query) use ($visibleDocumentIds): void {
                    $query->whereIn('document_id', $visibleDocumentIds)
                        ->with(['document', 'documentVersion']);
                },
            ])
            ->latest('starts_at')
            ->limit(30)
            ->get();
        $actions = $this->visibleActions($tenant, $user)
            ->with(['finding.auditSession', 'owner'])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->limit(50)
            ->get();
        $histories = $this->visibleHistories($tenant, $user)
            ->with('actor')
            ->latest('created_at')
            ->limit(30)
            ->get();
        $units = $this->accessibleUnits($tenant, $user);
        $periods = AcademicPeriod::query()
            ->where('institution_id', $tenant->getKey())
            ->orderByDesc('starts_on')
            ->get();
        $members = $tenant->memberships()
            ->where('status', 'active')
            ->with('user:id,name,email')
            ->get()
            ->filter(fn ($membership): bool => $membership->user instanceof User)
            ->filter(fn ($membership): bool => $this->userCanBeAssigned($tenant, $membership->user, null))
            ->values();
        $documents = $user->hasPermission('documents.view', $tenant)
            ? $this->visibleDocuments($tenant, $user)
                ->with(['currentVersion', 'versions'])
                ->latest('updated_at')
                ->limit(50)
                ->get()
            : collect();
        $policy = QualityPolicy::query()->firstOrNew(
            ['institution_id' => $tenant->getKey()],
            [
                'require_effectiveness_verification' => true,
                'require_independent_verifier' => false,
                'activity_completion_requires_approval' => false,
            ],
        );
        $overdueActivities = $this->visibleActivities($tenant, $user)
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();
        $overdueFindings = $this->visibleFindings($tenant, $user)
            ->whereIn('status', ['open', 'action_planned', 'in_progress', 'pending_verification'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();
        $overdueActions = $this->visibleActions($tenant, $user)
            ->whereIn('status', ['planned', 'in_progress', 'pending_verification'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        return view('quality.index', compact(
            'tenant',
            'user',
            'standards',
            'activities',
            'audits',
            'actions',
            'histories',
            'units',
            'periods',
            'members',
            'documents',
            'policy',
            'today',
            'overdueActivities',
            'overdueFindings',
            'overdueActions',
        ));
    }

    public function storeStandard(Request $request, string $institution, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.standards.manage');
        $data = $request->validate([
            'unit_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'standard_type' => ['nullable', 'string', 'max:80'],
            'version' => ['required', 'integer', 'min:1', 'max:65535'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'owner_user_id' => ['nullable', 'integer'],
        ]);
        if (QualityStandard::query()
            ->where('institution_id', $tenant->getKey())
            ->where('code', $data['code'])
            ->where('version', $data['version'])
            ->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode dan versi standar sudah digunakan pada institusi ini.']);
        }
        $unit = $this->validatedUnit($tenant, $user, $data['unit_id'] ?? null);
        $owner = $this->validatedMember($tenant, $data['owner_user_id'] ?? null, $unit);
        $standard = DB::transaction(function () use ($tenant, $unit, $owner, $data): QualityStandard {
            $standard = QualityStandard::query()->create([
                'institution_id' => $tenant->getKey(),
                ...$data,
                'unit_id' => $unit?->getKey(),
                'owner_user_id' => $owner?->getKey(),
                'status' => 'draft',
            ]);
            $this->recordEvent($tenant, $standard, 'quality.standard.created', [], ['status' => 'draft']);

            return $standard;
        });

        return redirect()->route('institutions.quality.index', $tenant->slug)
            ->with('status', 'Standar mutu dibuat sebagai draft. Lengkapi indikator sebelum dipublikasikan.');
    }

    public function updateStandard(
        Request $request,
        string $institution,
        int $standard,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.standards.manage');
        $record = $this->visibleStandards($tenant, $user)->whereKey($standard)->firstOrFail();
        abort_unless($record->status === 'draft', 422, 'Standar terbit tidak dapat diedit; buat versi baru.');
        $data = $request->validate([
            'unit_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'standard_type' => ['nullable', 'string', 'max:80'],
            'version' => ['required', 'integer', 'min:1', 'max:65535'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'owner_user_id' => ['nullable', 'integer'],
        ]);
        if (QualityStandard::query()
            ->where('institution_id', $tenant->getKey())
            ->where('code', $data['code'])
            ->where('version', $data['version'])
            ->where('id', '<>', $record->getKey())
            ->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode dan versi standar sudah digunakan pada institusi ini.']);
        }
        $unit = $this->validatedUnit($tenant, $user, $data['unit_id'] ?? null);
        $owner = $this->validatedMember($tenant, $data['owner_user_id'] ?? null, $unit);
        $before = ['title' => $record->title, 'code' => $record->code, 'version' => $record->version];
        DB::transaction(function () use ($tenant, $record, $unit, $owner, $data, $before): void {
            $record->fill([
                ...$data,
                'unit_id' => $unit?->getKey(),
                'owner_user_id' => $owner?->getKey(),
            ])->save();
            $this->recordEvent($tenant, $record, 'quality.standard.updated', $before, [
                'title' => $record->title,
                'code' => $record->code,
                'version' => $record->version,
            ]);
        });

        return back()->with('status', 'Draft standar diperbarui.');
    }

    public function storeIndicator(
        Request $request,
        string $institution,
        int $standard,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.standards.manage');
        $record = $this->visibleStandards($tenant, $user)->whereKey($standard)->firstOrFail();
        abort_unless($record->status === 'draft', 422, 'Indikator hanya dapat diubah pada standar draft.');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'target_json' => ['nullable', 'array'],
            'target_json.*' => ['nullable', 'string', 'max:255'],
            'measurement_method' => ['nullable', 'string', 'max:5000'],
            'frequency' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
        if (QualityIndicator::query()
            ->where('institution_id', $tenant->getKey())
            ->where('standard_id', $record->getKey())
            ->where('code', $data['code'])
            ->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode indikator sudah digunakan pada standar ini.']);
        }
        DB::transaction(function () use ($tenant, $record, $data): void {
            $indicator = QualityIndicator::query()->create([
                'institution_id' => $tenant->getKey(),
                'standard_id' => $record->getKey(),
                ...$data,
            ]);
            $this->recordEvent($tenant, $indicator, 'quality.indicator.created', [], [
                'standard_id' => $record->getKey(),
            ]);
        });

        return redirect()->route('institutions.quality.index', $tenant->slug)
            ->with('status', 'Indikator ditambahkan ke standar.');
    }

    public function updateIndicator(
        Request $request,
        string $institution,
        int $standard,
        int $indicator,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.standards.manage');
        $parent = $this->visibleStandards($tenant, $user)->whereKey($standard)->firstOrFail();
        abort_unless($parent->status === 'draft', 422, 'Indikator pada standar terbit tidak dapat diedit.');
        $record = QualityIndicator::query()
            ->where('institution_id', $tenant->getKey())
            ->where('standard_id', $parent->getKey())
            ->whereKey($indicator)
            ->firstOrFail();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'target_json' => ['nullable', 'array'],
            'target_json.*' => ['nullable', 'string', 'max:255'],
            'measurement_method' => ['nullable', 'string', 'max:5000'],
            'frequency' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
        if (QualityIndicator::query()
            ->where('institution_id', $tenant->getKey())
            ->where('standard_id', $parent->getKey())
            ->where('code', $data['code'])
            ->where('id', '<>', $record->getKey())
            ->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode indikator sudah digunakan pada standar ini.']);
        }
        $before = ['code' => $record->code, 'name' => $record->name];
        DB::transaction(function () use ($tenant, $record, $data, $before): void {
            $record->fill($data)->save();
            $this->recordEvent($tenant, $record, 'quality.indicator.updated', $before, [
                'code' => $record->code,
                'name' => $record->name,
            ]);
        });

        return back()->with('status', 'Indikator draft diperbarui.');
    }

    public function updateStandardStatus(
        Request $request,
        string $institution,
        int $standard,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.standards.manage');
        $record = $this->visibleStandards($tenant, $user)->whereKey($standard)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'retired'])]]);
        abort_unless($record->status === 'draft' || ($record->status === 'published' && $data['status'] === 'retired'), 422);
        abort_if(
            $data['status'] === 'published' && ! $record->indicators()->exists(),
            422,
            'Tambahkan minimal satu indikator sebelum standar dipublikasikan.',
        );
        $before = ['status' => $record->status];
        DB::transaction(function () use ($tenant, $record, $data, $before): void {
            $record->status = $data['status'];
            $record->save();
            $this->recordEvent($tenant, $record, 'quality.standard.status_changed', $before, ['status' => $record->status]);
        });

        return back()->with('status', 'Status standar diperbarui.');
    }

    public function storeActivity(Request $request, string $institution, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.activities.manage');
        $data = $request->validate([
            'standard_id' => ['required', 'integer'],
            'indicator_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'academic_period_id' => ['nullable', 'integer'],
            'stage' => ['required', Rule::in(PpeppActivity::STAGES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'owner_user_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
        ]);
        $standard = $this->visibleStandards($tenant, $user)->whereKey($data['standard_id'])->firstOrFail();
        abort_unless($standard->status === 'published', 422, 'Aktivitas harus menggunakan standar yang dipublikasikan.');
        $indicator = null;
        if (! empty($data['indicator_id'])) {
            $indicator = QualityIndicator::query()
                ->where('institution_id', $tenant->getKey())
                ->where('standard_id', $standard->getKey())
                ->whereKey($data['indicator_id'])
                ->firstOrFail();
        }
        $unit = $this->validatedUnit($tenant, $user, $data['unit_id'] ?? $standard->unit_id);
        if ($standard->unit_id !== null) {
            abort_unless((int) $unit?->getKey() === (int) $standard->unit_id, 422, 'Aktivitas harus berada pada unit pemilik standar.');
        }
        $owner = $this->validatedMember($tenant, $data['owner_user_id'] ?? null, $unit);
        $period = $this->validatedPeriod($tenant, $data['academic_period_id'] ?? null);
        DB::transaction(function () use ($tenant, $standard, $indicator, $unit, $owner, $period, $data): void {
            $activity = PpeppActivity::query()->create([
                'institution_id' => $tenant->getKey(),
                'standard_id' => $standard->getKey(),
                'indicator_id' => $indicator?->getKey(),
                'unit_id' => $unit?->getKey(),
                'academic_period_id' => $period?->getKey(),
                'stage' => $data['stage'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'owner_user_id' => $owner?->getKey(),
                'due_date' => $data['due_date'] ?? null,
                'status' => 'planned',
            ]);
            $this->recordEvent($tenant, $activity, 'quality.activity.created', [], ['status' => 'planned']);
        });

        return back()->with('status', 'Aktivitas PPEPP dibuat.');
    }

    public function updateActivityStatus(
        Request $request,
        string $institution,
        int $activity,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.activities.manage');
        $record = $this->visibleActivities($tenant, $user)->whereKey($activity)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(PpeppActivity::STATUSES)]]);
        $policy = $this->policyFor($tenant);
        $allowed = [
            'planned' => ['in_progress', 'cancelled'],
            'in_progress' => $policy->activity_completion_requires_approval
                ? ['pending_approval', 'cancelled']
                : ['completed', 'cancelled'],
            'completed' => [],
            'pending_approval' => [],
            'cancelled' => [],
        ];
        abort_unless(in_array($data['status'], $allowed[$record->status] ?? [], true), 422, 'Transisi status aktivitas tidak diizinkan.');
        $before = ['status' => $record->status];
        DB::transaction(function () use ($tenant, $record, $data, $before): void {
            $record->status = $data['status'];
            $record->completed_at = $record->status === 'completed' ? now() : null;
            $record->save();
            $this->recordEvent($tenant, $record, 'quality.activity.status_changed', $before, ['status' => $record->status]);
        });

        return back()->with('status', 'Status aktivitas diperbarui.');
    }

    public function reviewActivity(
        Request $request,
        string $institution,
        int $activity,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.activities.approve');
        $record = $this->visibleActivities($tenant, $user)->whereKey($activity)->firstOrFail();
        abort_unless($record->status === 'pending_approval', 422, 'Aktivitas belum menunggu persetujuan.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return'])],
            'approval_notes' => ['required', 'string', 'max:5000'],
        ]);
        $before = ['status' => $record->status];
        DB::transaction(function () use ($tenant, $user, $record, $data, $before): void {
            $approved = $data['decision'] === 'approve';
            $record->status = $approved ? 'completed' : 'in_progress';
            $record->completed_at = $approved ? now() : null;
            $record->approved_by = $approved ? $user->getKey() : null;
            $record->approved_at = $approved ? now() : null;
            $record->approval_notes = $data['approval_notes'];
            $record->save();
            $this->recordEvent(
                $tenant,
                $record,
                $approved ? 'quality.activity.approved' : 'quality.activity.returned',
                $before,
                ['status' => $record->status, 'approved_by' => $record->approved_by],
            );
        });

        return back()->with(
            'status',
            $data['decision'] === 'approve' ? 'Aktivitas PPEPP disetujui.' : 'Aktivitas dikembalikan untuk perbaikan.',
        );
    }

    public function storeActivityEvidence(
        Request $request,
        string $institution,
        int $activity,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.activities.manage');
        $record = $this->visibleActivities($tenant, $user)->whereKey($activity)->firstOrFail();
        $this->authorizePermission($request, $tenant, 'documents.view');
        $data = $request->validate([
            'evidence_version' => ['required', 'string', 'regex:/^[0-9]+:[0-9]+$/'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        [$documentId, $versionId] = array_map('intval', explode(':', $data['evidence_version']));
        [$document, $version] = $this->validatedEvidenceDocument($tenant, $user, $documentId, $versionId);
        DB::transaction(function () use ($tenant, $user, $record, $document, $version, $data): void {
            PpeppEvidence::query()->create([
                'institution_id' => $tenant->getKey(),
                'activity_id' => $record->getKey(),
                'document_id' => $document->getKey(),
                'document_version_id' => $version->getKey(),
                'linked_by' => $user->getKey(),
                'note' => $data['note'] ?? null,
            ]);
            $this->recordEvent($tenant, $record, 'quality.activity.evidence_added', [], [
                'activity_id' => $record->getKey(),
                'related_id' => $document->getKey(),
            ]);
        });

        return back()->with('status', 'Versi dokumen ditautkan sebagai bukti PPEPP.');
    }

    public function storeAudit(Request $request, string $institution, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.audits.manage');
        $data = $request->validate([
            'unit_id' => ['nullable', 'integer'],
            'academic_period_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'string', 'max:5000'],
            'lead_auditor_id' => ['required', 'integer'],
            'auditor_ids' => ['nullable', 'array', 'max:50'],
            'auditor_ids.*' => ['required', 'integer', 'distinct'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $unit = $this->validatedUnit($tenant, $user, $data['unit_id'] ?? null);
        $lead = $this->validatedMember($tenant, $data['lead_auditor_id'], $unit);
        abort_unless($lead instanceof User, 422);
        $period = $this->validatedPeriod($tenant, $data['academic_period_id'] ?? null);
        $auditorIds = array_unique(array_map('intval', [
            $lead->getKey(),
            ...($data['auditor_ids'] ?? []),
        ]));
        foreach ($auditorIds as $auditorId) {
            $auditor = $this->validatedMember($tenant, $auditorId, $unit);
            abort_unless(
                $auditor instanceof User
                    && ($auditor->hasPermission('quality.audits.perform', $tenant)
                        || $auditor->hasPermission('quality.audits.manage', $tenant)),
                422,
                'Auditor yang ditugaskan harus memiliki permission untuk melaksanakan audit.',
            );
        }
        $audit = DB::transaction(function () use ($tenant, $unit, $period, $lead, $auditorIds, $data): AuditSession {
            $audit = AuditSession::query()->create([
                'institution_id' => $tenant->getKey(),
                'unit_id' => $unit?->getKey(),
                'academic_period_id' => $period?->getKey(),
                'title' => $data['title'],
                'scope_json' => ['description' => $data['scope']],
                'lead_auditor_id' => $lead->getKey(),
                'starts_at' => Carbon::parse($data['starts_at'], $tenant->timezone)->utc(),
                'ends_at' => isset($data['ends_at']) ? Carbon::parse($data['ends_at'], $tenant->timezone)->utc() : null,
                'status' => 'planned',
            ]);
            $now = now();
            foreach ($auditorIds as $auditorId) {
                DB::table('audit_session_auditors')->insert([
                    'institution_id' => $tenant->getKey(),
                    'audit_session_id' => $audit->getKey(),
                    'user_id' => $auditorId,
                    'assigned_at' => $now,
                ]);
            }
            $this->recordEvent($tenant, $audit, 'quality.audit.created', [], ['status' => 'planned']);

            return $audit;
        });

        return back()->with('status', 'Rencana AMI dan auditor berhasil disimpan.');
    }

    public function updateAuditStatus(
        Request $request,
        string $institution,
        int $audit,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.view');
        $record = $this->visibleAudits($tenant, $user)->whereKey($audit)->firstOrFail();
        $this->authorizeAuditParticipant($user, $tenant, $record);
        $data = $request->validate(['status' => ['required', Rule::in(AuditSession::STATUSES)]]);
        $allowed = [
            'planned' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];
        abort_unless(in_array($data['status'], $allowed[$record->status] ?? [], true), 422, 'Transisi status audit tidak diizinkan.');
        if ($data['status'] === 'completed') {
            abort_if(
                $record->checklistItems()->where('status', 'pending')->exists(),
                422,
                'Selesaikan atau tandai tidak berlaku seluruh butir checklist sebelum audit ditutup.',
            );
        }
        $before = ['status' => $record->status];
        DB::transaction(function () use ($tenant, $record, $data, $before): void {
            $record->status = $data['status'];
            $record->ends_at = $record->status === 'completed' ? now() : $record->ends_at;
            $record->save();
            $this->recordEvent($tenant, $record, 'quality.audit.status_changed', $before, ['status' => $record->status]);
        });

        return back()->with('status', 'Status audit diperbarui.');
    }

    public function storeChecklistItem(
        Request $request,
        string $institution,
        int $audit,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.audits.manage');
        $session = $this->visibleAudits($tenant, $user)->whereKey($audit)->firstOrFail();
        abort_unless(in_array($session->status, ['planned', 'in_progress'], true), 422);
        $data = $request->validate([
            'standard_id' => ['nullable', 'integer'],
            'indicator_id' => ['nullable', 'integer'],
            'question' => ['required', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
        $standard = null;
        if (! empty($data['standard_id'])) {
            $standard = $this->visibleStandards($tenant, $user)
                ->whereKey($data['standard_id'])
                ->firstOrFail();
        }
        $indicator = null;
        if (! empty($data['indicator_id'])) {
            $indicatorQuery = QualityIndicator::query()->where('institution_id', $tenant->getKey())
                ->whereKey($data['indicator_id'])
                ->whereHas('standard', fn (Builder $query) => $this->scopeUnit($query, 'unit_id', $tenant, $user));
            if ($standard !== null) {
                $indicatorQuery->where('standard_id', $standard->getKey());
            }
            $indicator = $indicatorQuery->with('standard')->firstOrFail();
            $standard ??= $indicator->standard;
        }
        DB::transaction(function () use ($tenant, $session, $standard, $indicator, $data): void {
            $item = AuditChecklistItem::query()->create([
                'institution_id' => $tenant->getKey(),
                'audit_session_id' => $session->getKey(),
                'standard_id' => $standard?->getKey(),
                'indicator_id' => $indicator?->getKey(),
                'question' => $data['question'],
                'sort_order' => $data['sort_order'],
                'status' => 'pending',
            ]);
            $this->recordEvent($tenant, $item, 'quality.checklist.created', [], ['status' => 'pending']);
        });

        return back()->with('status', 'Butir checklist ditambahkan.');
    }

    public function updateChecklistItem(
        Request $request,
        string $institution,
        int $item,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.view');
        $record = AuditChecklistItem::query()
            ->where('institution_id', $tenant->getKey())
            ->whereHas('auditSession', fn (Builder $query) => $this->scopeUnit($query, 'unit_id', $tenant, $user))
            ->whereKey($item)
            ->firstOrFail();
        $session = $record->auditSession()->firstOrFail();
        $this->authorizeAuditParticipant($user, $tenant, $session);
        $data = $request->validate([
            'status' => ['required', Rule::in(AuditChecklistItem::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $before = ['status' => $record->status];
        DB::transaction(function () use ($tenant, $record, $data, $before): void {
            $record->fill($data)->save();
            $this->recordEvent($tenant, $record, 'quality.checklist.status_changed', $before, ['status' => $record->status]);
        });

        return back()->with('status', 'Hasil checklist diperbarui.');
    }

    public function storeFinding(
        Request $request,
        string $institution,
        int $audit,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.findings.manage');
        $session = $this->visibleAudits($tenant, $user)->whereKey($audit)->firstOrFail();
        abort_unless(in_array($session->status, ['planned', 'in_progress'], true), 422);
        $data = $request->validate([
            'checklist_item_id' => ['nullable', 'integer'],
            'finding_code' => ['nullable', 'string', 'max:80'],
            'severity' => ['required', Rule::in(AuditFinding::SEVERITIES)],
            'description' => ['required', 'string', 'max:10000'],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'recommendation' => ['nullable', 'string', 'max:10000'],
            'owner_user_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
        ]);
        if (! empty($data['checklist_item_id'])) {
            AuditChecklistItem::query()
                ->where('institution_id', $tenant->getKey())
                ->where('audit_session_id', $session->getKey())
                ->whereKey($data['checklist_item_id'])
                ->firstOrFail();
        }
        $owner = $this->validatedMember($tenant, $data['owner_user_id'] ?? null, $session->unit);
        DB::transaction(function () use ($tenant, $session, $owner, $data): void {
            $finding = AuditFinding::query()->create([
                'institution_id' => $tenant->getKey(),
                'audit_session_id' => $session->getKey(),
                'checklist_item_id' => $data['checklist_item_id'] ?? null,
                'finding_code' => $data['finding_code'] ?? null,
                'severity' => $data['severity'],
                'description' => $data['description'],
                'root_cause' => $data['root_cause'] ?? null,
                'recommendation' => $data['recommendation'] ?? null,
                'owner_user_id' => $owner?->getKey(),
                'due_date' => $data['due_date'] ?? null,
                'status' => 'open',
            ]);
            $this->recordEvent($tenant, $finding, 'quality.finding.created', [], ['status' => 'open']);
        });

        return back()->with('status', 'Temuan audit dibuat.');
    }

    public function storeFindingEvidence(
        Request $request,
        string $institution,
        int $finding,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.findings.manage');
        $record = $this->visibleFindings($tenant, $user)->whereKey($finding)->firstOrFail();
        $this->authorizePermission($request, $tenant, 'documents.view');
        $data = $request->validate([
            'evidence_version' => ['required', 'string', 'regex:/^[0-9]+:[0-9]+$/'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        [$documentId, $versionId] = array_map('intval', explode(':', $data['evidence_version']));
        [$document, $version] = $this->validatedEvidenceDocument($tenant, $user, $documentId, $versionId);
        DB::transaction(function () use ($tenant, $user, $record, $document, $version, $data): void {
            AuditFindingEvidence::query()->create([
                'institution_id' => $tenant->getKey(),
                'finding_id' => $record->getKey(),
                'document_id' => $document->getKey(),
                'document_version_id' => $version->getKey(),
                'linked_by' => $user->getKey(),
                'note' => $data['note'] ?? null,
            ]);
            $this->recordEvent($tenant, $record, 'quality.finding.evidence_added', [], [
                'finding_id' => $record->getKey(),
                'related_id' => $document->getKey(),
            ]);
        });

        return back()->with('status', 'Versi dokumen ditautkan ke temuan.');
    }

    public function storeCorrectiveAction(
        Request $request,
        string $institution,
        int $finding,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.actions.manage');
        $record = $this->visibleFindings($tenant, $user)->whereKey($finding)->firstOrFail();
        abort_unless($record->status !== 'closed', 422, 'Temuan sudah ditutup.');
        $data = $request->validate([
            'action_plan' => ['required', 'string', 'max:10000'],
            'owner_user_id' => ['required', 'integer'],
            'due_date' => ['required', 'date'],
        ]);
        $owner = $this->validatedMember($tenant, $data['owner_user_id'], $record->auditSession->unit);
        abort_unless($owner instanceof User, 422);
        $oldStatus = $record->status;
        DB::transaction(function () use ($tenant, $record, $owner, $data, $oldStatus): void {
            $action = CorrectiveAction::query()->create([
                'institution_id' => $tenant->getKey(),
                'finding_id' => $record->getKey(),
                'action_plan' => $data['action_plan'],
                'owner_user_id' => $owner->getKey(),
                'due_date' => $data['due_date'],
                'status' => 'planned',
            ]);
            $this->recordEvent($tenant, $action, 'quality.action.created', [], ['status' => 'planned']);
            $record->status = 'action_planned';
            $record->save();
            if ($oldStatus !== $record->status) {
                $this->recordEvent($tenant, $record, 'quality.finding.status_changed', ['status' => $oldStatus], ['status' => $record->status]);
            }
        });

        return back()->with('status', 'Rencana tindak lanjut dibuat.');
    }

    public function updateCorrectiveActionStatus(
        Request $request,
        string $institution,
        int $action,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.actions.manage');
        $record = $this->visibleActions($tenant, $user)->whereKey($action)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(['in_progress', 'pending_verification', 'closed'])]]);
        $allowed = [
            'planned' => ['in_progress'],
            'in_progress' => ['pending_verification', 'closed'],
            'pending_verification' => [],
            'closed' => [],
            'rejected' => ['in_progress'],
        ];
        abort_unless(in_array($data['status'], $allowed[$record->status] ?? [], true), 422, 'Transisi status tindakan tidak diizinkan.');
        $policy = $this->policyFor($tenant);
        if ($data['status'] === 'closed') {
            abort_if(
                $policy->require_effectiveness_verification || $policy->require_independent_verifier,
                422,
                'Kebijakan institusi mensyaratkan verifikasi efektivitas sebelum penutupan.',
            );
        }
        $finding = $record->finding()->firstOrFail();
        $before = ['status' => $record->status];
        DB::transaction(function () use ($tenant, $record, $finding, $data, $before): void {
            $record->status = $data['status'];
            if ($record->status === 'closed') {
                $record->closed_at = now();
                $record->effectiveness_status = 'not_required';
            }
            $record->save();
            $this->recordEvent($tenant, $record, 'quality.action.status_changed', $before, ['status' => $record->status]);
            $this->syncFindingStatus($tenant, $finding);
        });

        return back()->with('status', 'Status tindakan diperbarui.');
    }

    public function verifyCorrectiveAction(
        Request $request,
        string $institution,
        int $action,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $tenant = $tenantContext->institution();
        $user = $this->authorizePermission($request, $tenant, 'quality.actions.verify');
        $record = $this->visibleActions($tenant, $user)->whereKey($action)->firstOrFail();
        abort_unless($record->status === 'pending_verification', 422, 'Tindakan belum menunggu verifikasi.');
        $data = $request->validate([
            'effectiveness_status' => ['required', Rule::in(['effective', 'ineffective'])],
            'verification_notes' => ['required', 'string', 'max:10000'],
        ]);
        $policy = $this->policyFor($tenant);
        if ($policy->require_independent_verifier) {
            abort_unless((int) $record->owner_user_id !== (int) $user->getKey(), 422, 'Verifikasi harus dilakukan oleh pengguna selain PIC tindakan.');
        }
        $finding = $record->finding()->firstOrFail();
        $before = ['status' => $record->status];
        DB::transaction(function () use ($tenant, $user, $record, $finding, $data, $before): void {
            $effective = $data['effectiveness_status'] === 'effective';
            $record->effectiveness_status = $data['effectiveness_status'];
            $record->verification_notes = $data['verification_notes'];
            $record->verified_by = $user->getKey();
            $record->verified_at = now();
            $record->status = $effective ? 'closed' : 'in_progress';
            $record->closed_at = $effective ? now() : null;
            $record->save();
            $this->recordEvent($tenant, $record, 'quality.action.verified', $before, [
                'status' => $record->status,
                'effectiveness_status' => $record->effectiveness_status,
            ]);
            $this->syncFindingStatus($tenant, $finding);
        });

        return back()->with(
            'status',
            $data['effectiveness_status'] === 'effective'
                ? 'Efektivitas terverifikasi dan tindakan ditutup.'
                : 'Tindakan dikembalikan ke status berjalan untuk perbaikan lanjutan.',
        );
    }

    public function updatePolicy(Request $request, string $institution, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->institution();
        $this->authorizePermission($request, $tenant, 'quality.policies.manage');
        $data = $request->validate([
            'require_effectiveness_verification' => ['nullable', 'boolean'],
            'require_independent_verifier' => ['nullable', 'boolean'],
            'activity_completion_requires_approval' => ['nullable', 'boolean'],
        ]);
        $requireVerification = (bool) ($data['require_effectiveness_verification'] ?? false);
        $requireIndependent = (bool) ($data['require_independent_verifier'] ?? false);
        $requireActivityApproval = (bool) ($data['activity_completion_requires_approval'] ?? false);
        abort_if($requireIndependent && ! $requireVerification, 422, 'Verifikator independen hanya dapat diwajibkan bersama verifikasi efektivitas.');
        $policy = $this->policyFor($tenant);
        $before = [
            'require_effectiveness_verification' => $policy->require_effectiveness_verification,
            'require_independent_verifier' => $policy->require_independent_verifier,
            'activity_completion_requires_approval' => $policy->activity_completion_requires_approval,
        ];
        DB::transaction(function () use ($tenant, $policy, $requireVerification, $requireIndependent, $requireActivityApproval, $before): void {
            $policy->require_effectiveness_verification = $requireVerification;
            $policy->require_independent_verifier = $requireIndependent;
            $policy->activity_completion_requires_approval = $requireActivityApproval;
            $policy->save();
            $after = [
                'require_effectiveness_verification' => $requireVerification,
                'require_independent_verifier' => $requireIndependent,
                'activity_completion_requires_approval' => $requireActivityApproval,
            ];
            QualityHistory::query()->create([
                'institution_id' => $tenant->getKey(),
                'entity_type' => 'quality_policy',
                'entity_id' => $policy->getKey(),
                'event' => 'quality.policy.updated',
                'changes_json' => ['before' => $before, 'after' => $after],
                'actor_user_id' => auth()->id(),
                'created_at' => now(),
            ]);
            app(AuditLogger::class)->record('quality.policy.updated', $policy, $tenant, $before, $after);
        });

        return back()->with('status', 'Kebijakan verifikasi RTL/CAPA diperbarui.');
    }

    private function authorizePermission(Request $request, Institution $tenant, string $permission): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasPermission($permission, $tenant), 403);

        return $user;
    }

    private function authorizeAuditParticipant(User $user, Institution $tenant, AuditSession $audit): void
    {
        if ($user->hasPermission('quality.audits.manage', $tenant)) {
            return;
        }
        abort_unless($user->hasPermission('quality.audits.perform', $tenant), 403);
        abort_unless(
            DB::table('audit_session_auditors')
                ->where('institution_id', $tenant->getKey())
                ->where('audit_session_id', $audit->getKey())
                ->where('user_id', $user->getKey())
                ->exists(),
            404,
        );
    }

    private function validatedUnit(Institution $tenant, User $user, mixed $unitId): ?OrganizationalUnit
    {
        if ($unitId === null || $unitId === '') {
            return null;
        }
        $unit = $tenant->organizationalUnits()
            ->where('status', OrganizationalUnit::STATUS_ACTIVE)
            ->whereKey($unitId)
            ->firstOrFail();
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

    private function validatedMember(Institution $tenant, mixed $userId, ?OrganizationalUnit $unit): ?User
    {
        if ($userId === null || $userId === '') {
            return null;
        }
        $member = User::query()
            ->whereHas('institutionMemberships', fn (Builder $query) => $query
                ->where('institution_id', $tenant->getKey())
                ->where('status', 'active'))
            ->whereKey($userId)
            ->firstOrFail();
        if ($unit !== null) {
            abort_unless($this->userCanBeAssigned($tenant, $member, $unit), 404);
        }

        return $member;
    }

    private function userCanBeAssigned(Institution $tenant, User $member, ?OrganizationalUnit $unit): bool
    {
        if ($unit === null) {
            return true;
        }

        return $member->canAccessUnit($tenant, $unit);
    }

    /** @return Builder<QualityStandard> */
    private function visibleStandards(Institution $tenant, User $user): Builder
    {
        return $this->scopeUnit(
            QualityStandard::query()->where('institution_id', $tenant->getKey()),
            'unit_id',
            $tenant,
            $user,
        );
    }

    /** @return Builder<PpeppActivity> */
    private function visibleActivities(Institution $tenant, User $user): Builder
    {
        return $this->scopeUnit(
            PpeppActivity::query()->where('institution_id', $tenant->getKey()),
            'unit_id',
            $tenant,
            $user,
        );
    }

    /** @return Builder<AuditSession> */
    private function visibleAudits(Institution $tenant, User $user): Builder
    {
        return $this->scopeUnit(
            AuditSession::query()->where('institution_id', $tenant->getKey()),
            'unit_id',
            $tenant,
            $user,
        );
    }

    /** @return Builder<AuditFinding> */
    private function visibleFindings(Institution $tenant, User $user): Builder
    {
        return AuditFinding::query()
            ->where('institution_id', $tenant->getKey())
            ->whereHas('auditSession', fn (Builder $query) => $this->scopeUnit($query, 'unit_id', $tenant, $user));
    }

    /** @return Builder<CorrectiveAction> */
    private function visibleActions(Institution $tenant, User $user): Builder
    {
        return CorrectiveAction::query()
            ->where('institution_id', $tenant->getKey())
            ->whereHas('finding.auditSession', fn (Builder $query) => $this->scopeUnit($query, 'unit_id', $tenant, $user));
    }

    /** @return Builder<QualityHistory> */
    private function visibleHistories(Institution $tenant, User $user): Builder
    {
        return QualityHistory::query()
            ->where('institution_id', $tenant->getKey())
            ->where(function (Builder $query) use ($tenant, $user): void {
                $query->where('entity_type', 'quality_policy')
                    ->orWhere(function (Builder $scope) use ($tenant, $user): void {
                        $scope->where('entity_type', 'standard')
                            ->whereIn('entity_id', $this->visibleStandards($tenant, $user)->select('id'));
                    })
                    ->orWhere(function (Builder $scope) use ($tenant, $user): void {
                        $indicators = QualityIndicator::query()
                            ->where('institution_id', $tenant->getKey())
                            ->whereHas('standard', fn (Builder $query) => $this->scopeUnit($query, 'unit_id', $tenant, $user));
                        $scope->where('entity_type', 'indicator')->whereIn('entity_id', $indicators->select('id'));
                    })
                    ->orWhere(function (Builder $scope) use ($tenant, $user): void {
                        $scope->where('entity_type', 'activity')
                            ->whereIn('entity_id', $this->visibleActivities($tenant, $user)->select('id'));
                    })
                    ->orWhere(function (Builder $scope) use ($tenant, $user): void {
                        $scope->where('entity_type', 'audit_session')
                            ->whereIn('entity_id', $this->visibleAudits($tenant, $user)->select('id'));
                    })
                    ->orWhere(function (Builder $scope) use ($tenant, $user): void {
                        $items = AuditChecklistItem::query()
                            ->where('institution_id', $tenant->getKey())
                            ->whereHas('auditSession', fn (Builder $query) => $this->scopeUnit($query, 'unit_id', $tenant, $user));
                        $scope->where('entity_type', 'checklist_item')->whereIn('entity_id', $items->select('id'));
                    })
                    ->orWhere(function (Builder $scope) use ($tenant, $user): void {
                        $scope->where('entity_type', 'finding')
                            ->whereIn('entity_id', $this->visibleFindings($tenant, $user)->select('id'));
                    })
                    ->orWhere(function (Builder $scope) use ($tenant, $user): void {
                        $scope->where('entity_type', 'corrective_action')
                            ->whereIn('entity_id', $this->visibleActions($tenant, $user)->select('id'));
                    });
            });
    }

    /** @return Builder<Document> */
    private function visibleDocuments(Institution $tenant, User $user): Builder
    {
        $units = $user->accessibleUnitIds($tenant);

        return Document::query()
            ->where('institution_id', $tenant->getKey())
            ->where('status', Document::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($units): void {
                $query->whereNull('unit_id');
                if ($units !== []) {
                    $query->orWhereIn('unit_id', $units);
                }
            })
            ->where(function (Builder $query) use ($user, $tenant, $units): void {
                $query->where('access_level', Document::ACCESS_INSTITUTION);
                if ($units !== []) {
                    $query->orWhere(function (Builder $unitQuery) use ($units): void {
                        $unitQuery->where('access_level', Document::ACCESS_UNIT)->whereIn('unit_id', $units);
                    });
                }
                $query->orWhere(function (Builder $privateQuery) use ($user, $tenant): void {
                    $privateQuery->where('access_level', Document::ACCESS_PRIVATE)
                        ->where(function (Builder $ownerQuery) use ($user, $tenant): void {
                            $ownerQuery->where('owner_user_id', $user->getKey());
                            if ($user->hasPermission('documents.manage', $tenant)) {
                                $ownerQuery->orWhereNotNull('owner_user_id');
                            }
                        });
                });
            });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopeUnit(Builder $query, string $column, Institution $tenant, User $user): Builder
    {
        $unitIds = $user->accessibleUnitIds($tenant);
        $query->where(function (Builder $scope) use ($column, $unitIds): void {
            $scope->whereNull($column);
            if ($unitIds !== []) {
                $scope->orWhereIn($column, $unitIds);
            }
        });

        return $query;
    }

    private function accessibleUnits(Institution $tenant, User $user)
    {
        return $tenant->organizationalUnits()
            ->where('status', OrganizationalUnit::STATUS_ACTIVE)
            ->whereIn('id', $user->accessibleUnitIds($tenant))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{0: Document, 1: DocumentVersion}
     */
    private function validatedEvidenceDocument(Institution $tenant, User $user, mixed $documentId, mixed $versionId): array
    {
        $document = $this->visibleDocuments($tenant, $user)->whereKey($documentId)->firstOrFail();
        $version = DocumentVersion::query()
            ->where('institution_id', $tenant->getKey())
            ->where('document_id', $document->getKey())
            ->whereKey($versionId)
            ->firstOrFail();

        return [$document, $version];
    }

    private function policyFor(Institution $tenant): QualityPolicy
    {
        $policy = QualityPolicy::query()->where('institution_id', $tenant->getKey())->first();
        if ($policy !== null) {
            return $policy;
        }
        $policy = new QualityPolicy;
        $policy->institution_id = $tenant->getKey();
        $policy->require_effectiveness_verification = true;
        $policy->require_independent_verifier = false;

        return $policy;
    }

    private function syncFindingStatus(Institution $tenant, AuditFinding $finding): void
    {
        $beforeStatus = $finding->status;
        $actions = CorrectiveAction::query()
            ->where('institution_id', $tenant->getKey())
            ->where('finding_id', $finding->getKey())
            ->get(['status']);
        if ($actions->isEmpty()) {
            return;
        }
        if ($actions->every(fn (CorrectiveAction $action): bool => $action->status === 'closed')) {
            $finding->status = 'closed';
        } elseif ($actions->contains(fn (CorrectiveAction $action): bool => $action->status === 'pending_verification')) {
            $finding->status = 'pending_verification';
        } elseif ($actions->contains(fn (CorrectiveAction $action): bool => $action->status === 'in_progress')) {
            $finding->status = 'in_progress';
        } else {
            $finding->status = 'action_planned';
        }
        if ($finding->status !== $beforeStatus) {
            $finding->save();
            $this->recordEvent(
                $tenant,
                $finding,
                'quality.finding.status_changed',
                ['status' => $beforeStatus],
                ['status' => $finding->status],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordEvent(
        Institution $tenant,
        Model $entity,
        string $event,
        array $before,
        array $after,
    ): void {
        $type = match (true) {
            $entity instanceof QualityStandard => 'standard',
            $entity instanceof QualityIndicator => 'indicator',
            $entity instanceof PpeppActivity => 'activity',
            $entity instanceof AuditSession => 'audit_session',
            $entity instanceof AuditChecklistItem => 'checklist_item',
            $entity instanceof AuditFinding => 'finding',
            $entity instanceof CorrectiveAction => 'corrective_action',
            default => 'quality_record',
        };
        QualityHistory::query()->create([
            'institution_id' => $tenant->getKey(),
            'entity_type' => $type,
            'entity_id' => $entity->getKey(),
            'event' => $event,
            'changes_json' => ['before' => $before, 'after' => $after],
            'actor_user_id' => auth()->id(),
            'created_at' => now(),
        ]);
        app(AuditLogger::class)->record($event, $entity, $tenant, $before, $after);
    }
}
