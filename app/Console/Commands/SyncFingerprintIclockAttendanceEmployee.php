<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\AttendancePresentationRebuildService;
use App\Services\EmployeeFingerprintMonthSyncService;
use App\Services\FingerprintIclockAttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncFingerprintIclockAttendanceEmployee extends Command
{
    protected $signature = 'attendance:sync-fingerprint-iclock-employee
                            {employeeId : Employee id}
                            {--month : Sync attendance from start of current operational month until today for this employee}
                            {--progress : Print per-day/per-employee progress to the terminal while running}';

    protected $description = 'Sync fingerprint iClock attendance (and penalties) for a specific employee. Supports backfilling the current operational month.';

    public function handle(
        FingerprintIclockAttendanceService $service,
        AttendancePresentationRebuildService $presentationRebuild,
        EmployeeFingerprintMonthSyncService $monthSyncService
    ): int
    {
        $employeeId = (int) $this->argument('employeeId');
        if ($employeeId <= 0) {
            $this->error('Invalid employeeId. It must be a positive integer.');
            return 1;
        }

        $employee = Employee::query()->find($employeeId);
        if (! $employee) {
            $this->error("Employee #{$employeeId} not found.");

            return 1;
        }

        if ($this->option('month')) {
            $this->info("Syncing current operational month attendance for employee #{$employeeId} (from configured start day until today)...");
            $monthSyncService->syncForEmployee($employee);
            $this->info('Done.');
            return 0;
        }

        $today = Carbon::today('Asia/Riyadh');
        $this->info("Syncing today's attendance for employee #{$employeeId}...");
        $service->syncForEmployeeIdAndDate($employeeId, $today);
        $ymd = $today->format('Y-m-d');
        $this->info("Rebuilding attendance presentations for company {$employee->company_id} on {$ymd}...");
        $presentationRebuild->rebuildCompanyDateRange((int) $employee->company_id, $ymd, $ymd);
        $this->info('Done.');

        return 0;
    }
}

