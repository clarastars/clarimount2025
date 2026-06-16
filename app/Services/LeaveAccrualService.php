<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveAccrualLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService
{
    private const TZ = 'Asia/Riyadh';

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
        return ($date ?? Carbon::now(self::TZ))->format('Y-m');
    }

    /**
     * Set accrued balance from hire date through today and sync accrual logs (for new employees or hire/entitlement changes).
     */
    public function initializeAccruedBalanceForEmployee(Employee $employee, bool $replaceExistingLogs = true): float
    {
        $employee->refresh();
        $asOf = Carbon::now(self::TZ)->startOfDay();
        $monthlyDays = $this->monthlyAccrualDays($employee);

        if ($monthlyDays <= 0) {
            return $this->persistAccruedBalance($employee, 0, [], $replaceExistingLogs);
        }

        $hireDate = $this->resolveHireDate($employee);
        if ($hireDate === null || $hireDate->gt($asOf)) {
            return $this->persistAccruedBalance($employee, 0, [], $replaceExistingLogs);
        }

        $periods = $this->eligibleAccrualPeriods($hireDate, $asOf);
        $runningBalance = 0.0;
        $logRows = [];

        foreach ($periods as $period) {
            $runningBalance = round($runningBalance + $monthlyDays, 2);
            $logRows[] = [
                'accrual_period' => $period,
                'days_accrued' => $monthlyDays,
                'balance_after' => $runningBalance,
            ];
        }

        return $this->persistAccruedBalance($employee, $runningBalance, $logRows, $replaceExistingLogs);
    }

    public function isEmployeeEligibleForAccrualPeriod(Employee $employee, string $period): bool
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return false;
        }

        if ($this->monthlyAccrualDays($employee) <= 0) {
            return false;
        }

        $hireDate = $this->resolveHireDate($employee);
        if ($hireDate === null) {
            return false;
        }

        $periodStart = Carbon::createFromFormat('Y-m-d', $period.'-01', self::TZ)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->endOfDay();

        return $hireDate->lte($periodEnd);
    }

    /**
     * @return list<string> Accrual periods (YYYY-MM) from hire month through the as-of month.
     */
    public function eligibleAccrualPeriods(Carbon $hireDate, Carbon $asOf): array
    {
        $hireDate = $hireDate->copy()->timezone(self::TZ)->startOfDay();
        $asOf = $asOf->copy()->timezone(self::TZ)->startOfDay();

        if ($hireDate->gt($asOf)) {
            return [];
        }

        $periods = [];
        $cursor = $hireDate->copy()->startOfMonth();
        $asOfMonth = $asOf->copy()->startOfMonth();

        while ($cursor->lte($asOfMonth)) {
            if ($hireDate->lte($cursor->copy()->endOfMonth())) {
                $periods[] = $cursor->format('Y-m');
            }
            $cursor->addMonth();
        }

        return $periods;
    }

    private function accrueEmployeeForPeriod(Employee $employee, string $period, bool $force): ?LeaveAccrualLog
    {
        if (! $this->isEmployeeEligibleForAccrualPeriod($employee, $period)) {
            return null;
        }

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

    private function resolveHireDate(Employee $employee): ?Carbon
    {
        if ($employee->hire_date === null) {
            return null;
        }

        return Carbon::parse($employee->hire_date, self::TZ)->startOfDay();
    }

    /**
     * @param  list<array{accrual_period: string, days_accrued: float, balance_after: float}>  $logRows
     */
    private function persistAccruedBalance(
        Employee $employee,
        float $balance,
        array $logRows,
        bool $replaceExistingLogs
    ): float {
        return (float) DB::transaction(function () use ($employee, $balance, $logRows, $replaceExistingLogs): float {
            if ($replaceExistingLogs) {
                LeaveAccrualLog::query()
                    ->where('employee_id', $employee->id)
                    ->delete();
            }

            $lockedEmployee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $lockedEmployee->update([
                'leave_accrued_balance' => $balance,
            ]);

            foreach ($logRows as $row) {
                if (
                    ! $replaceExistingLogs
                    && LeaveAccrualLog::query()
                        ->where('employee_id', $lockedEmployee->id)
                        ->where('accrual_period', $row['accrual_period'])
                        ->exists()
                ) {
                    continue;
                }

                LeaveAccrualLog::query()->create([
                    'employee_id' => $lockedEmployee->id,
                    'accrual_period' => $row['accrual_period'],
                    'days_accrued' => $row['days_accrued'],
                    'annual_entitlement' => (int) $lockedEmployee->annual_leave_balance,
                    'balance_after' => $row['balance_after'],
                ]);
            }

            return $balance;
        });
    }
}
