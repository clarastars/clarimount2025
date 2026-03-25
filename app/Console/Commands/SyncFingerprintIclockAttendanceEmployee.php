<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FingerprintIclockAttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncFingerprintIclockAttendanceEmployee extends Command
{
    protected $signature = 'attendance:sync-fingerprint-iclock-employee
                            {employeeId : Employee id}
                            {--month : Sync attendance from start of current month until today for this employee}
                            {--progress : Print per-day/per-employee progress to the terminal while running}';

    protected $description = 'Sync fingerprint iClock attendance (and penalties) for a specific employee. Supports backfilling the current month.';

    public function handle(FingerprintIclockAttendanceService $service): int
    {
        $employeeId = (int) $this->argument('employeeId');
        if ($employeeId <= 0) {
            $this->error('Invalid employeeId. It must be a positive integer.');
            return 1;
        }

        if ($this->option('month')) {
            // For backfills we usually want to see what day/employee is processing right now.
            $service->setProgressEnabled(true);
            $this->info("Syncing current month attendance for employee #{$employeeId} (from start of month until today)...");
            $service->syncCurrentMonthUntilTodayForEmployeeId($employeeId);
            $this->info('Done.');
            return 0;
        }

        $today = Carbon::today('Asia/Riyadh');
        $this->info("Syncing today's attendance for employee #{$employeeId}...");
        $service->syncForEmployeeIdAndDate($employeeId, $today);
        $this->info('Done.');

        return 0;
    }
}

