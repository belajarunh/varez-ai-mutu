<?php

namespace App\Services\Projects;

use App\Models\AccreditationProject;
use App\Models\Institution;
use App\Models\InstrumentVersion;
use App\Models\ProjectIndicator;
use App\Models\User;
use App\Services\Instruments\InstrumentSnapshotBuilder;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccreditationProjectCreator
{
    public function __construct(
        private readonly InstrumentSnapshotBuilder $snapshotBuilder,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<int>  $teamUserIds
     */
    public function create(
        Institution $institution,
        User $actor,
        InstrumentVersion $version,
        ?int $unitId,
        string $title,
        string $periodLabel,
        ?string $targetDate,
        int $ownerUserId,
        array $teamUserIds,
    ): AccreditationProject {
        if (! in_array($institution->institution_type, ['higher_education', 'school'], true)) {
            throw ValidationException::withMessages([
                'institution_type' => 'Proyek instrumen hanya tersedia untuk institusi perguruan tinggi atau sekolah yang telah ditentukan.',
            ]);
        }

        $version->loadMissing('instrument.accreditationBody');
        if (
            $version->status !== InstrumentVersion::STATUS_PUBLISHED
            || $version->verification_status !== InstrumentVersion::VERIFICATION_VERIFIED
            || $version->instrument->status !== 'published'
            || $version->instrument->domain_type !== $institution->institution_type
            || $version->instrument->accreditationBody->status !== 'verified'
            || ($version->effective_from !== null && $version->effective_from->gt(today()))
            || ($version->effective_to !== null && $version->effective_to->lt(today()))
        ) {
            throw ValidationException::withMessages([
                'instrument_version_id' => 'Pilih versi instrumen terverifikasi dan berlaku untuk tipe institusi ini.',
            ]);
        }

        $memberIds = array_values(array_unique([...$teamUserIds, $ownerUserId, (int) $actor->getKey()]));
        $activeMemberIds = $institution->memberships()
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->whereIn('user_id', $memberIds)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        sort($memberIds);
        sort($activeMemberIds);
        if ($activeMemberIds !== $memberIds) {
            throw ValidationException::withMessages(['team_user_ids' => 'Semua anggota tim dan pemilik harus memiliki membership aktif pada institusi ini.']);
        }

        if ($unitId !== null && ! $institution->organizationalUnits()->whereKey($unitId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['unit_id' => 'Pilih unit aktif dari institusi ini.']);
        }

        $builtSnapshot = $this->snapshotBuilder->build($version);

        return DB::transaction(function () use (
            $institution,
            $actor,
            $version,
            $unitId,
            $title,
            $periodLabel,
            $targetDate,
            $ownerUserId,
            $memberIds,
            $builtSnapshot,
        ): AccreditationProject {
            $project = AccreditationProject::query()->create([
                'institution_id' => $institution->getKey(),
                'unit_id' => $unitId,
                'instrument_version_id' => $version->getKey(),
                'project_type' => $institution->institution_type,
                'title' => $title,
                'period_label' => $periodLabel,
                'status' => 'planning',
                'target_date' => $targetDate,
                'owner_user_id' => $ownerUserId,
                'created_by' => $actor->getKey(),
                'instrument_snapshot_json' => $builtSnapshot['snapshot'],
                'instrument_snapshot_checksum' => $builtSnapshot['checksum'],
            ]);

            foreach ($memberIds as $memberId) {
                $project->members()->attach($memberId, [
                    'institution_id' => $institution->getKey(),
                    'project_role' => $memberId === $ownerUserId ? 'owner' : ($memberId === $actor->getKey() ? 'creator' : 'member'),
                ]);
            }

            foreach ($builtSnapshot['indicator_nodes'] as $node) {
                ProjectIndicator::query()->create([
                    'institution_id' => $institution->getKey(),
                    'project_id' => $project->getKey(),
                    'source_instrument_node_id' => $node['source_node_id'],
                    'snapshot_node_json' => $node,
                    'status' => 'not_started',
                    'owner_user_id' => $ownerUserId,
                ]);
            }

            $this->auditLogger->record(
                'accreditation_project.created',
                $project,
                $institution,
                after: [
                    'title' => $project->title,
                    'project_type' => $project->project_type,
                    'instrument_version_id' => $project->instrument_version_id,
                    'status' => $project->status,
                ],
            );

            return $project->load(['instrumentVersion.instrument', 'unit', 'owner', 'indicators']);
        });
    }
}
