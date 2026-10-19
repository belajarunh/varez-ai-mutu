<?php

use App\Http\Controllers\AccreditationProjectController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\OrganizationalUnitController;
use App\Http\Controllers\PerformanceImportController;
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
