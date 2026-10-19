<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(TenantContext $tenantContext): JsonResponse
    {
        $institution = $tenantContext->institution();
        Gate::authorize('viewAuditLogs', $institution);

        $logs = AuditLog::query()
            ->where('institution_id', $institution->getKey())
            ->latest('created_at')
            ->paginate(50, [
                'id',
                'actor_user_id',
                'action',
                'subject_type',
                'subject_id',
                'before_json',
                'after_json',
                'correlation_id',
                'created_at',
            ]);

        return response()->json(['data' => $logs]);
    }
}
