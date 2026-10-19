<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\OrganizationalUnitController;
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
