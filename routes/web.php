<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CredentialController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Employee\DeclarationController;
use App\Http\Controllers\Employee\HistoryController;
use App\Http\Controllers\Manager\TeamHistoryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->prefix('employee')
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

        Route::get('/language', function () {
            return Inertia::render('Employee/LanguageSelection', [
                'declarations' => [],
            ]);
        })->name('language');

    });

Route::middleware(['auth','manager'])->prefix('manager')
    ->name('manager.')
    ->group(function () {

    Route::get(
        '/team-history',
        [TeamHistoryController::class, 'index']
    )->name('team-history');

    Route::get('/manager/review', function () {
        return Inertia::render('Manager/DeclarationReview');
    })->name('review');
});


Route::middleware(['auth'])->prefix('admin')
->name('admin.')
->group(function () {
    
    Route::get(
        '/report',
        [ReportController::class, 'index']
    )
        ->name('report');

    Route::get('/roles', [RoleController::class, 'index'])->middleware(['permission:role.view'])->name('roles');

    Route::get(
            '/dashboard',
            [DashboardController::class, 'index']
        )->name('dashboard');


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
});

Route::fallback(function () {

    if (Auth::check()) {
        return redirect()->route('employee.history');
    }

    return redirect()->route('login');
});

require __DIR__.'/auth.php';