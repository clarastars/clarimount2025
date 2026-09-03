<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AccrueMonthlyLeaveBalanceJob;
use App\Services\LeaveAccrualService;
use Illuminate\Console\Command;

class AccrueMonthlyLeaveBalanceCommand extends Command
{
    protected $signature = 'leaves:accrue-monthly
                            {--period= : Accrual period in YYYY-MM (defaults to last completed month)}
                            {--force : Re-run accrual for the period even if already processed}
                            {--sync : Run synchronously instead of dispatching a queue job}';

    protected $description = 'Accrue leave for the last completed month (annual entitlement ÷ 12)';

    public function handle(LeaveAccrualService $service): int
    {
        $period = $this->option('period') ?: $service->resolveLastCompletedAccrualPeriod();
        $force = (bool) $this->option('force');

        if ($this->option('sync')) {
            $result = $service->accrueForPeriod($period, $force);

            $this->info("Leave accrual completed for {$period}.");
            $this->line("Accrued: {$result['accrued']} employees");
            $this->line("Skipped: {$result['skipped']} employees");

            return self::SUCCESS;
        }

        AccrueMonthlyLeaveBalanceJob::dispatch($period, $force);

        $this->info("Monthly leave accrual job dispatched for {$period}.");

        return self::SUCCESS;
    }
}
