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

// Final early-departure pass for yesterday after overtime checkouts can still arrive past 20:00.
Schedule::job(new \App\Jobs\ProcessEarlyDeparturePenaltiesJob(forYesterday: true))
    ->dailyAt('00:30')
    ->timezone('Asia/Riyadh')
    ->description('Reprocess early departure penalties for yesterday');

// Daily leave accrual for employees with hire_date: rebuild balance through yesterday (excludes today).
// Employees without hire_date are skipped — use: php artisan leaves:accrue-monthly-missing-hire-date --period=YYYY-MM
Schedule::command('leaves:sync-accrued-balances')
    ->dailyAt('02:00')
    ->timezone('Asia/Riyadh')
    ->description('Sync leave accrued balances through yesterday for employees with hire_date');
