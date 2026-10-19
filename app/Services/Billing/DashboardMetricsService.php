<?php

namespace App\Services\Billing;

use App\Models\AccreditationProject;
use App\Models\Document;
use App\Models\Institution;
use App\Models\UsageLedger;
use App\Models\User;

class DashboardMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function forInstitution(Institution $institution, User $user): array
    {
        $canView = $user->hasPlatformRole('platform.super_admin')
            || $user->hasPermission('dashboard.view', $institution)
            || $user->hasPermission('quality.view', $institution)
            || $user->hasPermission('institutions.view', $institution)
            || $user->hasPermission('projects.view', $institution);

        if (! $canView) {
            return ['status' => 'forbidden', 'message' => 'User does not have dashboard access.'];
        }

        $projects = AccreditationProject::query()->where('institution_id', $institution->getKey())->count();
        $documents = Document::query()->where('institution_id', $institution->getKey())->count();
        $period = now()->format('Y-m');
        $aiUsage = (float) UsageLedger::query()
            ->where('institution_id', $institution->getKey())
            ->where('usage_type', 'ai_tokens')
            ->where('period_key', $period)
            ->sum('quantity');
        $storage = (float) UsageLedger::query()
            ->where('institution_id', $institution->getKey())
            ->where('usage_type', 'storage_mb')
            ->where('period_key', $period)
            ->sum('quantity');

        return [
            'period' => $period,
            'institution_id' => $institution->getKey(),
            'project_count' => $projects,
            'document_count' => $documents,
            'ai_usage_tokens' => $aiUsage,
            'storage_mb' => $storage,
            'health' => [
                'projects' => $projects,
                'documents' => $documents,
                'ai_usage_is_tracked' => true,
                'storage_is_tracked' => true,
            ],
            'summary' => [
                'progress_indicator' => 'based_on_project_status',
                'data_gaps' => 'review_required',
                'deadline_window' => 'current_period',
                'source_of_truth' => 'institution_scope',
            ],
            'metric_definitions' => [
                'project_count' => 'Total accreditation projects currently in this institution.',
                'document_count' => 'Total documents currently in this institution.',
                'ai_usage_tokens' => 'Sum of ai_tokens usage ledger entries for the displayed month.',
                'storage_mb' => 'Sum of storage_mb usage ledger entries for the displayed month.',
            ],
            'metric_sources' => [
                'project_count' => 'accreditation_projects.institution_id',
                'document_count' => 'documents.institution_id',
                'ai_usage_tokens' => 'usage_ledgers where usage_type=ai_tokens and period_key matches period.',
                'storage_mb' => 'usage_ledgers where usage_type=storage_mb and period_key matches period.',
            ],
        ];
    }
}
