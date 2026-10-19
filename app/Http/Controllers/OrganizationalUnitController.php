<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\OrganizationalUnit;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrganizationalUnitController extends Controller
{
    public function index(TenantContext $tenantContext): JsonResponse
    {
        $institution = $tenantContext->institution();
        Gate::authorize('viewAny', [OrganizationalUnit::class, $institution]);

        $user = request()->user();
        $allowedIds = $user->accessibleUnitIds($institution);
        $units = $institution->organizationalUnits()
            ->where('status', OrganizationalUnit::STATUS_ACTIVE)
            ->whereIn('id', $allowedIds)
            ->orderBy('name')
            ->get(['id', 'parent_id', 'unit_type', 'name', 'code', 'status']);

        return response()->json(['data' => $units]);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $institution = $tenantContext->institution();
        Gate::authorize('create', [OrganizationalUnit::class, $institution]);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'unit_type' => ['required', 'in:faculty,study_program,school_unit,department,quality_unit,other'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('organizational_units', 'code')->where('institution_id', $institution->getKey()),
            ],
        ]);

        $this->assertParentBelongsToInstitution($institution, $data['parent_id'] ?? null);
        $this->assertUserCanAccessParent($request, $institution, $data['parent_id'] ?? null);

        $unit = DB::transaction(function () use ($institution, $data, $auditLogger): OrganizationalUnit {
            $unit = $institution->organizationalUnits()->create([
                ...$data,
                'status' => OrganizationalUnit::STATUS_ACTIVE,
            ]);

            $auditLogger->record('organizational_unit.created', $unit, $institution, after: $unit->only([
                'name', 'parent_id', 'unit_type', 'code', 'status',
            ]));

            return $unit;
        });

        return response()->json(['data' => $unit], 201);
    }

    public function show(
        string $institution,
        int $unit,
        TenantContext $tenantContext,
    ): JsonResponse {
        $tenant = $tenantContext->institution();
        $record = $this->findScopedUnit($tenant, $unit);
        Gate::authorize('view', $record);

        return response()->json(['data' => $record]);
    }

    public function update(
        Request $request,
        string $institution,
        int $unit,
        TenantContext $tenantContext,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $institution = $tenantContext->institution();
        $record = $this->findScopedUnit($institution, $unit);
        Gate::authorize('update', $record);

        $data = $request->validate([
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'unit_type' => ['sometimes', 'required', 'in:faculty,study_program,school_unit,department,quality_unit,other'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('organizational_units', 'code')
                    ->where('institution_id', $institution->getKey())
                    ->ignore($record->getKey()),
            ],
        ]);

        if (array_key_exists('parent_id', $data)) {
            $this->assertParentBelongsToInstitution($institution, $data['parent_id']);
            $this->assertUserCanAccessParent($request, $institution, $data['parent_id']);
            $this->assertParentDoesNotCreateCycle($record, $data['parent_id']);
        }

        DB::transaction(function () use ($record, $data, $institution, $auditLogger): void {
            $before = $record->only(array_keys($data));
            $record->fill($data)->save();
            $auditLogger->record(
                'organizational_unit.updated',
                $record,
                $institution,
                $before,
                $record->only(array_keys($data)),
            );
        });

        return response()->json(['data' => $record->refresh()]);
    }

    public function archive(
        string $institution,
        int $unit,
        TenantContext $tenantContext,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $institution = $tenantContext->institution();
        $record = $this->findScopedUnit($institution, $unit);
        Gate::authorize('archive', $record);

        if ($record->children()->where('status', OrganizationalUnit::STATUS_ACTIVE)->exists()) {
            abort(409, 'Pindahkan atau arsipkan unit turunannya terlebih dahulu.');
        }

        DB::transaction(function () use ($record, $institution, $auditLogger): void {
            $before = ['status' => $record->status];
            $record->forceFill(['status' => OrganizationalUnit::STATUS_ARCHIVED])->save();
            $auditLogger->record(
                'organizational_unit.archived',
                $record,
                $institution,
                $before,
                ['status' => $record->status],
            );
        });

        return response()->json(['data' => ['id' => $record->getKey(), 'status' => $record->status]]);
    }

    private function findScopedUnit(Institution $institution, int $id): OrganizationalUnit
    {
        return $institution->organizationalUnits()->whereKey($id)->firstOrFail();
    }

    private function assertParentBelongsToInstitution(Institution $institution, ?int $parentId): void
    {
        if ($parentId !== null && ! $institution->organizationalUnits()
            ->whereKey($parentId)
            ->where('status', OrganizationalUnit::STATUS_ACTIVE)
            ->exists()) {
            abort(404);
        }
    }

    private function assertUserCanAccessParent(Request $request, Institution $institution, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = $institution->organizationalUnits()->whereKey($parentId)->firstOrFail();

        if (! $request->user()->canAccessUnit($institution, $parent)) {
            abort(404);
        }
    }

    private function assertParentDoesNotCreateCycle(OrganizationalUnit $unit, ?int $parentId): void
    {
        $visited = [];

        while ($parentId !== null) {
            if ($parentId === $unit->getKey() || in_array($parentId, $visited, true)) {
                abort(422, 'Struktur unit tidak boleh membentuk siklus.');
            }

            $visited[] = $parentId;
            $parentId = $unit->institution->organizationalUnits()
                ->whereKey($parentId)
                ->value('parent_id');
        }
    }
}
