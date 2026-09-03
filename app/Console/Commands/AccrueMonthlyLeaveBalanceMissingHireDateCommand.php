<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LeaveAccrualService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AccrueMonthlyLeaveBalanceMissingHireDateCommand extends Command
{
    protected $signature = 'leaves:accrue-monthly-missing-hire-date
                            {--period= : Accrual period in YYYY-MM (defaults to last completed month in Asia/Riyadh)}';

    protected $description = 'Accrue the last completed month for active employees missing hire_date (keeps entitlement + duplicate-period protection)';

    public function handle(LeaveAccrualService $service): int
    {
        $period = $this->option('period') ?: $service->resolveLastCompletedAccrualPeriod();

        if (! is_string($period) || ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Period must be in YYYY-MM format, e.g. 2026-07.');

            return self::FAILURE;
        }

        $this->info("Accruing monthly leave for employees without hire_date for period {$period}...");

        try {
            $result = $service->accrueForPeriodMissingHireDate($period);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Leave accrual completed for {$period} (missing hire_date only).");
        $this->line("Accrued: {$result['accrued']} employees");
        $this->line("Skipped: {$result['skipped']} employees (already accrued this period or not eligible)");

        return self::SUCCESS;
    }
}
