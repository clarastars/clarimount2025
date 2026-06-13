<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveAccrualLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService
{
    /**
     * @return array{processed: int, skipped: int, accrued: int}
     */
    public function accrueForPeriod(string $period, bool $force = false): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Accrual period must be in YYYY-MM format.');
        }

        $processed = 0;
        $skipped = 0;
        $accrued = 0;

        Employee::query()
            ->where('employment_status', 'active')
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($period, $force, &$processed, &$skipped, &$accrued): void {
                foreach ($employees as $employee) {
                    $result = $this->accrueEmployeeForPeriod($employee, $period, $force);

                    if ($result === null) {
                        $skipped++;
                        continue;
                    }

                    $processed++;
                    $accrued++;
                }
            });

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'accrued' => $accrued,
        ];
    }

    public function monthlyAccrualDays(Employee $employee): float
    {
        $entitlement = (int) ($employee->annual_leave_balance ?? 0);

        if ($entitlement <= 0) {
            return 0.0;
        }

        return round($entitlement / 12, 2);
    }

    public function resolveCurrentAccrualPeriod(?Carbon $date = null): string
    {
        return ($date ?? Carbon::now('Asia/Riyadh'))->format('Y-m');
    }

    private function accrueEmployeeForPeriod(Employee $employee, string $period, bool $force): ?LeaveAccrualLog
    {
        $monthlyDays = $this->monthlyAccrualDays($employee);

        if ($monthlyDays <= 0) {
            return null;
        }

        if (
            ! $force
            && LeaveAccrualLog::query()
                ->where('employee_id', $employee->id)
                ->where('accrual_period', $period)
                ->exists()
        ) {
            return null;
        }

        return DB::transaction(function () use ($employee, $period, $monthlyDays, $force): LeaveAccrualLog {
            if ($force) {
                LeaveAccrualLog::query()
                    ->where('employee_id', $employee->id)
                    ->where('accrual_period', $period)
                    ->delete();
            }

            $lockedEmployee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $newBalance = round((float) $lockedEmployee->leave_accrued_balance + $monthlyDays, 2);

            $lockedEmployee->update([
                'leave_accrued_balance' => $newBalance,
            ]);

            return LeaveAccrualLog::query()->create([
                'employee_id' => $lockedEmployee->id,
                'accrual_period' => $period,
                'days_accrued' => $monthlyDays,
                'annual_entitlement' => (int) $lockedEmployee->annual_leave_balance,
                'balance_after' => $newBalance,
            ]);
        });
    }
}
