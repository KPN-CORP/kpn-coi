<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CredentialController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormDashboardController;
use App\Http\Controllers\Admin\FormReportController;
use App\Http\Controllers\Admin\FormTemplateController;
use App\Http\Controllers\Admin\FormTypeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Employee\DeclarationController;
use App\Http\Controllers\Employee\DeclarationAttachmentController;
use App\Http\Controllers\Employee\FormController;
use App\Http\Controllers\Employee\HistoryController;
use App\Http\Controllers\FormImageController;
use App\Http\Controllers\Manager\TeamFormController;
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

        // Forms (Pakta Integritas and other form types): one page per type,
        // addressed by the form type's code, e.g. /employee/forms/integrity-pact.
        Route::get(
            '/forms/{formType}',
            [FormController::class, 'index']
        )->name('forms.show');

        Route::get(
            '/forms/{formType}/read/{template}',
            [FormController::class, 'read']
        )->name('forms.read');

        Route::post(
            '/forms/templates/{template}/draft',
            [FormController::class, 'draft']
        )->name('forms.draft');

        Route::post(
            '/forms/templates/{template}/sign',
            [FormController::class, 'sign']
        )->name('forms.sign');

        Route::get(
            '/forms/declarations/{declaration}/pdf',
            [FormController::class, 'pdf']
        )->name('forms.pdf');

    });

// Images inside form templates (header logo etc.). Readable by any
// signed-in account, since every signer sees the template's header.
Route::middleware(['auth:web,non_employee'])
    ->get('/forms/images/{file}', [FormImageController::class, 'show'])
    ->where('file', '[A-Za-z0-9._-]+')
    ->name('forms.image');

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

    // A manager's team view of one form type -- the "Team Declaration" entry
    // under that form type's menu.
    Route::get('/forms/{formType}', [TeamFormController::class, 'index'])
        ->name('forms.team');
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

    // Link a non-employee to their HRIS record. Writes only the link; their
    // declarations stay where they are and follow them via the prior identity.
    Route::post(
        '/credentials/{user}/convert',
        [CredentialController::class, 'convertToEmployee']
    )->name('credentials.convert');

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

    // Forms: report and dashboard, one form type at a time.
    Route::prefix('forms')->name('forms.')->group(function () {

        Route::get('/dashboard', [FormDashboardController::class, 'index'])
            ->middleware('permission:form_dashboard.view')
            ->name('dashboard');

        Route::get('/report', [FormReportController::class, 'index'])
            ->middleware('permission:form_report.view')
            ->name('report');

        Route::get('/report/export', [FormReportController::class, 'export'])
            ->middleware('permission:form_report.export')
            ->name('report.export');

        Route::get('/report/{declaration}/pdf', [FormReportController::class, 'pdf'])
            ->middleware('permission:form_report.view')
            ->name('report.pdf');
    });

    // Master Settings: form templates and form types.
    Route::prefix('master')->name('master.')->group(function () {

        Route::get('/templates', [FormTemplateController::class, 'index'])
            ->middleware('permission:form_master.view')
            ->name('templates.index');

        Route::get('/templates/create', [FormTemplateController::class, 'create'])
            ->middleware('permission:form_master.create')
            ->name('templates.create');

        Route::post('/templates', [FormTemplateController::class, 'store'])
            ->middleware('permission:form_master.create')
            ->name('templates.store');

        Route::get('/templates/{template}/edit', [FormTemplateController::class, 'edit'])
            ->middleware('permission:form_master.view')
            ->name('templates.edit');

        Route::put('/templates/{template}', [FormTemplateController::class, 'update'])
            ->middleware('permission:form_master.update')
            ->name('templates.update');

        Route::post('/templates/{template}/toggle', [FormTemplateController::class, 'toggle'])
            ->middleware('permission:form_master.update')
            ->name('templates.toggle');

        Route::post('/templates/{template}/duplicate', [FormTemplateController::class, 'duplicate'])
            ->middleware('permission:form_master.create')
            ->name('templates.duplicate');

        Route::delete('/templates/{template}', [FormTemplateController::class, 'destroy'])
            ->middleware('permission:form_master.delete')
            ->name('templates.destroy');

        Route::post('/images', [FormImageController::class, 'store'])
            ->middleware('permission:form_master.create|form_master.update')
            ->name('images.store');

        // Admin pages address a form type by id; the code is for employee URLs.
        Route::get('/form-types', [FormTypeController::class, 'index'])
            ->middleware('permission:form_master.view')
            ->name('form-types.index');

        Route::post('/form-types', [FormTypeController::class, 'store'])
            ->middleware('permission:form_master.create')
            ->name('form-types.store');

        Route::put('/form-types/{formType:id}', [FormTypeController::class, 'update'])
            ->middleware('permission:form_master.update')
            ->name('form-types.update');

        Route::delete('/form-types/{formType:id}', [FormTypeController::class, 'destroy'])
            ->middleware('permission:form_master.delete')
            ->name('form-types.destroy');
    });
});

Route::fallback(function () {

    if (Auth::check()) {
        return redirect()->route('employee.history');
    }

    return redirect()->route('login');
});

require __DIR__.'/auth.php';