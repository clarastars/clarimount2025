<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;

/**
 * Resolves manual deduction using the same daily rates as {@see SalaryRunService}:
 * daily basic = basic_salary / 30; daily gross = (basic_salary + allowances) / 30.
 */
class ManualDeductionAmountService
{
    public const INPUT_MANUAL = 'manual';

    public const INPUT_BASIC_DAYS = 'basic_days';

    public const INPUT_BASIC_DAILY_PERCENT = 'basic_daily_percent';

    public const INPUT_GROSS_DAYS = 'gross_days';

    public const INPUT_GROSS_DAILY_PERCENT = 'gross_daily_percent';

    public const INPUT_BASIC_HOURS = 'basic_hours';

    public const INPUT_MODES = [
        self::INPUT_MANUAL,
        self::INPUT_BASIC_DAYS,
        self::INPUT_BASIC_DAILY_PERCENT,
        self::INPUT_GROSS_DAYS,
        self::INPUT_GROSS_DAILY_PERCENT,
        self::INPUT_BASIC_HOURS,
    ];

    public function hasValidBasicSalary(Employee $employee): bool
    {
        $basic = $employee->basic_salary;

        return $basic !== null && (float) $basic > 0;
    }

    public function grossMonthly(Employee $employee): float
    {
        $basic = (float) ($employee->basic_salary ?? 0);
        $allowances = (float) ($employee->allowances ?? 0);

        return $basic + $allowances;
    }

    public function hasValidGrossSalary(Employee $employee): bool
    {
        return $this->grossMonthly($employee) > 0;
    }

    /**
     * Basic daily wage: basic_salary / 30 (same convention as salary run penalties).
     */
    public function basicDailyWage(Employee $employee): ?float
    {
        if (! $this->hasValidBasicSalary($employee)) {
            return null;
        }

        return round((float) $employee->basic_salary / 30, 8);
    }

    /**
     * Gross daily wage: (basic_salary + allowances) / 30, same as {@see SalaryRunService} dailyWage.
     */
    public function grossDailyWage(Employee $employee): ?float
    {
        if (! $this->hasValidGrossSalary($employee)) {
            return null;
        }

        return round($this->grossMonthly($employee) / 30, 8);
    }

    /**
     * Basic hourly wage using the same minute-rate convention as AttendancePenaltyService:
     * basic_salary / (work_days_per_month × average_work_minutes_per_day) × 60.
     */
    public function basicHourlyWage(Employee $employee): ?float
    {
        if (! $this->hasValidBasicSalary($employee)) {
            return null;
        }

        $employee->loadMissing('shift.workdays');
        if (! $employee->shift) {
            return null;
        }

        $workDaysPerWeek = $employee->shift->workdays()->where('is_workday', true)->count();
        if ($workDaysPerWeek <= 0) {
            return null;
        }

        $averageWorkMinutesPerDay = $employee->shift->averageWorkMinutesPerWorkday();
        if ($averageWorkMinutesPerDay === null || $averageWorkMinutesPerDay <= 0) {
            return null;
        }

        $workDaysPerMonth = $workDaysPerWeek * (30 / 7);
        $minuteRate = (float) $employee->basic_salary / ($workDaysPerMonth * $averageWorkMinutesPerDay);

        return round($minuteRate * 60, 8);
    }

    public function resolveAmount(
        Employee $employee,
        string $mode,
        ?float $manualAmount,
        ?float $days,
        ?float $percent,
        ?float $hours
    ): ?float {
        return match ($mode) {
            self::INPUT_MANUAL => $manualAmount !== null
                ? round($manualAmount, 2)
                : null,
            self::INPUT_BASIC_DAYS => $this->fromBasicDays($employee, $days),
            self::INPUT_BASIC_DAILY_PERCENT => $this->fromBasicDailyPercent($employee, $percent),
            self::INPUT_GROSS_DAYS => $this->fromGrossDays($employee, $days),
            self::INPUT_GROSS_DAILY_PERCENT => $this->fromGrossDailyPercent($employee, $percent),
            self::INPUT_BASIC_HOURS => $this->fromBasicHours($employee, $hours),
            default => null,
        };
    }

    public function fromBasicDays(Employee $employee, ?float $days): ?float
    {
        if ($days === null || $days <= 0) {
            return null;
        }
        $daily = $this->basicDailyWage($employee);
        if ($daily === null) {
            return null;
        }

        return round($days * $daily, 2);
    }

    public function fromBasicDailyPercent(Employee $employee, ?float $percent): ?float
    {
        if ($percent === null || $percent <= 0) {
            return null;
        }
        $daily = $this->basicDailyWage($employee);
        if ($daily === null) {
            return null;
        }

        return round(($percent / 100) * $daily, 2);
    }

    public function fromGrossDays(Employee $employee, ?float $days): ?float
    {
        if ($days === null || $days <= 0) {
            return null;
        }
        $daily = $this->grossDailyWage($employee);
        if ($daily === null) {
            return null;
        }

        return round($days * $daily, 2);
    }

    public function fromGrossDailyPercent(Employee $employee, ?float $percent): ?float
    {
        if ($percent === null || $percent <= 0) {
            return null;
        }
        $daily = $this->grossDailyWage($employee);
        if ($daily === null) {
            return null;
        }

        return round(($percent / 100) * $daily, 2);
    }

    public function fromBasicHours(Employee $employee, ?float $hours): ?float
    {
        if ($hours === null || $hours <= 0) {
            return null;
        }

        $hourly = $this->basicHourlyWage($employee);
        if ($hourly === null) {
            return null;
        }

        return round($hours * $hourly, 2);
    }
}
