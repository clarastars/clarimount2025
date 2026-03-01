<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\EmployeeDebt;
use App\Models\Leave;
use App\Models\SalaryRun;
use App\Models\SalaryRunItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryRunService
{
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

            // Calculate start and end dates for the month
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();

            foreach ($employees as $employee) {
                $grossSalary = ($employee->basic_salary ?? 0) + ($employee->allowances ?? 0);
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
                    $basicSalary = $employee->basic_salary ? (float) $employee->basic_salary : null;
                    $penaltyAmount = $this->calculatePenaltyAmount($penalty, $grossSalary, $dailyWage, $basicSalary);
                    $penaltiesTotal += $penaltyAmount;

                    $breakdown[] = [
                        'date' => $penalty->attendance_date->format('Y-m-d'),
                        'action_type' => $penalty->action_type,
                        'action_value' => $penalty->action_value,
                        'action_text' => $penalty->action_text,
                        'amount' => $penaltyAmount,
                    ];
                }

                // Get existing salary run item to preserve debt_deductions if exists
                $existingItem = SalaryRunItem::where('salary_run_id', $salaryRun->id)
                    ->where('employee_id', $employee->id)
                    ->first();

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
                    ->filter(fn (Leave $leave) => $leave->overlapsMonth($year, $month));

                $unpaidLeaveTotal = 0;
                foreach ($unpaidLeaves as $leave) {
                    $daysInMonth = $leave->daysInMonth($year, $month);
                    $amount = round($daysInMonth * $dailyWage, 2);
                    $unpaidLeaveTotal += $amount;
                    $breakdown[] = [
                        'date' => $leave->start_date->format('Y-m-d') . ' / ' . $leave->end_date->format('Y-m-d'),
                        'action_type' => 'unpaid_leave',
                        'action_value' => $daysInMonth,
                        'action_text' => 'Unpaid leave',
                        'amount' => $amount,
                    ];
                }

                $netSalary = $grossSalary - $penaltiesTotal - (float) $unpaidLeaveTotal - $debtDeductionsTotal;

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
                        'unpaid_leave_total' => $unpaidLeaveTotal,
                        'net_salary' => $netSalary,
                        'breakdown' => $breakdown,
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
                // Percentage of daily wage
                $percentage = $penalty->action_value ?? 0;
                return ($percentage / 100) * $dailyWage;

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
