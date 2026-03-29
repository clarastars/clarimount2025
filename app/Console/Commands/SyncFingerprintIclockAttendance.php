<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FingerprintIclockAttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncFingerprintIclockAttendance extends Command
{
    protected $signature = 'attendance:sync-fingerprint-iclock
                           {--job : Dispatch the job to the queue instead of running synchronously}
                           {--month : Sync attendance from start of current month until today}
                           {--date= : Sync attendance for one day only (Y-m-d, Asia/Riyadh)}
                           {--progress : Print per-day/per-employee progress to the terminal while running}';

    protected $description = 'Sync today\'s attendance from fingerprint iClock API (first punch = check-in, last = check-out)';

    public function handle(FingerprintIclockAttendanceService $service): int
    {
        if ($this->option('month') && $this->option('date')) {
            $this->error('Use either --month or --date, not both.');

            return 1;
        }

        // If --date is provided, sync a single calendar day for all employees with fingerprint pin.
        if ($this->option('date')) {
            try {
                $date = Carbon::parse($this->option('date'), 'Asia/Riyadh')->startOfDay();
            } catch (\Throwable) {
                $this->error('Invalid --date. Use Y-m-d (e.g. 2026-03-15).');

                return 1;
            }

            $service->setProgressEnabled(true);
            $this->info('Syncing iClock attendance for '.$date->format('Y-m-d').' (Asia/Riyadh)...');
            $service->syncForDate($date);
            $this->info('Done.');

            return 0;
        }

        // If --month is provided, backfill from start of current month until today.
        if ($this->option('month')) {
            // For backfills we usually want to see what employee/day is processing right now.
            $service->setProgressEnabled(true);

            $this->info('Syncing current month attendance (from start of month until today) from iClock API...');
            $service->syncCurrentMonthUntilToday();
            $this->info('Done syncing current month.');
            return 0;
        }

        if ($this->option('job')) {
            \App\Jobs\SyncFingerprintIclockAttendanceJob::dispatch();
            $this->info('Sync job dispatched to the queue.');
            return 0;
        }

        $this->info('Syncing today\'s attendance from iClock API...');
        $service->syncToday();
        $this->info('Done.');
        return 0;
    }
}
