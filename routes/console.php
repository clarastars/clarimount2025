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

// Monthly leave accrual: annual entitlement ÷ 12 per active employee (e.g. 21 → 1.75/month)
Schedule::job(new \App\Jobs\AccrueMonthlyLeaveBalanceJob())
    ->monthlyOn(1, '02:00')
    ->timezone('Asia/Riyadh')
    ->description('Accrue monthly leave balance for active employees');
