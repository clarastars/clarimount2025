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

    protected $description = 'Recalculate leave_accrued_balance from hire_date and annual entitlement';

    public function handle(LeaveAccrualService $service): int
    {
        $employeeId = $this->option('employee');

        $query = Employee::query()->orderBy('id');
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

        $this->info("Recalculated accrued balance for {$count} employee(s).");

        return self::SUCCESS;
    }
}
