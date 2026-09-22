<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\LeaveAccrualService;
use Illuminate\Console\Command;

class SyncLeaveAccruedBalancesCommand extends Command
{
    protected $signature = 'leaves:sync-accrued-balances
                            {--employee= : Recalculate for a single employee id only}';

    protected $description = 'Recalculate leave_accrued_balance from hire_date through today (includes today; current month pro-rated). Skips employees without hire_date.';

    public function handle(LeaveAccrualService $service): int
    {
        $employeeId = $this->option('employee');

        $query = Employee::query()
            ->whereNotNull('hire_date')
            ->orderBy('id');

        if ($employeeId !== null && $employeeId !== '') {
            $query->where('id', (int) $employeeId);
        }

        $count = 0;
        $query->chunkById(100, function ($employees) use ($service, &$count): void {
            foreach ($employees as $employee) {
                $balance = $service->initializeAccruedBalanceForEmployee($employee);
                $count++;
                $this->line("Employee #{$employee->id}: {$balance} days accrued");
            }
        });

        $this->info("Recalculated accrued balance for {$count} employee(s) with hire_date.");

        return self::SUCCESS;
    }
}
