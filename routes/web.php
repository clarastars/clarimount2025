<?php

use App\Http\Controllers\AdditionsController;
use App\Http\Controllers\Admin\AdminTeamController;
use App\Http\Controllers\AssetCategoryController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetTemplateController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendancePenaltyApprovalController;
use App\Http\Controllers\BayzatConfigController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySalaryRunApprovalStepsController;
use App\Http\Controllers\CustodyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeductionsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDebtController;
use App\Http\Controllers\EmployeePortalLeaveController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\FingerprintDeviceEmployeeController;
use App\Http\Controllers\LaborLawRuleController;
use App\Http\Controllers\CompanyLeaveApprovalStepsController;
use App\Http\Controllers\CompanyLeaveController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PrintJobController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SalaryRunController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StorageTestController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ZkTecoController;
use App\Http\Controllers\ZKTekoDebugController;
use App\Http\Controllers\ZKTekoWebhookController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ZKTeco Device Data Routes (no authentication required, CSRF exempt)
// This route must be BEFORE any middleware groups to ensure it's accessible
Route::match(['GET', 'POST'], '/iclock/cdata', function (\Illuminate\Http\Request $request) {
    // Log at route level to confirm request reached Laravel
    try {
        Log::channel('single')->info('=== ZKTeco ROUTE HIT ===');
        Log::channel('single')->info('Route Time: '.now()->toDateTimeString());
        Log::channel('single')->info('Route Method: '.$request->method());
        Log::channel('single')->info('Route URL: '.$request->fullUrl());
        Log::channel('single')->info('Route IP: '.$request->ip());
        Log::channel('single')->info('Route Query: '.json_encode($request->query()));
    } catch (\Exception $e) {
        Log::channel('single')->error('Route Log Error: '.$e->getMessage());
    }

    // Call the controller
    return app(ZkTecoController::class)->cdata($request);
})->name('zkteco.cdata');

// ZKTeko Fingerprint Device Webhook Routes (no authentication required)
Route::prefix('webhook/fp')->name('webhook.fp.')->group(function () {
    Route::post('/', [ZKTekoWebhookController::class, 'handle'])->name('handle');
    Route::get('/test', [ZKTekoWebhookController::class, 'test'])->name('test');
});

// Simple test route to verify routing is working
Route::get('/test-simple', function () {
    return response()->json(['message' => 'Simple route working']);
});

Route::get('/', function () {
    // Redirect authenticated users to dashboard
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    // Show landing page for non-authenticated users
    return view('landing.index');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role_or_permission:super-admin|settings.access'])->group(function () {
    Route::get('storage-test', [StorageTestController::class, 'index'])->name('storage-test.index');
    Route::post('storage-test', [StorageTestController::class, 'store'])->name('storage-test.store');
    Route::delete('storage-test', [StorageTestController::class, 'destroy'])->name('storage-test.destroy');
});

// Super Admin routes
Route::middleware(['auth', 'verified', 'super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard');

    // Team management
    Route::resource('teams', AdminTeamController::class);
    Route::post('teams/{team}/suspend', [AdminTeamController::class, 'suspend'])->name('teams.suspend');
    Route::post('teams/{team}/activate', [AdminTeamController::class, 'activate'])->name('teams.activate');
});

// Team management routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Routes that don't require team access
    Route::get('/teams/select', [TeamController::class, 'select'])->name('teams.select');
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::post('/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');

    // Routes that require team access
    Route::middleware(['team.access'])->group(function () {
        Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
        Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::post('/teams/{team}/invite', [TeamController::class, 'inviteUser'])->name('teams.invite');
        Route::delete('/teams/{team}/users/{user}', [TeamController::class, 'removeUser'])->name('teams.remove-user');
    });

    // Placeholder routes for team features
    Route::middleware(['team.access'])->group(function () {
        Route::get('/teams/{team}/billing', function () {
            return Inertia::render('Teams/Billing');
        })->name('teams.billing');

        Route::get('/teams/{team}/analytics', function () {
            return Inertia::render('Teams/Analytics');
        })->name('teams.analytics')->middleware('permission:view analytics');
    });
});

// Company management routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('companies', CompanyController::class);
});

// Department management routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('departments', DepartmentController::class);
});

