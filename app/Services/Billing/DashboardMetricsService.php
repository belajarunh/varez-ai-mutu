<?php

namespace App\Services\Billing;

use App\Models\AccreditationProject;
use App\Models\Document;
use App\Models\Institution;
use App\Models\User;
use App\Models\UsageLedger;

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
        $aiUsage = (float) UsageLedger::query()
            ->where('institution_id', $institution->getKey())
            ->where('usage_type', 'ai_tokens')
            ->sum('quantity');
        $storage = (float) UsageLedger::query()
            ->where('institution_id', $institution->getKey())
            ->where('usage_type', 'storage_mb')
            ->sum('quantity');

        return [
            'period' => now()->format('Y-m'),
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
        ];
    }
}
