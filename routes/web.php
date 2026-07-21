<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CredentialController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Employee\DeclarationController;
use App\Http\Controllers\Employee\DeclarationAttachmentController;
use App\Http\Controllers\Employee\HistoryController;
use App\Http\Controllers\Manager\TeamHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('dbauth', [SsoController::class, 'dbauth']);

Route::middleware(['auth:web,non_employee'])->prefix('employee')
    ->name('employee.')
    ->group(function () {

        Route::get( '/', HistoryController::class )->name('history');

        Route::get(
            '/declaration',
            [DeclarationController::class, 'create']
        )->name('declarations.create');

        Route::post(
            '/declarations/draft',
            [DeclarationController::class, 'saveDraft']
        )->name('declarations.draft');

        Route::post(
            '/declarations/submit',
            [DeclarationController::class, 'submit']
        )->name('declarations.submit');

        Route::get(
            '/declarations/{declaration}/pdf',
            [DeclarationController::class, 'downloadPdf']
        )->name('declarations.pdf');

        // 2025 historical import: supporting-document upload/download.
        Route::post(
            '/declarations/{declaration}/attachment',
            [DeclarationAttachmentController::class, 'store']
        )->name('declarations.attachment.store');

        Route::get(
            '/declarations/{declaration}/attachment',
            [DeclarationAttachmentController::class, 'show']
        )->name('declarations.attachment.show');

    });

Route::middleware(['auth:web','manager'])->prefix('manager')
    ->name('manager.')
    ->group(function () {

    Route::get(
        '/team-history',
        [TeamHistoryController::class, 'index']
    )->name('team-history');

    Route::get('/manager/review', function () {
        return Inertia::render('Manager/DeclarationReview');
    })->name('review');

    Route::get('/team-history/excel', [TeamHistoryController::class, 'exportExcel'])
        ->name('team-history.excel');
});

Route::middleware(['auth:web'])->prefix('admin')
->name('admin.')
->group(function () {
    
    Route::get(
        '/report',
        [ReportController::class, 'index']
    )
        ->name('report');

    Route::get('/roles', [RoleController::class, 'index'])->middleware(['permission:role.view'])->name('roles');

    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware(['permission:role.create'])
        ->name('roles.store');

    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware(['permission:role.update'])
        ->name('roles.update');

    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware(['permission:role.delete'])
        ->name('roles.destroy');

    Route::get(
            '/dashboard',
            [DashboardController::class, 'index']
        )->name('dashboard');

    Route::post(
        '/dashboard/pdf',
        [DashboardController::class, 'downloadDashboardPdf']
    )->name('dashboard.pdf');

    Route::get('/dashboard/excel', [DashboardController::class, 'exportExcel'])
        ->name('dashboard.excel');


    Route::get(
        '/credentials',
        [CredentialController::class, 'index']
    )->name('credentials');

    Route::post(
        '/credentials',
        [CredentialController::class, 'store']
    )->name('credentials.store');

    Route::put(
        '/credentials/{user}',
        [CredentialController::class, 'update']
    )->name('credentials.update');

    Route::delete(
        '/credentials/{user}',
        [CredentialController::class, 'destroy']
    )->name('credentials.destroy');

    Route::post(
        '/roles/{role}/assign-users',
        [RoleController::class, 'assignUsers']
    )->name('roles.assign-users');

    Route::post(
        '/report/export',
        [ReportController::class, 'export']
    )->name('report.export');

    Route::get(
        '/report/export/{reportDownload}/status',
        [ReportController::class, 'exportStatus']
    )->name('report.export.status');

    Route::get(
        '/report/export/{reportDownload}/download',
        [ReportController::class, 'exportDownload']
    )->name('report.export.download');

    Route::get(
        '/report/declaration/{declaration}/pdf',
        [ReportController::class, 'exportPdf']
    )->name('report.declaration.pdf');

    Route::get(
        '/report/declaration/{declaration}/attachment',
        [ReportController::class, 'attachment']
    )->name('report.declaration.attachment');

    Route::post(
        '/credentials/{user}/reset-password',
        [CredentialController::class, 'resetPassword']
    )->name('credentials.reset-password');

    Route::post(
        '/credentials/import',
        [CredentialController::class, 'import']
    )->name('credentials.import');

    Route::get(
        '/credentials/import/error',
        [CredentialController::class, 'downloadImportError']
    )->name('credentials.import.error');

    Route::get(
        '/credentials/template',
        [CredentialController::class, 'downloadTemplate']
    )->name('credentials.template');
});

Route::fallback(function () {

    if (Auth::check()) {
        return redirect()->route('employee.history');
    }

    return redirect()->route('login');
});

require __DIR__.'/auth.php';