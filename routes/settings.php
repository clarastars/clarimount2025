<?php

use App\Http\Controllers\Settings\LeaveApprovalStepsController;
use App\Http\Controllers\Settings\LeaveTypeController;
use App\Http\Controllers\Settings\SalaryCertificateApprovalStepsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\EmployeeReferenceDataController;
use App\Http\Controllers\Settings\EmailTestController;
use App\Http\Controllers\Settings\EmployeeGlobalSearchSettingsController;
use App\Http\Controllers\Settings\SalaryCertificateFeeSettingsController;
use App\Http\Controllers\Settings\MissingHireDateExportController;
use App\Http\Controllers\Settings\OperationalMonthSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SalaryRunApprovalStepsController;
use App\Http\Controllers\Settings\TeamPermissionController;
use App\Http\Controllers\Settings\UserLoginSettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('settings', function () {
        $user = auth()->user();
        $isEmployee = $user && (
            $user->roles()->where('name', 'employee')->exists()
            || $user->employee()->exists()
        );

        if ($isEmployee && ! $user->hasRole('super-admin') && ! $user->can('settings.access') && ! $user->can('leave-types.manage')) {
            return redirect()->route('profile.edit');
        }

        if ($user?->hasRole('super-admin') || $user?->can('settings.access')) {
            return redirect()->route('profile.edit');
        }

        if ($user?->can('leave-types.manage')) {
            return redirect()->route('settings.leave-types.index');
        }

        return redirect()->route('profile.edit');
    });

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

    Route::get('settings/salary-run-approvals', [SalaryRunApprovalStepsController::class, 'index'])->name('settings.salary-run-approvals.index');
    Route::get('settings/leave-approvals', [LeaveApprovalStepsController::class, 'index'])->name('settings.leave-approvals.index');
    Route::get('settings/salary-certificate-approvals', [SalaryCertificateApprovalStepsController::class, 'index'])->name('settings.salary-certificate-approvals.index');
    Route::middleware('role_or_permission:super-admin|settings.access|leave-types.manage')->group(function () {
        Route::get('settings/leave-types', [LeaveTypeController::class, 'index'])->name('settings.leave-types.index');
        Route::post('settings/leave-types', [LeaveTypeController::class, 'store'])->name('settings.leave-types.store');
        Route::put('settings/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('settings.leave-types.update');
        Route::delete('settings/leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('settings.leave-types.destroy');
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
        Route::get('settings/salary-certificate-fee', [SalaryCertificateFeeSettingsController::class, 'edit'])->name('settings.salary-certificate-fee.edit');
        Route::put('settings/salary-certificate-fee', [SalaryCertificateFeeSettingsController::class, 'update'])->name('settings.salary-certificate-fee.update');

        Route::get('settings/user-login', [UserLoginSettingsController::class, 'index'])->name('settings.user-login.index');
        Route::put('settings/user-login/{user}', [UserLoginSettingsController::class, 'update'])->name('settings.user-login.update');
    });

    Route::middleware('super-admin')->group(function () {
        Route::get('settings/missing-hire-date-export', [MissingHireDateExportController::class, 'index'])
            ->name('settings.missing-hire-date-export.index');
        Route::get('settings/missing-hire-date-export/download', [MissingHireDateExportController::class, 'export'])
            ->name('settings.missing-hire-date-export.download');
    });
});
