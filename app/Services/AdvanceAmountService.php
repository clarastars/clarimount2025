<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;

class AdvanceAmountService
{
    /** @var list<int> Fixed ladder offered for both the advance and the monthly deduction. */
    public const ALLOWED_AMOUNTS = [200, 500, 1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000];

    public function __construct(
        private ManualDeductionAmountService $deductionAmountService,
    ) {}

    public function grossMonthlyFor(Employee $employee): float
    {
        return round($this->deductionAmountService->grossMonthly($employee), 2);
    }

    /**
     * Full fixed ladder for the advance principal (always up to 10,000).
     *
     * @return list<int>
     */
    public function allAmountOptions(): array
    {
        return self::ALLOWED_AMOUNTS;
    }

    /**
     * Monthly deduction options are capped strictly below the gross monthly salary
     * so a single installment can never consume a full month of pay.
     *
     * @return list<int>
     */
    public function optionsForGross(float $gross): array
    {
        return array_values(array_filter(
            self::ALLOWED_AMOUNTS,
            static fn (int $amount): bool => $amount < $gross,
        ));
    }

    /**
     * @return list<int>
     */
    public function optionsForEmployee(Employee $employee): array
    {
        return $this->optionsForGross($this->grossMonthlyFor($employee));
    }

    /**
     * Monthly options for an employee, optionally capped by the chosen advance amount.
     *
     * @return list<int>
     */
    public function monthlyOptionsForEmployee(Employee $employee, ?float $advanceAmount = null): array
    {
        $options = $this->optionsForEmployee($employee);

        if ($advanceAmount === null || $advanceAmount <= 0) {
            return $options;
        }

        return array_values(array_filter(
            $options,
            static fn (int $amount): bool => $amount <= $advanceAmount,
        ));
    }

    public function isAllowedAmount(float $amount): bool
    {
        foreach (self::ALLOWED_AMOUNTS as $allowed) {
            if (abs($allowed - $amount) < 0.001) {
                return true;
            }
        }

        return false;
    }

    /**
     * Equal installments of the monthly deduction, with the remainder paid in the final month.
     *
     * @return array{months_count: int, schedule: list<array{month_index: int, amount: float}>, monthly_deduction: float}
     */
    public function buildRepaymentSchedule(float $amount, float $monthly): array
    {
        $amount = round($amount, 2);
        $monthly = round($monthly, 2);

        if ($amount <= 0 || $monthly <= 0) {
            return ['months_count' => 0, 'schedule' => [], 'monthly_deduction' => 0.0];
        }

        $monthly = min($monthly, $amount);

        $schedule = [];
        $remaining = $amount;
        $monthIndex = 0;

        while ($remaining > 0.001) {
            $monthIndex++;
            $installment = round(min($monthly, $remaining), 2);
            $schedule[] = [
                'month_index' => $monthIndex,
                'amount' => $installment,
            ];
            $remaining = round($remaining - $installment, 2);
        }

        return [
            'months_count' => count($schedule),
            'schedule' => $schedule,
            'monthly_deduction' => $monthly,
        ];
    }

    /**
     * Split an advance into a fixed number of installments (remainder in the final month).
     *
     * @return array{months_count: int, schedule: list<array{month_index: int, amount: float}>, monthly_deduction: float}
     */
    public function buildRepaymentScheduleForInstallments(float $amount, int $installments): array
    {
        $amount = round($amount, 2);
        $installments = max(1, $installments);

        if ($amount <= 0) {
            return ['months_count' => 0, 'schedule' => [], 'monthly_deduction' => 0.0];
        }

        $baseMonthly = round($amount / $installments, 2);

        if ($baseMonthly <= 0) {
            return ['months_count' => 0, 'schedule' => [], 'monthly_deduction' => 0.0];
        }

        $schedule = [];
        $allocated = 0.0;

        for ($index = 1; $index <= $installments; $index++) {
            if ($index === $installments) {
                $installment = round($amount - $allocated, 2);
            } else {
                $installment = $baseMonthly;
                $allocated = round($allocated + $installment, 2);
            }

            if ($installment <= 0) {
                continue;
            }

            $schedule[] = [
                'month_index' => count($schedule) + 1,
                'amount' => $installment,
            ];
        }

        $monthlyDeduction = $schedule[0]['amount'] ?? 0.0;

        return [
            'months_count' => count($schedule),
            'schedule' => $schedule,
            'monthly_deduction' => $monthlyDeduction,
        ];
    }
}
