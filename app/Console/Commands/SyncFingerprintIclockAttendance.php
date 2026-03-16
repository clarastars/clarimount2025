<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FingerprintIclockAttendanceService;
use Illuminate\Console\Command;

class SyncFingerprintIclockAttendance extends Command
{
    protected $signature = 'attendance:sync-fingerprint-iclock
                           {--job : Dispatch the job to the queue instead of running synchronously}
                           {--month : Sync attendance from start of current month until today}';

    protected $description = 'Sync today\'s attendance from fingerprint iClock API (first punch = check-in, last = check-out)';

    public function handle(FingerprintIclockAttendanceService $service): int
    {
        // If --month is provided, backfill from start of current month until today.
        if ($this->option('month')) {
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
