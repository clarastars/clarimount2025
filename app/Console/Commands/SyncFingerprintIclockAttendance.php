<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FingerprintIclockAttendanceService;
use Illuminate\Console\Command;

class SyncFingerprintIclockAttendance extends Command
{
    protected $signature = 'attendance:sync-fingerprint-iclock
                           {--job : Dispatch the job to the queue instead of running synchronously}';

    protected $description = 'Sync today\'s attendance from fingerprint iClock API (first punch = check-in, last = check-out)';

    public function handle(FingerprintIclockAttendanceService $service): int
    {
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
