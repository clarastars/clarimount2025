<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\EmployeeDebt;
use App\Models\EmployeeDeduction;
use App\Models\Leave;
use App\Models\SalaryRun;
use App\Models\SalaryRunItem;
use Illuminate\Support\Facades\DB;

class SalaryRunService
{
    public function __construct(
        private OperationalMonthService $operationalMonthService
    ) {}

    /**
     * Create or update salary run for a company, year, and month
     */
    public function createOrUpdateSalaryRun(int $companyId, int $year, int $month): SalaryRun
    {
        return DB::transaction(function () use ($companyId, $year, $month) {
            // Get or create salary run
            $salaryRun = SalaryRun::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ]
            );

            // Get all active employees for the company
            $employees = Employee::where('company_id', $companyId)
                ->where('employment_status', 'active')
                ->get();

            // Calculate start and end dates based on global operational month boundaries.
            $operationalRange = $this->operationalMonthService->resolveRangeForPayrollMonth($year, $month);
            $startDate = $operationalRange['start'];
            $endDate = $operationalRange['end'];

            foreach ($employees as $employee) {
                $existingItem = SalaryRunItem::where('salary_run_id', $salaryRun->id)
                    ->where('employee_id', $employee->id)
                    ->first();

                $breakdownExclusions = $existingItem?->breakdown_exclusions ?? [];

                $grossSalary = ($employee->basic_salary ?? 0) + ($employee->allowances ?? 0);
                $insuranceBase = (float) ($employee->basic_salary ?? 0) + (float) ($employee->allowance_housing ?? 0);
                $insuranceRate = (float) ($employee->social_insurance_deduction_rate ?? 0);
                $socialInsuranceDeductionTotal = $insuranceRate > 0
                    ? round(($insuranceBase * $insuranceRate) / 100, 2)
                    : 0.0;
                $dailyWage = $grossSalary / 30; // Simplified: using 30 days

                // Get approved penalties for this employee in this month
                $approvedPenalties = AttendancePenalty::where('employee_id', $employee->id)
                    ->where('approval_status', 'approved')
                    ->whereBetween('attendance_date', [
                        $startDate->format('Y-m-d'),
                        $endDate->format('Y-m-d')
                    ])
                    ->get();

                // Calculate total penalties
                $penaltiesTotal = 0;
                $breakdown = [];

                foreach ($approvedPenalties as $penalty) {
                    if ($this->isBreakdownExcluded($breakdownExclusions, 'attendance_penalty', $penalty->id)) {
                        continue;
                    }

                    $basicSalary = $employee->basic_salary ? (float) $employee->basic_salary : null;
                    $penaltyAmount = $this->calculatePenaltyAmount($penalty, $grossSalary, $dailyWage, $basicSalary);
                    $lateMinutesDeduction = (float) ($penalty->late_minutes_deduction_amount ?? 0);
                    $totalForPenalty = $penaltyAmount + $lateMinutesDeduction;
                    $penaltiesTotal += $totalForPenalty;
                    $penaltyCategory = $this->resolvePenaltyCategory((string) $penalty->violation_type);

                    $breakdown[] = [
                        'date' => \Carbon\Carbon::parse((string) $penalty->attendance_date)->format('Y-m-d'),
                        'violation_type' => $penalty->violation_type,
                        'penalty_category' => $penaltyCategory,
                        'action_type' => $penalty->action_type,
                        'action_value' => $penalty->action_value,
                        'action_text' => $penalty->action_text,
                        'amount' => $totalForPenalty,
                        'penalty_amount' => $penaltyAmount,
                        'late_minutes_deduction_amount' => $lateMinutesDeduction,
                        'attendance_penalty_id' => $penalty->id,
                        'source' => 'penalty',
                    ];
                }

                // Manual deductions (employee_deductions) for this month
                $manualDeductions = EmployeeDeduction::where('employee_id', $employee->id)
                    ->whereBetween('deduction_date', [
                        $startDate->format('Y-m-d'),
                        $endDate->format('Y-m-d'),
                    ])
                    ->orderBy('deduction_date')
                    ->get();

                foreach ($manualDeductions as $deduction) {
                    if ($this->isBreakdownExcluded($breakdownExclusions, 'employee_deduction', $deduction->id)) {
                        continue;
                    }

                    $amount = (float) $deduction->amount;
                    $penaltiesTotal += $amount;
                    $breakdown[] = [
                        'date' => \Carbon\Carbon::parse((string) $deduction->deduction_date)->format('Y-m-d'),
                        'action_type' => 'manual_deduction',
                        'deduction_type' => (string) $deduction->deduction_type,
                        'action_value' => null,
                        'action_text' => $deduction->reason,
                        'amount' => $amount,
                        'employee_deduction_id' => $deduction->id,
                        'source' => 'manual_deduction',
                    ];
                }

                $debtDeductions = $existingItem?->debt_deductions ?? [];
                $debtDeductionsTotal = 0;

                // Calculate total debt deductions
                if (is_array($debtDeductions)) {
                    foreach ($debtDeductions as $deduction) {
                        $debtDeductionsTotal += $deduction['amount'] ?? 0;
                    }
                }

                // Unpaid leave deduction: get leaves where is_paid = false overlapping this month
                $unpaidLeaves = Leave::where('employee_id', $employee->id)
                    ->where('is_paid', false)
                    ->get()
                    ->filter(fn (Leave $leave) => $leave->overlapsDateRange($startDate, $endDate));

                $unpaidLeaveTotal = 0;
                foreach ($unpaidLeaves as $leave) {
                    /** @var Leave $leave */
                    if ($this->isBreakdownExcluded($breakdownExclusions, 'unpaid_leave', $leave->id)) {
                        continue;
                    }

                    $daysInOperationalRange = $leave->daysInDateRange($startDate, $endDate);
                    $amount = round($daysInOperationalRange * $dailyWage, 2);
                    $unpaidLeaveTotal += $amount;
                    $breakdown[] = [
                        'date' => \Carbon\Carbon::parse((string) $leave->start_date)->format('Y-m-d')
                            . ' / '
                            . \Carbon\Carbon::parse((string) $leave->end_date)->format('Y-m-d'),
                        'action_type' => 'unpaid_leave',
                        'action_value' => $daysInOperationalRange,
                        'action_text' => 'Unpaid leave',
                        'amount' => $amount,
                        'leave_id' => $leave->id,
                        'source' => 'unpaid_leave',
                    ];
                }

                $netSalary = $grossSalary
                    - $penaltiesTotal
                    - (float) $unpaidLeaveTotal
                    - $debtDeductionsTotal
                    - $socialInsuranceDeductionTotal;

                // Upsert salary run item
                SalaryRunItem::updateOrCreate(
                    [
                        'salary_run_id' => $salaryRun->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'basic_salary' => $employee->basic_salary ?? 0,
                        'allowances' => $employee->allowances ?? 0,
                        'gross_salary' => $grossSalary,
                        'penalties_total' => $penaltiesTotal,
                        'social_insurance_deduction_total' => $socialInsuranceDeductionTotal,
                        'unpaid_leave_total' => $unpaidLeaveTotal,
                        'net_salary' => $netSalary,
                        'breakdown' => $breakdown,
                        'breakdown_exclusions' => $breakdownExclusions,
                        'debt_deductions' => $debtDeductions, // Preserve existing debt deductions
                    ]
                );
            }

            return $salaryRun->fresh(['items.employee']);
        });
    }

    /**
     * Calculate penalty amount based on action type
     *
     * @param AttendancePenalty $penalty
     * @param float $grossSalary
     * @param float $dailyWage
     * @param float|null $basicSalary
     * @return float
     */
    public function calculatePenaltyAmount(AttendancePenalty $penalty, float $grossSalary, float $dailyWage, ?float $basicSalary = null): float
    {
        switch ($penalty->action_type) {
            case 'warning':
                return 0;

            case 'deduction_percentage':
                // Percentage of basic daily wage (الخصم من الراتب الأساسي)
                $percentage = $penalty->action_value ?? 0;
                if ($basicSalary === null || $basicSalary <= 0) {
                    return 0;
                }
                $basicDailyWage = $basicSalary / 30;

                return ($percentage / 100) * $basicDailyWage;

            case 'deduction_days':
                // Days * daily wage
                // Check if we have separate gross and basic days
                if ($penalty->action_value_gross_days !== null || $penalty->action_value_basic_days !== null) {
                    $deduction = 0;
                    
                    // Deduct from gross salary
                    if ($penalty->action_value_gross_days !== null && $penalty->action_value_gross_days > 0) {
                        $deduction += $penalty->action_value_gross_days * $dailyWage;
                    }
                    
                    // Deduct from basic salary
                    if ($penalty->action_value_basic_days !== null && $penalty->action_value_basic_days > 0 && $basicSalary !== null) {
                        $basicDailyWage = $basicSalary / 30; // Assuming 30 days per month
                        $deduction += $penalty->action_value_basic_days * $basicDailyWage;
                    }
                    
                    return $deduction;
                }
                
                // Fallback to old behavior (action_value)
                $days = $penalty->action_value ?? 0;
                return $days * $dailyWage;

            case 'absent_deduction':
                // Absence penalty: deduct one day from gross salary + one day from basic salary for next day
                // For the absence day: deduct full day from gross salary
                $deduction = $dailyWage;
                
                // For the next day: deduct one day from basic salary
                if ($basicSalary !== null) {
                    $basicDailyWage = $basicSalary / 30; // Assuming 30 days per month
                    $deduction += $basicDailyWage;
                }
                
                return $deduction;

            case 'termination':
                // Not calculated in salary (or flag only)
                return 0;

            default:
                return 0;
        }
    }

    /**
     * @param  array<int, array{type?: string, id?: int}>|null  $exclusions
     */
    private function isBreakdownExcluded(?array $exclusions, string $type, int $id): bool
    {
        if ($exclusions === null || $exclusions === []) {
            return false;
        }

        foreach ($exclusions as $row) {
            if (($row['type'] ?? '') === $type && (int) ($row['id'] ?? 0) === $id) {
                return true;
            }
        }

        return false;
    }

    private function resolvePenaltyCategory(string $violationType): string
    {
        if ($violationType === 'absent_without_excuse') {
            return 'absence';
        }

        if (str_starts_with($violationType, 'late_')) {
            return 'late';
        }

        return 'other';
    }

    /**
     * Apply debt deductions to employee debts when salary run is finalized
     */
    public function applyDebtDeductions(SalaryRun $salaryRun): void
    {
        DB::transaction(function () use ($salaryRun) {
            $items = $salaryRun->items()->with('employee.debts')->get();

            foreach ($items as $item) {
                $debtDeductions = $item->debt_deductions ?? [];

                if (!is_array($debtDeductions) || empty($debtDeductions)) {
                    continue;
                }

                foreach ($debtDeductions as $deduction) {
                    $debtId = $deduction['debt_id'] ?? null;
                    $deductedAmount = $deduction['amount'] ?? 0;

                    if (!$debtId || $deductedAmount <= 0) {
                        continue;
                    }

                    $debt = EmployeeDebt::find($debtId);

                    if (!$debt || $debt->employee_id !== $item->employee_id) {
                        continue;
                    }

                    // Update debt amount
                    $newAmount = max(0, $debt->amount - $deductedAmount);
                    $debt->update(['amount' => $newAmount]);

                    // Optionally delete debt if amount becomes 0
                    if ($newAmount == 0) {
                        $debt->delete();
                    }
                }
            }
        });
    }
}
