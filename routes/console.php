<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled tasks for Bayzat attendance system
Schedule::command('bayzat:retry-failed')->hourly()->description('Retry failed Bayzat sync records');
Schedule::command('attendance:cleanup-imports --days=30')->monthly()->description('Clean up old attendance import files');

// Fingerprint iClock API: sync today's attendance (first punch = check-in, last punch = check-out) every 10 minutes
Schedule::job(new \App\Jobs\SyncFingerprintIclockAttendanceJob())->everyTenMinutes()->description('Sync fingerprint iClock attendance for today');

// Rebuild attendance index cache (presentations + absence penalties) for the current month nightly
Schedule::job(new \App\Jobs\RebuildAttendancePresentationJob(null, true))
    ->dailyAt('01:15')
    ->description('Rebuild attendance_daily_presentations for current month (all companies)');

// Evaluate early departures after the workday using the latest checkout punch for today.
Schedule::job(new \App\Jobs\ProcessEarlyDeparturePenaltiesJob())
    ->dailyAt('20:00')
    ->timezone('Asia/Riyadh')
    ->description('Process early departure attendance penalties for today');

// Monthly leave accrual: previous completed month (e.g. on 1 Sep accrue August; 21 → 1.75/month)
Schedule::job(new \App\Jobs\AccrueMonthlyLeaveBalanceJob())
    ->monthlyOn(1, '02:00')
    ->timezone('Asia/Riyadh')
    ->description('Accrue leave balance for the last completed month');
