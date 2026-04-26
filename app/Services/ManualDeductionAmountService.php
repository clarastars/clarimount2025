<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;

/**
 * Resolves manual deduction amount from the same daily-basic rules as {@see SalaryRunService} (basic ÷ 30).
 */
class ManualDeductionAmountService
{
    public const INPUT_MANUAL = 'manual';

    public const INPUT_BASIC_DAYS = 'basic_days';

    public const INPUT_BASIC_DAILY_PERCENT = 'basic_daily_percent';

    public const INPUT_MODES = [
        self::INPUT_MANUAL,
        self::INPUT_BASIC_DAYS,
        self::INPUT_BASIC_DAILY_PERCENT,
    ];

    public function hasValidBasicSalary(Employee $employee): bool
    {
        $basic = $employee->basic_salary;

        return $basic !== null && (float) $basic > 0;
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

    public function resolveAmount(
        Employee $employee,
        string $mode,
        ?float $manualAmount,
        ?float $days,
        ?float $percent
    ): ?float {
        return match ($mode) {
            self::INPUT_MANUAL => $manualAmount !== null
                ? round($manualAmount, 2)
                : null,
            self::INPUT_BASIC_DAYS => $this->fromBasicDays($employee, $days),
            self::INPUT_BASIC_DAILY_PERCENT => $this->fromBasicDailyPercent($employee, $percent),
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
}