// Labor Law Rules management routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('labor-law-rules', LaborLawRuleController::class);
});

// Shifts management (Settings)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('shifts', ShiftController::class);
});

// IT Asset Management & Ticketing System routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Debug route for employee creation
    Route::get('/debug/employee-create', function () {
        $user = Auth::user();
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        return response()->json([
            'user_id' => $user->id,
            'owned_companies_count' => $user->ownedCompanies()->count(),
            'owned_company_ids' => $ownedCompanyIds->toArray(),
            'current_company' => $user->currentCompany()?->id,
        ]);
    });

    // Locations management
    Route::middleware('role_or_permission:super-admin|asset-inventory.access')->group(function () {
        Route::resource('locations', LocationController::class);

        // Asset Categories management
        Route::resource('asset-categories', AssetCategoryController::class);

        // Assets management
        Route::get('assets/export-by-category', [AssetController::class, 'exportByCategory'])->name('assets.export-by-category');
        Route::resource('assets', AssetController::class);
        Route::get('api/assets/bulk-created', [AssetController::class, 'getBulkCreatedAssets'])->name('api.assets.bulk-created');
        Route::delete('api/assets/bulk-created', [AssetController::class, 'clearBulkCreatedAssets'])->name('api.assets.clear-bulk-created');

        // Asset Assignment tracking
        Route::get('api/assets/{asset}/assignments', [AssetController::class, 'getAssignments'])->name('api.assets.assignments');
        Route::post('api/assets/{asset}/assignments/{assignment}/print-document', [AssetController::class, 'printAssignmentDocument'])->name('api.assets.assignments.print-document');
        Route::post('api/assets/{asset}/assignments/{assignment}/upload-document', [AssetController::class, 'uploadSignedDocument'])->name('api.assets.assignments.upload-document');
        Route::get('api/assets/{asset}/assignments/{assignment}/download-document', [AssetController::class, 'downloadSignedDocument'])->name('api.assets.assignments.download-document');

        // Asset Templates management
        Route::resource('asset-templates', AssetTemplateController::class);

        // API endpoints for async searches (asset inventory scope)
        Route::get('api/locations/search', [LocationController::class, 'search'])->name('api.locations.search');
        Route::get('api/asset-templates/search', [AssetTemplateController::class, 'search'])->name('api.asset-templates.search');
        Route::get('api/asset-templates/by-category', [AssetTemplateController::class, 'byCategory'])->name('api.asset-templates.by-category');
    });

    // Employee Import routes (must come before resource routes)
    Route::get('employees/import', [EmployeeImportController::class, 'instructions'])->name('employees.import');
    Route::get('employees/import/upload', [EmployeeImportController::class, 'upload'])->name('employees.import.upload');
    Route::get('employees/import/sample-csv', [EmployeeImportController::class, 'sampleCsv'])->name('employees.import.sample-csv');
    Route::get('employees/export-csv', [EmployeeImportController::class, 'exportCsv'])->name('employees.export-csv');
    Route::post('employees/import/process', [EmployeeImportController::class, 'processUpload'])->name('employees.import.process');
    Route::post('employees/import/execute', [EmployeeImportController::class, 'executeImport'])->name('employees.import.execute');
    Route::get('employees/fingerprint-device', [FingerprintDeviceEmployeeController::class, 'index'])->name('employees.fingerprint-device');
    Route::get('api/employees/fingerprint-device-list', [FingerprintDeviceEmployeeController::class, 'listForSelect'])->name('api.employees.fingerprint-device-list');

    // Employees management
    Route::put('employees/{employee}/fingerprint-link', [EmployeeController::class, 'updateFingerprintLink'])->name('employees.fingerprint-link');
    Route::post('employees/{employee}/sync-fingerprint-month', [EmployeeController::class, 'syncFingerprintMonth'])->name('employees.sync-fingerprint-month');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::delete('employees/{employee}/documents/{type}', [EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy');
    Route::get('employees/expiring-documents', [EmployeeController::class, 'expiringDocuments'])
        ->name('employees.expiring-documents.index');
    Route::resource('employees', EmployeeController::class);

    // Employee Leaves routes
    Route::get('employees/{employee}/leaves/create', [LeaveController::class, 'create'])->name('employees.leaves.create');
    Route::post('employees/{employee}/leaves', [LeaveController::class, 'store'])->name('employees.leaves.store');

    // Company Leaves routes
    Route::get('companies/{company}/leaves', [CompanyLeaveController::class, 'index'])->name('companies.leaves.index');
    Route::post('companies/{company}/leaves', [CompanyLeaveController::class, 'store'])->name('companies.leaves.store');
    Route::post('companies/{company}/leave-requests/{leaveRequest}/approve', [CompanyLeaveController::class, 'approveRequest'])->name('companies.leave-requests.approve');
    Route::post('companies/{company}/leave-requests/{leaveRequest}/reject', [CompanyLeaveController::class, 'rejectRequest'])->name('companies.leave-requests.reject');
    Route::post('companies/{company}/leave-requests/{leaveRequest}/approval-steps/{leaveApprovalStep}/approve', [CompanyLeaveController::class, 'approveWorkflowStep'])->name('companies.leave-requests.approve-step');
    Route::post('companies/{company}/leave-requests/{leaveRequest}/approval-steps/{leaveApprovalStep}/reject', [CompanyLeaveController::class, 'rejectWorkflowStep'])->name('companies.leave-requests.reject-step');

    // Leave approval steps (per company)
    Route::get('companies/{company}/leave-approvals', [CompanyLeaveApprovalStepsController::class, 'index'])->name('companies.leave-approvals.index');
    Route::post('companies/{company}/leave-approvals', [CompanyLeaveApprovalStepsController::class, 'store'])->name('companies.leave-approvals.store');
    Route::put('companies/{company}/leave-approvals/{leaveApprovalStep}', [CompanyLeaveApprovalStepsController::class, 'update'])->name('companies.leave-approvals.update');
    Route::delete('companies/{company}/leave-approvals/{leaveApprovalStep}', [CompanyLeaveApprovalStepsController::class, 'destroy'])->name('companies.leave-approvals.destroy');
    Route::post('companies/{company}/leave-approvals/reorder', [CompanyLeaveApprovalStepsController::class, 'reorder'])->name('companies.leave-approvals.reorder');

    // Employee portal — self-service leaves
    Route::get('my/leaves', [EmployeePortalLeaveController::class, 'index'])->name('employee.leaves.index');
    Route::post('my/leaves', [EmployeePortalLeaveController::class, 'store'])->name('employee.leaves.store');
    Route::delete('my/leaves/{leaveRequest}', [EmployeePortalLeaveController::class, 'destroy'])->name('employee.leaves.destroy');

    // Employee Debts routes
    Route::prefix('employees/{employee}/debts')->name('employee-debts.')->group(function () {
        Route::post('/', [EmployeeDebtController::class, 'store'])->name('store');
        Route::put('/{debt}', [EmployeeDebtController::class, 'update'])->name('update');
        Route::delete('/{debt}', [EmployeeDebtController::class, 'destroy'])->name('destroy');
    });

    // Custody management routes
    Route::get('employees/{employee}/custody', [CustodyController::class, 'show'])->name('employees.custody.show');
    Route::post('employees/{employee}/custody', [CustodyController::class, 'store'])->name('employees.custody.store');
    Route::post('employees/{employee}/custody/quick-create-asset', [CustodyController::class, 'storeQuickAsset'])->name('employees.custody.quick-create-asset');
    Route::get('custody-changes/{custodyChange}/document', [CustodyController::class, 'generateDocument'])->name('custody.document');
    Route::get('custody-changes/{custodyChange}/uploaded-document', [CustodyController::class, 'showUploadedDocument'])->name('custody.uploaded-document');
    Route::post('custody-changes/{custodyChange}/upload', [CustodyController::class, 'uploadDocument'])->name('custody.upload');
    Route::get('api/custody/available-assets', [CustodyController::class, 'getAvailableAssets'])->name('api.custody.available-assets');
    Route::get('api/custody/asset-templates/search', [AssetTemplateController::class, 'search'])->name('api.custody.asset-templates.search');
    Route::get('api/custody/asset-templates/by-category', [AssetTemplateController::class, 'byCategory'])->name('api.custody.asset-templates.by-category');
    Route::post('api/custody/asset-templates', [AssetTemplateController::class, 'store'])->name('api.custody.asset-templates.store');

    // Print Jobs management
    Route::get('/print-available', [PrintJobController::class, 'printStation'])->name('print-station');
    Route::post('api/print-jobs', [PrintJobController::class, 'create'])->name('api.print-jobs.create');
    Route::get('api/print-jobs/pending', [PrintJobController::class, 'pending'])->name('api.print-jobs.pending');
    Route::get('api/print-jobs/history', [PrintJobController::class, 'history'])->name('api.print-jobs.history');
    Route::get('api/print-jobs/statistics', [PrintJobController::class, 'statistics'])->name('api.print-jobs.statistics');
    Route::patch('api/print-jobs/{printJob}/status', [PrintJobController::class, 'updateStatus'])->name('api.print-jobs.update-status');
    Route::delete('api/print-jobs/{printJob}', [PrintJobController::class, 'cancel'])->name('api.print-jobs.cancel');

    // Attendance Management routes
    Route::get('companies/{company}/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::put('companies/{company}/attendance/penalty-auto-approval', [AttendanceController::class, 'updatePenaltyAutoApproval'])->name('attendance.penalty-auto-approval.update');
    Route::get('companies/{company}/attendance/late', [AttendanceController::class, 'late'])->name('attendance.late');
    Route::get('companies/{company}/attendance/deductions', [DeductionsController::class, 'index'])->name('attendance.deductions');
    Route::post('attendance/deductions', [DeductionsController::class, 'store'])->name('attendance.deductions.store');
    Route::put('attendance/deductions/{deduction}', [DeductionsController::class, 'update'])->name('attendance.deductions.update');
    Route::delete('attendance/deductions/{deduction}', [DeductionsController::class, 'destroy'])->name('attendance.deductions.destroy');
    Route::get('companies/{company}/attendance/additions', [AdditionsController::class, 'index'])->name('attendance.additions');
    Route::post('attendance/additions', [AdditionsController::class, 'store'])->name('attendance.additions.store');
    Route::put('attendance/additions/{addition}', [AdditionsController::class, 'update'])->name('attendance.additions.update');
    Route::delete('attendance/additions/{addition}', [AdditionsController::class, 'destroy'])->name('attendance.additions.destroy');
    Route::get('companies/{company}/attendance/create', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('companies/{company}/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('companies/{company}/attendance/{import}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('companies/{company}/attendance/{import}/retry', [AttendanceController::class, 'retrySync'])->name('attendance.retry');
    Route::post('companies/{company}/attendance/batches/{batch}/retry', [AttendanceController::class, 'retrySyncBatch'])->name('attendance.batch.retry');
    Route::get('companies/{company}/attendance/template/download', [AttendanceController::class, 'downloadTemplate'])->name('attendance.template');

    // Attendance Penalty Approval routes
    Route::post('attendance-penalties/{penalty}/approve', [AttendancePenaltyApprovalController::class, 'approve'])->name('attendance-penalties.approve');
    Route::post('attendance-penalties/{penalty}/reject', [AttendancePenaltyApprovalController::class, 'reject'])->name('attendance-penalties.reject');

    // Salary run approval steps (per company)
    Route::get('companies/{company}/salary-run-approvals', [CompanySalaryRunApprovalStepsController::class, 'index'])->name('companies.salary-run-approvals.index');
    Route::post('companies/{company}/salary-run-approvals', [CompanySalaryRunApprovalStepsController::class, 'store'])->name('companies.salary-run-approvals.store');
    Route::put('companies/{company}/salary-run-approvals/{salaryRunApprovalStep}', [CompanySalaryRunApprovalStepsController::class, 'update'])->name('companies.salary-run-approvals.update');
    Route::delete('companies/{company}/salary-run-approvals/{salaryRunApprovalStep}', [CompanySalaryRunApprovalStepsController::class, 'destroy'])->name('companies.salary-run-approvals.destroy');
    Route::post('companies/{company}/salary-run-approvals/reorder', [CompanySalaryRunApprovalStepsController::class, 'reorder'])->name('companies.salary-run-approvals.reorder');

    // Salary Runs routes
    Route::get('companies/{company}/salary-runs', [SalaryRunController::class, 'index'])->name('salary-runs.index');
    Route::get('companies/{company}/salary-runs/export-excel/{salaryRun}', [SalaryRunController::class, 'exportExcel'])->name('salary-runs.export-excel');
    Route::get('companies/{company}/salary-runs/{year}/{month}', [SalaryRunController::class, 'show'])->name('salary-runs.show');
    Route::post('companies/{company}/salary-runs', [SalaryRunController::class, 'store'])->name('salary-runs.store');
    Route::delete('companies/{company}/salary-runs/{salaryRun}', [SalaryRunController::class, 'destroy'])->name('salary-runs.destroy');
    Route::post('companies/{company}/salary-runs/{salaryRun}/finalize', [SalaryRunController::class, 'finalize'])->name('salary-runs.finalize');
    Route::post('companies/{company}/salary-runs/{salaryRun}/approval-steps/{salaryRunApprovalStep}/approve', [SalaryRunController::class, 'approveStep'])->name('salary-runs.approve-step');
    Route::post('companies/{company}/salary-runs/{salaryRun}/approval-steps/{salaryRunApprovalStep}/reject', [SalaryRunController::class, 'rejectStep'])->name('salary-runs.reject-step');
    Route::post('companies/{company}/salary-runs/{salaryRun}/update-debt-deductions', [SalaryRunController::class, 'updateDebtDeductions'])->name('salary-runs.update-debt-deductions');
    Route::post('companies/{company}/salary-runs/{salaryRun}/remove-breakdown-line', [SalaryRunController::class, 'removeBreakdownLine'])->name('salary-runs.remove-breakdown-line');

    // Bayzat Configuration routes
    Route::get('bayzat-configs', [BayzatConfigController::class, 'index'])->name('bayzat-configs.index');
    Route::get('companies/{company}/bayzat-config', [BayzatConfigController::class, 'show'])->name('bayzat-configs.show');
    Route::post('companies/{company}/bayzat-config', [BayzatConfigController::class, 'store'])->name('bayzat-configs.store');
    Route::put('companies/{company}/bayzat-config', [BayzatConfigController::class, 'update'])->name('bayzat-configs.update');
    Route::delete('companies/{company}/bayzat-config', [BayzatConfigController::class, 'destroy'])->name('bayzat-configs.destroy');
    Route::post('companies/{company}/bayzat-config/test', [BayzatConfigController::class, 'testConnection'])->name('bayzat-configs.test');
    Route::post('companies/{company}/bayzat-config/toggle', [BayzatConfigController::class, 'toggle'])->name('bayzat-configs.toggle');
    Route::put('companies/{company}/bayzat-config/settings', [BayzatConfigController::class, 'updateSettings'])->name('bayzat-configs.settings');

    // ZKTeko Debug Dashboard routes
    Route::prefix('zkteko-debug')->name('zkteko-debug.')->group(function () {
        Route::get('/', [ZKTekoDebugController::class, 'index'])->name('index');
        Route::get('/devices/{id}', [ZKTekoDebugController::class, 'show'])->name('device.show');
        Route::get('/status', [ZKTekoDebugController::class, 'status'])->name('status');
        Route::get('/devices/{id}/heartbeats', [ZKTekoDebugController::class, 'heartbeats'])->name('device.heartbeats');
        Route::get('/devices/{id}/attendance-records', [ZKTekoDebugController::class, 'attendanceRecords'])->name('device.attendance-records');
        Route::post('/devices/{id}/mark-offline', [ZKTekoDebugController::class, 'markOffline'])->name('device.mark-offline');
        Route::post('/devices/{id}/clear-error', [ZKTekoDebugController::class, 'clearError'])->name('device.clear-error');
    });

    // API endpoints for async searches
    Route::get('api/companies/search', [CompanyController::class, 'search'])->name('api.companies.search');
    Route::get('api/departments/search', [DepartmentController::class, 'search'])->name('api.departments.search');
    Route::get('api/employees/search', [EmployeeController::class, 'search'])->name('api.employees.search');
    Route::get('api/employees/global-search', [EmployeeController::class, 'globalSearch'])->name('api.employees.global-search');

    Route::get('api/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::post('api/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
    Route::post('api/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
