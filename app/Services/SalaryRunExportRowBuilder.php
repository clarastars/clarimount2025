<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\SalaryRunItem;

class SalaryRunExportRowBuilder
{
    /**
     * Build a stable export snapshot from the salary run item and employee profile.
     * Used when finalizing a salary run so Excel exports never change afterward.
     *
     * @return array<string, float|string>
     */
    public function buildLiveSnapshot(SalaryRunItem $item, ?Employee $employee): array
    {
        $factor = $this->resolveExportSalaryFactor($item, $employee);

        $housing = $this->prorateAllowance(
            $employee && $employee->allowance_housing !== null ? (float) $employee->allowance_housing : 0.0,
            $factor,
        );
        $transport = $this->prorateAllowance(
            $employee && $employee->allowance_transportation !== null ? (float) $employee->allowance_transportation : 0.0,
            $factor,
        );
        $other = $this->prorateAllowance(
            $employee && $employee->allowance_other !== null ? (float) $employee->allowance_other : 0.0,
            $factor,
        );
        $food = $this->prorateAllowance(
            $employee && $employee->allowance_food !== null ? (float) $employee->allowance_food : 0.0,
            $factor,
        );
        $personalCar = $this->prorateAllowance(
            $employee && $employee->allowance_personal_car !== null ? (float) $employee->allowance_personal_car : 0.0,
            $factor,
        );

        $itemAllowances = $item->allowances !== null ? (float) $item->allowances : 0.0;
        $detailedSum = $housing + $transport + $other + $food + $personalCar;
        $additionalAllowances = $itemAllowances > $detailedSum ? round($itemAllowances - $detailedSum, 2) : 0.0;
        $additionalAllowances = round($additionalAllowances + $this->sumManualAdditions($item), 2);

        [
            $penalties,
            $trafficViolations,
            $absences,
            $attestations,
        ] = $this->splitTotalsForExportColumns($item);

        return $this->composeSnapshot(
            $this->resolveEmployeeName($employee),
            $item,
            $housing,
            $transport,
            $other,
            $food,
            $personalCar,
            $additionalAllowances,
            $penalties,
            $trafficViolations,
            $absences,
            $attestations,
        );
    }

    /**
     * Build a frozen snapshot using only persisted salary-run item data.
     * Used for legacy finalized runs that were finalized before snapshots existed.
     *
     * @return array<string, float|string>
     */
    public function buildFrozenSnapshotFromItem(SalaryRunItem $item, ?Employee $employee): array
    {
        $additionalAllowances = round(
            ($item->allowances !== null ? (float) $item->allowances : 0.0) + $this->sumManualAdditions($item),
            2,
        );

        [
            $penalties,
            $trafficViolations,
            $absences,
            $attestations,
        ] = $this->splitTotalsForExportColumns($item);

        return $this->composeSnapshot(
            $this->resolveEmployeeName($employee),
            $item,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            $additionalAllowances,
            $penalties,
            $trafficViolations,
            $absences,
            $attestations,
        );
    }

    /**
     * @param  array<string, float|string>  $snapshot
     * @return array<int, float|string>
     */
    public function snapshotToExcelRow(array $snapshot): array
    {
        return [
            (string) ($snapshot['employee_name'] ?? ''),
            $this->formatAmount($snapshot['basic_salary'] ?? null),
            $this->formatAmount($snapshot['housing'] ?? null),
            $this->formatAmount($snapshot['transportation'] ?? null),
            $this->formatAmount($snapshot['transfers'] ?? null),
            $this->formatAmount($snapshot['other'] ?? null),
            $this->formatAmount($snapshot['food'] ?? null),
            $this->formatAmount($snapshot['personal_car'] ?? null),
            $this->formatAmount($snapshot['additional_allowances'] ?? null),
            $this->formatAmount($snapshot['unpaid_leave'] ?? null),
            $this->formatAmount($snapshot['debts'] ?? null),
            $this->formatAmount($snapshot['traffic_violations'] ?? null),
            $this->formatAmount($snapshot['penalties'] ?? null),
            $this->formatAmount($snapshot['social_insurance'] ?? null),
            $this->formatAmount($snapshot['absences'] ?? null),
            $this->formatAmount($snapshot['attestations'] ?? null),
            $this->formatAmount($snapshot['net_salary'] ?? null),
        ];
    }

