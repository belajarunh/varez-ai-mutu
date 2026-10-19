<?php

use App\Http\Controllers\AccreditationProjectController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\OrganizationalUnitController;
use App\Http\Controllers\PerformanceImportController;
use App\Http\Controllers\QualityController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('portal.home');
})->name('portal.home');

Route::middleware(['auth', 'verified', 'tenant.context'])
    ->prefix('institutions/{institution}')
    ->name('institutions.')
    ->group(function (): void {
        Route::get('/', [InstitutionController::class, 'show'])->name('show');
        Route::patch('/', [InstitutionController::class, 'update'])->name('update');

        Route::get('/units', [OrganizationalUnitController::class, 'index'])->name('units.index');
        Route::post('/units', [OrganizationalUnitController::class, 'store'])->name('units.store');
        Route::get('/units/{unit}', [OrganizationalUnitController::class, 'show'])->name('units.show');
        Route::patch('/units/{unit}', [OrganizationalUnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [OrganizationalUnitController::class, 'archive'])->name('units.archive');

        Route::get('/members', [MembershipController::class, 'index'])->name('members.index');
        Route::patch('/members/{membership}', [MembershipController::class, 'update'])->name('members.update');
        Route::post('/invitations', [InvitationController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('invitations.store');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/projects', [AccreditationProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/create', [AccreditationProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [AccreditationProjectController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('projects.store');
        Route::get('/projects/{project}', [AccreditationProjectController::class, 'show'])->name('projects.show');
        Route::patch('/projects/{project}/indicators/{indicator}', [AccreditationProjectController::class, 'updateIndicator'])
            ->name('projects.indicators.update');

        Route::get('/performance', [PerformanceImportController::class, 'index'])->name('performance.index');
        Route::get('/performance/imports/create', [PerformanceImportController::class, 'create'])
            ->name('performance.imports.create');
        Route::post('/performance/periods', [PerformanceImportController::class, 'createPeriod'])
            ->middleware('throttle:20,1')
            ->name('performance.periods.store');
        Route::post('/performance/imports', [PerformanceImportController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('performance.imports.store');
        Route::get('/performance/imports/{batch}/mapping', [PerformanceImportController::class, 'mapping'])
            ->whereNumber('batch')
            ->name('performance.imports.mapping');
        Route::post('/performance/imports/{batch}/validate', [PerformanceImportController::class, 'startValidation'])
            ->whereNumber('batch')
            ->middleware('throttle:20,1')
            ->name('performance.imports.validate');
        Route::get('/performance/imports/{batch}', [PerformanceImportController::class, 'show'])
            ->whereNumber('batch')
            ->name('performance.imports.show');
        Route::post('/performance/imports/{batch}/confirm', [PerformanceImportController::class, 'confirm'])
            ->whereNumber('batch')
            ->middleware('throttle:20,1')
            ->name('performance.imports.confirm');
        Route::get('/performance/imports/{batch}/errors.csv', [PerformanceImportController::class, 'errorReport'])
            ->whereNumber('batch')
            ->name('performance.imports.errors');
        Route::get('/performance/schemas/{schema}/template/{format}', [PerformanceImportController::class, 'template'])
            ->whereNumber('schema')
            ->whereIn('format', ['csv', 'xlsx'])
            ->name('performance.schemas.template');

        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
        Route::post('/documents', [DocumentController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('documents.store');
        Route::get('/documents/categories', [DocumentController::class, 'categories'])->name('documents.categories');
        Route::post('/documents/categories', [DocumentController::class, 'storeCategory'])
            ->middleware('throttle:20,1')
            ->name('documents.categories.store');
        Route::patch('/documents/categories/{category}', [DocumentController::class, 'updateCategory'])
            ->whereNumber('category')
            ->name('documents.categories.update');
        Route::get('/documents/{document}', [DocumentController::class, 'show'])
            ->whereNumber('document')
            ->name('documents.show');
        Route::post('/documents/{document}/versions', [DocumentController::class, 'storeVersion'])
            ->whereNumber('document')
            ->middleware('throttle:10,1')
            ->name('documents.versions.store');
        Route::patch('/documents/{document}', [DocumentController::class, 'update'])
            ->whereNumber('document')
            ->name('documents.update');
        Route::patch('/documents/{document}/verification', [DocumentController::class, 'verify'])
            ->whereNumber('document')
            ->name('documents.verify');
        Route::post('/documents/{document}/relations', [DocumentController::class, 'storeRelation'])
            ->whereNumber('document')
            ->name('documents.relations.store');
        Route::delete('/documents/{document}/relations/{relation}', [DocumentController::class, 'deleteRelation'])
            ->whereNumber('document')
            ->whereNumber('relation')
            ->name('documents.relations.delete');
        Route::get('/documents/{document}/versions/{version}/download', [DocumentController::class, 'download'])
            ->whereNumber('document')
            ->whereNumber('version')
            ->name('documents.versions.download');

        Route::get('/quality', [QualityController::class, 'index'])->name('quality.index');
        Route::post('/quality/policies', [QualityController::class, 'updatePolicy'])->name('quality.policies.update');
        Route::post('/quality/standards', [QualityController::class, 'storeStandard'])->name('quality.standards.store');
        Route::patch('/quality/standards/{standard}', [QualityController::class, 'updateStandard'])
            ->whereNumber('standard')
            ->name('quality.standards.update');
        Route::post('/quality/standards/{standard}/indicators', [QualityController::class, 'storeIndicator'])
            ->whereNumber('standard')
            ->name('quality.indicators.store');
        Route::patch('/quality/standards/{standard}/indicators/{indicator}', [QualityController::class, 'updateIndicator'])
            ->whereNumber('standard')
            ->whereNumber('indicator')
            ->name('quality.indicators.update');
        Route::patch('/quality/standards/{standard}/status', [QualityController::class, 'updateStandardStatus'])
            ->whereNumber('standard')
            ->name('quality.standards.status');
        Route::post('/quality/activities', [QualityController::class, 'storeActivity'])->name('quality.activities.store');
        Route::patch('/quality/activities/{activity}/status', [QualityController::class, 'updateActivityStatus'])
            ->whereNumber('activity')
            ->name('quality.activities.status');
        Route::post('/quality/activities/{activity}/approval', [QualityController::class, 'reviewActivity'])
            ->whereNumber('activity')
            ->name('quality.activities.approval');
        Route::post('/quality/activities/{activity}/evidence', [QualityController::class, 'storeActivityEvidence'])
            ->whereNumber('activity')
            ->name('quality.activities.evidence.store');
        Route::post('/quality/audits', [QualityController::class, 'storeAudit'])->name('quality.audits.store');
        Route::patch('/quality/audits/{audit}/status', [QualityController::class, 'updateAuditStatus'])
            ->whereNumber('audit')
            ->name('quality.audits.status');
        Route::post('/quality/audits/{audit}/checklist', [QualityController::class, 'storeChecklistItem'])
            ->whereNumber('audit')
            ->name('quality.checklist.store');
        Route::patch('/quality/checklist/{item}', [QualityController::class, 'updateChecklistItem'])
            ->whereNumber('item')
            ->name('quality.checklist.update');
        Route::post('/quality/audits/{audit}/findings', [QualityController::class, 'storeFinding'])
            ->whereNumber('audit')
            ->name('quality.findings.store');
        Route::post('/quality/findings/{finding}/evidence', [QualityController::class, 'storeFindingEvidence'])
            ->whereNumber('finding')
            ->name('quality.findings.evidence.store');
        Route::post('/quality/findings/{finding}/actions', [QualityController::class, 'storeCorrectiveAction'])
            ->whereNumber('finding')
            ->name('quality.actions.store');
        Route::patch('/quality/actions/{action}/status', [QualityController::class, 'updateCorrectiveActionStatus'])
            ->whereNumber('action')
            ->name('quality.actions.status');
        Route::post('/quality/actions/{action}/verify', [QualityController::class, 'verifyCorrectiveAction'])
            ->whereNumber('action')
            ->name('quality.actions.verify');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function (): void {
        Route::post('/institutions', [InstitutionController::class, 'storePlatform'])
            ->name('institutions.store');
        Route::patch('/institutions/{institution}/status', [InstitutionController::class, 'updatePlatformStatus'])
            ->name('institutions.status');
    });

Route::post('/invitations/accept', [InvitationController::class, 'accept'])
    ->middleware(['auth', 'verified', 'throttle:10,1'])
    ->name('invitations.accept');
