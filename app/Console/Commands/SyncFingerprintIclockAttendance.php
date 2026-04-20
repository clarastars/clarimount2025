<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AttendancePresentationRebuildService;
use App\Services\FingerprintIclockAttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncFingerprintIclockAttendance extends Command
{
    protected $signature = 'attendance:sync-fingerprint-iclock
                           {--job : Dispatch the job to the queue instead of running synchronously}
                           {--month : Sync attendance from start of current month until today}
                           {--date= : Sync attendance for one day only (Y-m-d, Asia/Riyadh)}
                           {--company_id= : Limit sync to a specific company id}
                           {--progress : Print per-day/per-employee progress to the terminal while running}';

    protected $description = 'Sync today\'s attendance from fingerprint iClock API (first punch = check-in, last = check-out)';

    public function handle(FingerprintIclockAttendanceService $service, AttendancePresentationRebuildService $presentationRebuild): int
    {
        $companyId = null;
        $companyIdOption = $this->option('company_id');
        if ($companyIdOption !== null && $companyIdOption !== '') {
            if (! ctype_digit((string) $companyIdOption)) {
                $this->error('Invalid --company_id. It must be a positive integer.');

                return 1;
            }

            $companyId = (int) $companyIdOption;
            if ($companyId <= 0 || ! Company::query()->whereKey($companyId)->exists()) {
                $this->error("Company with id {$companyId} was not found.");

                return 1;
            }
        }

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
            $scope = $companyId !== null ? " for company #{$companyId}" : '';
            $this->info('Syncing iClock attendance for '.$date->format('Y-m-d').' (Asia/Riyadh)'.$scope.'...');
            $service->syncForDate($date, $companyId);
            $this->info('Rebuilding attendance presentations for '.$date->format('Y-m-d').'...');
            if ($companyId !== null) {
                $presentationRebuild->rebuildCompanyDateRange($companyId, $date->format('Y-m-d'), $date->format('Y-m-d'));
            } else {
                $presentationRebuild->rebuildDateForAllCompanies($date->format('Y-m-d'));
            }
            $this->info('Done.');

            return 0;
        }

        // If --month is provided, backfill from start of current month until today.
        if ($this->option('month')) {
            // For backfills we usually want to see what employee/day is processing right now.
            $service->setProgressEnabled(true);

            $scope = $companyId !== null ? " for company #{$companyId}" : '';
            $this->info('Syncing current month attendance (from start of month until today) from iClock API'.$scope.'...');
            $service->syncCurrentMonthUntilToday($companyId);
            $this->info('Rebuilding attendance presentations for current month...');
            if ($companyId !== null) {
                $now = Carbon::now('Asia/Riyadh');
                $presentationRebuild->rebuildCompanyDateRange(
                    $companyId,
                    $now->copy()->startOfMonth()->format('Y-m-d'),
                    Carbon::today('Asia/Riyadh')->format('Y-m-d')
                );
            } else {
                $presentationRebuild->rebuildCurrentMonthForAllCompanies();
            }
            $this->info('Done syncing current month.');
            return 0;
        }

        if ($this->option('job')) {
            if ($companyId !== null) {
                $this->warn('The --job mode currently queues all companies; --company_id is ignored in --job mode.');
            }
            \App\Jobs\SyncFingerprintIclockAttendanceJob::dispatch();
            $this->info('Sync job dispatched to the queue.');
            return 0;
        }

        $scope = $companyId !== null ? " for company #{$companyId}" : '';
        $this->info('Syncing today\'s attendance from iClock API'.$scope.'...');
        $service->syncToday($companyId);
        $this->info('Rebuilding attendance presentations for today...');
        $today = Carbon::today('Asia/Riyadh')->format('Y-m-d');
        if ($companyId !== null) {
            $presentationRebuild->rebuildCompanyDateRange($companyId, $today, $today);
        } else {
            $presentationRebuild->rebuildDateForAllCompanies($today);
        }
        $this->info('Done.');
        return 0;
    }
}