    /**
     * @return array<string, float|string>
     */
    private function composeSnapshot(
        string $employeeName,
        SalaryRunItem $item,
        float $housing,
        float $transport,
        float $other,
        float $food,
        float $personalCar,
        float $additionalAllowances,
        float $penalties,
        float $trafficViolations,
        float $absences,
        float $attestations,
    ): array {
        $debtTotal = 0.0;
        if (is_array($item->debt_deductions)) {
            foreach ($item->debt_deductions as $deduction) {
                $debtTotal += (float) ($deduction['amount'] ?? 0);
            }
        }

        return [
            'employee_name' => $employeeName,
            'basic_salary' => $item->basic_salary !== null ? (float) $item->basic_salary : 0.0,
            'housing' => $housing,
            'transportation' => $transport,
            'transfers' => 0.0,
            'other' => $other,
            'food' => $food,
            'personal_car' => $personalCar,
            'additional_allowances' => $additionalAllowances,
            'unpaid_leave' => $item->unpaid_leave_total !== null ? (float) $item->unpaid_leave_total : 0.0,
            'debts' => round($debtTotal, 2),
            'traffic_violations' => $trafficViolations,
            'penalties' => $penalties,
            'social_insurance' => $item->social_insurance_deduction_total !== null
                ? (float) $item->social_insurance_deduction_total
                : 0.0,
            'absences' => $absences,
            'attestations' => $attestations,
            'net_salary' => $item->net_salary !== null ? (float) $item->net_salary : 0.0,
        ];
    }

    private function resolveEmployeeName(?Employee $employee): string
    {
        if ($employee === null) {
            return '';
        }

        $parts = [
            trim((string) ($employee->first_name ?? '')),
            trim((string) ($employee->father_name ?? '')),
            trim((string) ($employee->last_name ?? '')),
        ];

        return implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function splitTotalsForExportColumns(SalaryRunItem $item): array
    {
        $penaltiesTotal = 0.0;
        $trafficViolationTotal = 0.0;
        $absenceTotal = 0.0;
        $attestationsTotal = 0.0;
        $breakdown = is_array($item->breakdown) ? $item->breakdown : [];

        foreach ($breakdown as $line) {
            if (! is_array($line)) {
                continue;
            }

            if (($line['source'] ?? null) === 'manual_addition') {
                continue;
            }

            if (($line['source'] ?? null) !== 'penalty') {
                if (($line['source'] ?? null) !== 'manual_deduction') {
                    continue;
                }

                $amount = (float) ($line['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $deductionType = (string) ($line['deduction_type'] ?? '');
                if ($deductionType === 'absence') {
                    $absenceTotal += $amount;
                } elseif ($deductionType === 'traffic_violation') {
                    $trafficViolationTotal += $amount;
                } elseif ($deductionType === 'attestations') {
                    $attestationsTotal += $amount;
                } else {
                    $penaltiesTotal += $amount;
                }

                continue;
            }

            $amount = (float) ($line['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $category = (string) ($line['penalty_category'] ?? '');
            $violationType = (string) ($line['violation_type'] ?? '');

            if ($category === 'absence' || $violationType === 'absent_without_excuse') {
                $absenceTotal += $amount;
            } else {
                $penaltiesTotal += $amount;
            }
        }

        return [
            round($penaltiesTotal, 2),
            round($trafficViolationTotal, 2),
            round($absenceTotal, 2),
            round($attestationsTotal, 2),
        ];
    }

    private function sumManualAdditions(SalaryRunItem $item): float
    {
        $total = 0.0;
        $breakdown = is_array($item->breakdown) ? $item->breakdown : [];

        foreach ($breakdown as $line) {
            if (! is_array($line) || ($line['source'] ?? null) !== 'manual_addition') {
                continue;
            }

            $amount = (float) ($line['amount'] ?? 0);
            if ($amount > 0) {
                $total += $amount;
            }
        }

        return round($total, 2);
    }

    private function resolveExportSalaryFactor(SalaryRunItem $item, ?Employee $employee): float
    {
        if ($employee === null) {
            return 1.0;
        }

        $fullBasic = (float) ($employee->basic_salary ?? 0);
        $itemBasic = $item->basic_salary !== null ? (float) $item->basic_salary : null;

        if ($fullBasic > 0 && $itemBasic !== null) {
            return min(1.0, max(0.0, $itemBasic / $fullBasic));
        }

        $fullAllowances = (float) ($employee->allowances ?? 0);
        $itemAllowances = $item->allowances !== null ? (float) $item->allowances : null;

        if ($fullAllowances > 0 && $itemAllowances !== null) {
            return min(1.0, max(0.0, $itemAllowances / $fullAllowances));
        }

        return 1.0;
    }

    private function prorateAllowance(float $fullAmount, float $factor): float
    {
        if ($fullAmount <= 0 || $factor <= 0) {
            return 0.0;
        }

        return round($fullAmount * $factor, 2);
    }

    private function formatAmount(mixed $value): float|string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $amount = (float) $value;

        return $amount > 0 ? $amount : '';
    }
}
