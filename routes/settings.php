<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\EmployeeReferenceDataController;
use App\Http\Controllers\Settings\EmailTestController;
use App\Http\Controllers\Settings\EmployeeGlobalSearchSettingsController;
use App\Http\Controllers\Settings\OperationalMonthSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SalaryRunApprovalStepsController;
use App\Http\Controllers\Settings\TeamPermissionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');

    Route::middleware('role_or_permission:super-admin|settings.access')->group(function () {
        Route::get('settings/permissions-teams', [TeamPermissionController::class, 'index'])->name('settings.permissions-teams.index');
        Route::post('settings/permissions-teams/teams', [TeamPermissionController::class, 'storeTeam'])->name('settings.permissions-teams.store-team');
        Route::put('settings/permissions-teams/teams/{team}', [TeamPermissionController::class, 'updateTeam'])->name('settings.permissions-teams.update-team');
        Route::delete('settings/permissions-teams/teams/{team}', [TeamPermissionController::class, 'deleteTeam'])->name('settings.permissions-teams.delete-team');
        Route::post('settings/permissions-teams/teams/{team}/permissions', [TeamPermissionController::class, 'syncTeamPermissions'])->name('settings.permissions-teams.sync-permissions');
    });

    Route::middleware('role_or_permission:super-admin|settings.access')->group(function () {
        Route::get('settings/employee-reference-data', [EmployeeReferenceDataController::class, 'index'])->name('settings.employee-reference-data.index');
        Route::post('settings/employee-reference-data/nationalities', [EmployeeReferenceDataController::class, 'storeNationality'])->name('settings.employee-reference-data.nationalities.store');
        Route::put('settings/employee-reference-data/nationalities/{nationality}', [EmployeeReferenceDataController::class, 'updateNationality'])->name('settings.employee-reference-data.nationalities.update');
        Route::post('settings/employee-reference-data/countries', [EmployeeReferenceDataController::class, 'storeCountry'])->name('settings.employee-reference-data.countries.store');
        Route::put('settings/employee-reference-data/countries/{country}', [EmployeeReferenceDataController::class, 'updateCountry'])->name('settings.employee-reference-data.countries.update');

        Route::get('settings/email-test', [EmailTestController::class, 'index'])->name('settings.email-test.index');
        Route::post('settings/email-test', [EmailTestController::class, 'send'])->name('settings.email-test.send');

        Route::get('settings/operational-month', [OperationalMonthSettingsController::class, 'edit'])->name('settings.operational-month.edit');
        Route::put('settings/operational-month', [OperationalMonthSettingsController::class, 'update'])->name('settings.operational-month.update');
        Route::get('settings/employee-global-search', [EmployeeGlobalSearchSettingsController::class, 'edit'])->name('settings.employee-global-search.edit');
        Route::put('settings/employee-global-search', [EmployeeGlobalSearchSettingsController::class, 'update'])->name('settings.employee-global-search.update');

        Route::get('settings/salary-run-approvals', [SalaryRunApprovalStepsController::class, 'index'])->name('settings.salary-run-approvals.index');
        Route::post('settings/salary-run-approvals', [SalaryRunApprovalStepsController::class, 'store'])->name('settings.salary-run-approvals.store');
        Route::put('settings/salary-run-approvals/{salaryRunApprovalStep}', [SalaryRunApprovalStepsController::class, 'update'])->name('settings.salary-run-approvals.update');
        Route::delete('settings/salary-run-approvals/{salaryRunApprovalStep}', [SalaryRunApprovalStepsController::class, 'destroy'])->name('settings.salary-run-approvals.destroy');
        Route::post('settings/salary-run-approvals/reorder', [SalaryRunApprovalStepsController::class, 'reorder'])->name('settings.salary-run-approvals.reorder');
    });
});
