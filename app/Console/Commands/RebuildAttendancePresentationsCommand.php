<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RebuildAttendancePresentationJob;
use App\Models\Employee;
use App\Services\AttendancePresentationRebuildService;
use App\Services\OperationalMonthService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RebuildAttendancePresentationsCommand extends Command
{
    protected $signature = 'attendance:rebuild-presentations
                            {--date= : Single day Y-m-d (Asia/Riyadh); default today when not using --month}
                            {--month : Rebuild from start of current operational month through today}
                            {--company= : Only this company id (runs in-process, not queued)}
                            {--company_id= : Alias of --company for consistency with other commands}
                            {--employee_id= : Only this employee id (runs in-process)}
                            {--sync : Run in this PHP process for all companies (no queue)}';

    protected $description = 'Rebuild cached attendance index rows (status, late minutes, punches) and absence penalties';

    public function handle(AttendancePresentationRebuildService $service, OperationalMonthService $operationalMonthService): int
    {
        if ($this->option('month') && $this->option('date')) {
            $this->error('Use either --month or --date, not both.');

            return 1;
        }

        $employeeId = null;
        $employeeOption = $this->option('employee_id');
        if ($employeeOption !== null && $employeeOption !== '') {
            if (! ctype_digit((string) $employeeOption)) {
                $this->error('Invalid --employee_id. It must be a positive integer.');

                return 1;
            }

            $employeeId = (int) $employeeOption;
            if ($employeeId <= 0 || ! Employee::query()->whereKey($employeeId)->exists()) {
                $this->error("Employee with id {$employeeId} was not found.");

                return 1;
            }
        }

        $companyOption = $this->option('company_id');
        if ($companyOption === null || $companyOption === '') {
            $companyOption = $this->option('company');
        }

        $companyId = $companyOption !== null && $companyOption !== ''
            ? (int) $companyOption
            : null;

        if ($employeeId !== null && $companyId !== null) {
            $this->warn('--company/--company_id is ignored when --employee_id is provided.');
        }

        if ($employeeId !== null) {
            if ($this->option('month')) {
                $now = Carbon::now('Asia/Riyadh');
                $operationalRange = $operationalMonthService->resolveCurrentOperationalMonthRange($now);
                $start = $operationalRange['start']->format('Y-m-d');
                $end = Carbon::today('Asia/Riyadh')->min($operationalRange['end']->copy()->startOfDay())->format('Y-m-d');
                $this->info("Rebuilding presentations for employee {$employeeId} from {$start} to {$end}...");
                $service->rebuildEmployeeDateRange($employeeId, $start, $end);
            } else {
                $date = $this->option('date')
                    ? Carbon::parse($this->option('date'), 'Asia/Riyadh')->format('Y-m-d')
                    : Carbon::today('Asia/Riyadh')->format('Y-m-d');
                $this->info("Rebuilding presentations for employee {$employeeId} on {$date}...");
                $service->rebuildEmployeeDateRange($employeeId, $date, $date);
            }
            $this->info('Done.');

            return 0;
        }

        if ($companyId !== null) {
            if ($this->option('month')) {
                $now = Carbon::now('Asia/Riyadh');
                $operationalRange = $operationalMonthService->resolveCurrentOperationalMonthRange($now);
                $start = $operationalRange['start']->format('Y-m-d');
                $end = Carbon::today('Asia/Riyadh')->min($operationalRange['end']->copy()->startOfDay())->format('Y-m-d');
                $this->info("Rebuilding presentations for company {$companyId} from {$start} to {$end}...");
                $service->rebuildCompanyDateRange($companyId, $start, $end);
            } else {
                $date = $this->option('date')
                    ? Carbon::parse($this->option('date'), 'Asia/Riyadh')->format('Y-m-d')
                    : Carbon::today('Asia/Riyadh')->format('Y-m-d');
                $this->info("Rebuilding presentations for company {$companyId} on {$date}...");
                $service->rebuildCompanyDateRange($companyId, $date, $date);
            }
            $this->info('Done.');

            return 0;
        }

        if ($this->option('sync')) {
            if ($this->option('month')) {
                $this->info('Rebuilding presentations for current month (all companies)...');
                $service->rebuildCurrentMonthForAllCompanies();
            } else {
                $date = $this->option('date')
                    ? Carbon::parse($this->option('date'), 'Asia/Riyadh')->format('Y-m-d')
                    : Carbon::today('Asia/Riyadh')->format('Y-m-d');
                $this->info("Rebuilding presentations for {$date} (all companies)...");
                $service->rebuildDateForAllCompanies($date);
            }
            $this->info('Done.');

            return 0;
        }

        if ($this->option('month')) {
            RebuildAttendancePresentationJob::dispatch(null, true);
            $this->info('Queued: rebuild current month for all companies.');
        } else {
            $date = $this->option('date')
                ? Carbon::parse($this->option('date'), 'Asia/Riyadh')->format('Y-m-d')
                : Carbon::today('Asia/Riyadh')->format('Y-m-d');
            RebuildAttendancePresentationJob::dispatch($date, false);
            $this->info("Queued: rebuild presentations for {$date}.");
        }

        return 0;
    }
}
