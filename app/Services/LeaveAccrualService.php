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

    /**
     * Accrue one month for active employees who have annual entitlement but no hire_date.
     * Skips hire_date eligibility only; duplicate periods are never applied twice.
     *
     * @return array{processed: int, skipped: int, accrued: int}
     */
    public function accrueForPeriodMissingHireDate(string $period): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Accrual period must be in YYYY-MM format.');
        }

        $processed = 0;
        $skipped = 0;
        $accrued = 0;

        Employee::query()
            ->where('employment_status', 'active')
            ->whereNull('hire_date')
            ->where('annual_leave_balance', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($period, &$processed, &$skipped, &$accrued): void {
                foreach ($employees as $employee) {
                    $result = $this->accrueEmployeeForPeriod(
                        $employee,
                        $period,
                        force: false,
                        requireHireDate: false,
                    );

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

    /**
     * Extra leave days that will have been earned by the as-of date, beyond today's stored balance.
     * Only completed months are counted (the in-progress month is not accrued in advance).
     */
    public function futureAccrualDaysUntil(Employee $employee, Carbon $asOf): float
    {
        $monthlyDays = $this->monthlyAccrualDays($employee);

        if ($monthlyDays <= 0) {
            return 0.0;
        }

        $todayEarnedThrough = $this->resolveEarnedThroughDate($employee);
        $asOfEarnedThrough = $this->resolveEarnedThroughDate($employee, $asOf);

        if ($asOfEarnedThrough->lte($todayEarnedThrough)) {
            return 0.0;
        }

        $hireDate = $this->resolveHireDate($employee);
        $cursor = $todayEarnedThrough->copy()->addDay()->startOfMonth();
        $endMonth = $asOfEarnedThrough->copy()->startOfMonth();
        $extra = 0.0;

        while ($cursor->lte($endMonth)) {
            $daysForPeriod = $this->accrualDaysForPeriod(
                $employee,
                $cursor->format('Y-m'),
                $hireDate,
                $asOfEarnedThrough,
            );

            if ($daysForPeriod > 0) {
                $extra = round($extra + $daysForPeriod, 2);
            }

            $cursor->addMonth();
        }

        return $extra;
    }

    public function resolveCurrentAccrualPeriod(?Carbon $date = null): string
    {
        return ($date ?? Carbon::now(self::TZ))->format('Y-m');
    }

    /**
     * Last fully completed calendar month (YYYY-MM). On 3 Sep this is 2026-08.
     */
    public function resolveLastCompletedAccrualPeriod(?Carbon $date = null): string
    {
        return $this->resolveLastCompletedAccrualDate($date)->format('Y-m');
    }

    /**
     * Last day of the last fully completed calendar month.
     */
    public function resolveLastCompletedAccrualDate(?Carbon $date = null): Carbon
    {
        $date = $date === null
            ? Carbon::now(self::TZ)->startOfDay()
            : ($this->calendarDateInRiyadh($date) ?? Carbon::now(self::TZ)->startOfDay());

        return $date->copy()->timezone(self::TZ)->startOfMonth()->subDay()->startOfDay();
    }

    /**
     * Date through which leave has actually been earned: last completed month,
     * or the departure date when the employee has already left.
     */
    public function resolveEarnedThroughDate(Employee $employee, ?Carbon $date = null): Carbon
    {
        $asOf = $date === null
            ? Carbon::now(self::TZ)->startOfDay()
            : ($this->calendarDateInRiyadh($date) ?? Carbon::now(self::TZ)->startOfDay());

        $departureDate = $this->resolveDepartureDate($employee);
        if ($departureDate !== null && $departureDate->lte($asOf)) {
            return $departureDate;
        }

        return $this->resolveLastCompletedAccrualDate($asOf);
    }

    /**
     * Accrued days earned by the given date (completed months only, unless already departed).
     */
    public function projectedAccruedBalanceAsOf(Employee $employee, Carbon $asOf): float
    {
        $monthlyDays = $this->monthlyAccrualDays($employee);

        if ($monthlyDays <= 0) {
            return 0.0;
        }

        $hireDate = $this->resolveHireDate($employee);

        if ($hireDate === null) {
            return $this->projectAccruedWithoutHireDate($employee, $asOf);
        }

        $earnedThrough = $this->resolveEarnedThroughDate($employee, $asOf);

        if ($hireDate->gt($earnedThrough)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($this->eligibleAccrualPeriods($hireDate, $earnedThrough) as $period) {
            $daysForPeriod = $this->accrualDaysForPeriod($employee, $period, $hireDate, $earnedThrough);

            if ($daysForPeriod <= 0) {
                continue;
            }

            $total = round($total + $daysForPeriod, 2);
        }

        return $total;
    }

    /**
     * Days earned from hire through a specific date (the last month is pro-rated).
     * Months that have not completed yet as of today are excluded, so settlement
     * never pays leave that is still in progress on the live balance.
     */
    public function projectedAccruedBalanceThroughDate(Employee $employee, Carbon $asOf): float
    {
        $monthlyDays = $this->monthlyAccrualDays($employee);

        if ($monthlyDays <= 0) {
            return 0.0;
        }

        $through = $this->resolveSettlementEarnedThroughDate($employee, $asOf);
        $hireDate = $this->resolveHireDate($employee);

        if ($hireDate === null) {
            return $this->projectAccruedWithoutHireDateThrough($employee, $through);
        }

        if ($hireDate->gt($through)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($this->eligibleAccrualPeriods($hireDate, $through) as $period) {
            $daysForPeriod = $this->accrualDaysForPeriod(
                $employee,
                $period,
                $hireDate,
                $through,
                prorateToAsOf: true,
            );

            if ($daysForPeriod <= 0) {
                continue;
            }

            $total = round($total + $daysForPeriod, 2);
        }

        return $total;
    }

    /**
     * Set accrued balance from hire date through the last completed month (or departure date).
     */
    public function initializeAccruedBalanceForEmployee(Employee $employee, bool $replaceExistingLogs = true): float
    {
        $employee->refresh();
        $asOf = $this->resolveEarnedThroughDate($employee);
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
            $daysForPeriod = $this->accrualDaysForPeriod($employee, $period, $hireDate, $asOf);

            if ($daysForPeriod <= 0) {
                continue;
            }

            $runningBalance = round($runningBalance + $daysForPeriod, 2);
            $logRows[] = [
                'accrual_period' => $period,
                'days_accrued' => $daysForPeriod,
                'balance_after' => $runningBalance,
            ];
        }

        return $this->persistAccruedBalance($employee, $runningBalance, $logRows, $replaceExistingLogs);
    }

    /**
     * Accrued days for a calendar month, pro-rated when the hire or departure date falls mid-month.
     */
    public function accrualDaysForPeriod(
        Employee $employee,
        string $period,
        ?Carbon $hireDate = null,
        ?Carbon $asOf = null,
        bool $prorateToAsOf = false,
    ): float {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Accrual period must be in YYYY-MM format.');
        }

        $monthlyDays = $this->monthlyAccrualDays($employee);

        if ($monthlyDays <= 0) {
            return 0.0;
        }

        $periodStart = Carbon::createFromFormat('Y-m-d', $period.'-01', self::TZ)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->startOfDay();
        $daysInMonth = $periodStart->daysInMonth;

        $hireDate ??= $this->resolveHireDate($employee);
        if ($hireDate === null) {
            return $monthlyDays;
        }

        $asOf ??= $this->resolveAccrualAsOfDate($employee);
        $departureDate = $this->resolveDepartureDate($employee);

        if ($hireDate->gt($periodEnd) || $asOf->lt($periodStart)) {
            return 0.0;
        }

        $rangeStart = $hireDate->gt($periodStart) ? $hireDate->copy()->startOfDay() : $periodStart->copy();
        $rangeEnd = $periodEnd->copy();

        if ($departureDate !== null && $departureDate->lt($rangeEnd)) {
            $rangeEnd = $departureDate->copy()->startOfDay();
        }

        if ($prorateToAsOf && $asOf->lt($rangeEnd)) {
            $rangeEnd = $asOf->copy()->startOfDay();
        }

        if ($rangeStart->gt($rangeEnd)) {
            return 0.0;
        }

        $daysInRange = (int) round($rangeStart->diffInDays($rangeEnd, false)) + 1;

        if ($daysInRange >= $daysInMonth) {
            return $monthlyDays;
        }

        return round(($daysInRange / $daysInMonth) * $monthlyDays, 2);
    }

    public function isEmployeeEligibleForAccrualPeriod(
        Employee $employee,
        string $period,
        bool $requireHireDate = true
    ): bool {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return false;
        }

        if ($this->monthlyAccrualDays($employee) <= 0) {
            return false;
        }

        $hireDate = $this->resolveHireDate($employee);
        if ($hireDate === null) {
            return ! $requireHireDate;
        }

        return $this->accrualDaysForPeriod($employee, $period, $hireDate) > 0;
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

    private function accrueEmployeeForPeriod(
        Employee $employee,
        string $period,
        bool $force = false,
        bool $requireHireDate = true
    ): ?LeaveAccrualLog {
        if (! $this->isEmployeeEligibleForAccrualPeriod($employee, $period, $requireHireDate)) {
            return null;
        }

        $daysForPeriod = $this->accrualDaysForPeriod($employee, $period);

        if ($daysForPeriod <= 0) {
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

        return DB::transaction(function () use ($employee, $period, $daysForPeriod, $force): LeaveAccrualLog {
            if ($force) {
                LeaveAccrualLog::query()
                    ->where('employee_id', $employee->id)
                    ->where('accrual_period', $period)
                    ->delete();
            }

            $lockedEmployee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $newBalance = round((float) $lockedEmployee->leave_accrued_balance + $daysForPeriod, 2);

            $lockedEmployee->update([
                'leave_accrued_balance' => $newBalance,
            ]);

            return LeaveAccrualLog::query()->create([
                'employee_id' => $lockedEmployee->id,
                'accrual_period' => $period,
                'days_accrued' => $daysForPeriod,
                'annual_entitlement' => (int) $lockedEmployee->annual_leave_balance,
                'balance_after' => $newBalance,
            ]);
        });
    }

    /**
     * Cap a settlement as-of date at the last completed month (and departure).
     */
    private function resolveSettlementEarnedThroughDate(Employee $employee, Carbon $asOf): Carbon
    {
        $through = $this->calendarDateInRiyadh($asOf) ?? Carbon::now(self::TZ)->startOfDay();
        $lastCompleted = $this->resolveLastCompletedAccrualDate();

        if ($through->gt($lastCompleted)) {
            $through = $lastCompleted->copy();
        }

        $departureDate = $this->resolveDepartureDate($employee);
        if ($departureDate !== null && $through->gt($departureDate)) {
            $through = $departureDate->copy();
        }

        return $through;
    }

    private function projectAccruedWithoutHireDateThrough(Employee $employee, Carbon $through): float
    {
        $currentAccrued = round((float) ($employee->leave_accrued_balance ?? 0), 2);
        $storedThrough = $this->resolveEarnedThroughDate($employee);

        if ($through->gte($storedThrough)) {
            return $currentAccrued;
        }

        $monthlyDays = $this->monthlyAccrualDays($employee);
        $unearned = 0.0;
        $cursor = $through->copy()->addDay()->startOfDay();

        while ($cursor->lte($storedThrough)) {
            $monthEnd = $cursor->copy()->endOfMonth()->startOfDay();
            $chunkEnd = $storedThrough->lt($monthEnd) ? $storedThrough->copy() : $monthEnd;
            $daysInMonth = $cursor->daysInMonth;
            $days = (int) round($cursor->diffInDays($chunkEnd, false)) + 1;

            $unearned = $days >= $daysInMonth
                ? round($unearned + $monthlyDays, 2)
                : round($unearned + ($days / $daysInMonth) * $monthlyDays, 2);

            $cursor = $chunkEnd->copy()->addDay()->startOfDay();
        }

        return max(0.0, round($currentAccrued - $unearned, 2));
    }

    private function projectAccruedWithoutHireDate(Employee $employee, Carbon $asOf): float
    {
        $currentAccrued = round((float) ($employee->leave_accrued_balance ?? 0), 2);
        $todayEarnedThrough = $this->resolveEarnedThroughDate($employee);
        $asOfEarnedThrough = $this->resolveEarnedThroughDate($employee, $asOf);

        if ($asOfEarnedThrough->gte($todayEarnedThrough)) {
            return round($currentAccrued + $this->futureAccrualDaysUntil($employee, $asOf), 2);
        }

        $monthlyDays = $this->monthlyAccrualDays($employee);
        $cursor = $asOfEarnedThrough->copy()->addDay()->startOfMonth();
        $endMonth = $todayEarnedThrough->copy()->startOfMonth();
        $laterMonths = 0.0;

        while ($cursor->lte($endMonth)) {
            $laterMonths = round($laterMonths + $monthlyDays, 2);
            $cursor->addMonth();
        }

        return max(0.0, round($currentAccrued - $laterMonths, 2));
    }

    private function resolveHireDate(Employee $employee): ?Carbon
    {
        return $this->calendarDateInRiyadh($employee->hire_date);
    }

    private function resolveDepartureDate(Employee $employee): ?Carbon
    {
        return $this->calendarDateInRiyadh($employee->departure_date ?? $employee->termination_date);
    }

    private function resolveAccrualAsOfDate(Employee $employee, ?Carbon $date = null): Carbon
    {
        $asOf = $date === null
            ? Carbon::now(self::TZ)->startOfDay()
            : $this->calendarDateInRiyadh($date) ?? Carbon::now(self::TZ)->startOfDay();

        $departureDate = $this->resolveDepartureDate($employee);

        if ($departureDate !== null && $departureDate->lt($asOf)) {
            return $departureDate;
        }

        return $asOf;
    }

    private function calendarDateInRiyadh(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            $date = $value->format('Y-m-d');
        } else {
            $raw = trim((string) $value);
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $matches) === 1) {
                $date = $matches[1];
            } else {
                $date = Carbon::parse($raw)->format('Y-m-d');
            }
        }

        return Carbon::createFromFormat('Y-m-d', $date, self::TZ)->startOfDay();
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
