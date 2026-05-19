<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\EmployeeAddition;
use App\Models\EmployeeDebt;
use App\Models\EmployeeDeduction;
use App\Models\Leave;
use App\Models\SalaryRun;
use App\Models\SalaryRunItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryRunService
{
    private const TZ = 'Asia/Riyadh';

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

            // Operational month: penalties, manual deductions/additions, unpaid leave overlap (custom boundaries when set).
            $operationalRange = $this->operationalMonthService->resolveRangeForPayrollMonth($year, $month);
            $startDate = $operationalRange['start'];
            $endDate = $operationalRange['end'];

            // Calendar month: base salary, allowances, gross, insurance proration (always 1st–last day of payroll month).
            $calendarRange = $this->resolveCalendarMonthRange($year, $month);
            $calendarMonthStart = $calendarRange['start'];
            $calendarMonthEndDay = $calendarRange['end'];

            /** @var \Illuminate\Database\Eloquent\Collection<int, Employee> $employees */
            foreach ($employees as $employee) {
                $existingItem = SalaryRunItem::where('salary_run_id', $salaryRun->id)
                    ->where('employee_id', $employee->id)
                    ->first();

                $breakdownExclusions = $existingItem?->breakdown_exclusions ?? [];

                $fullBasicSalary = (float) ($employee->basic_salary ?? 0);
                $fullAllowances = (float) ($employee->allowances ?? 0);
                $fullGrossSalary = $fullBasicSalary + $fullAllowances;

                $proration = $this->resolveEmploymentProration(
                    $employee,
                    $calendarMonthStart,
                    $calendarMonthEndDay
                );
                $salaryFactor = $proration['factor'];

                $hireDateTz = $employee->hire_date !== null
                    ? Carbon::parse((string) $employee->hire_date, self::TZ)->startOfDay()
                    : null;
                $opStart = $startDate->copy()->timezone(self::TZ)->startOfDay();
                $attendanceRangeStart = ($hireDateTz !== null && $hireDateTz->gt($opStart))
                    ? $hireDateTz
                    : $opStart;

                $basicSalary = round($fullBasicSalary * $salaryFactor, 2);
                $allowances = round($fullAllowances * $salaryFactor, 2);
                $grossSalary = round($basicSalary + $allowances, 2);
                $insuranceBase = round((($employee->basic_salary ?? 0) + ($employee->allowance_housing ?? 0)) * $salaryFactor, 2);
                $insuranceRate = (float) ($employee->social_insurance_deduction_rate ?? 0);
                $socialInsuranceDeductionTotal = $insuranceRate > 0
                    ? round(($insuranceBase * $insuranceRate) / 100, 2)
                    : 0.0;
                $dailyWage = $fullGrossSalary / 30; // Keep a full daily wage; monthly proration is applied separately.

                // Get approved penalties for this employee in the operational payroll window (not calendar month).
                $approvedPenalties = AttendancePenalty::where('employee_id', $employee->id)
                    ->where('approval_status', 'approved')
                    ->whereBetween('attendance_date', [
                        $attendanceRangeStart->format('Y-m-d'),
                        $endDate->format('Y-m-d'),
                    ])
                    ->get();

                // Calculate total penalties
                $penaltiesTotal = 0;
                $breakdown = [];

                foreach ($approvedPenalties as $penalty) {
                    if ($this->isBreakdownExcluded($breakdownExclusions, 'attendance_penalty', $penalty->id)) {
                        continue;
                    }

                    $penaltyAmount = $this->calculatePenaltyAmount($penalty, $fullGrossSalary, $dailyWage, $fullBasicSalary);
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

                // Manual deductions (employee_deductions) in the operational payroll window.
                $manualDeductions = EmployeeDeduction::where('employee_id', $employee->id)
                    ->whereBetween('deduction_date', [
                        $attendanceRangeStart->format('Y-m-d'),
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

                // Manual additions (employee_additions) in the operational payroll window.
                $manualAdditions = EmployeeAddition::where('employee_id', $employee->id)
                    ->whereBetween('addition_date', [
                        $attendanceRangeStart->format('Y-m-d'),
                        $endDate->format('Y-m-d'),
                    ])
                    ->orderBy('addition_date')
                    ->get();

                $additionsTotal = 0.0;
                foreach ($manualAdditions as $addition) {
                    if ($this->isBreakdownExcluded($breakdownExclusions, 'employee_addition', $addition->id)) {
                        continue;
                    }

                    $amount = (float) $addition->amount;
                    $additionsTotal += $amount;
                    $breakdown[] = [
                        'date' => \Carbon\Carbon::parse((string) $addition->addition_date)->format('Y-m-d'),
                        'action_type' => 'manual_addition',
                        'addition_type' => (string) $addition->addition_type,
                        'action_value' => null,
                        'action_text' => $addition->reason,
                        'amount' => $amount,
                        'employee_addition_id' => $addition->id,
                        'source' => 'manual_addition',
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

                // Unpaid leave: overlap with operational payroll window only.
                $unpaidLeaves = Leave::where('employee_id', $employee->id)
                    ->where('is_paid', false)
                    ->get()
                    ->filter(fn (Leave $leave) => $leave->overlapsDateRange($attendanceRangeStart, $endDate));

                $unpaidLeaveTotal = 0;
                foreach ($unpaidLeaves as $leave) {
                    /** @var Leave $leave */
                    if ($this->isBreakdownExcluded($breakdownExclusions, 'unpaid_leave', $leave->id)) {
                        continue;
                    }

                    $daysInAttendanceRange = $leave->daysInDateRange($attendanceRangeStart, $endDate);
                    $amount = round($daysInAttendanceRange * $dailyWage, 2);
                    $unpaidLeaveTotal += $amount;
                    $breakdown[] = [
                        'date' => \Carbon\Carbon::parse((string) $leave->start_date)->format('Y-m-d')
                            .' / '
                            .\Carbon\Carbon::parse((string) $leave->end_date)->format('Y-m-d'),
                        'action_type' => 'unpaid_leave',
                        'action_value' => $daysInAttendanceRange,
                        'action_text' => 'Unpaid leave',
                        'amount' => $amount,
                        'leave_id' => $leave->id,
                        'source' => 'unpaid_leave',
                    ];
                }

                if ($salaryFactor < 1.0) {
                    $breakdown[] = [
                        'date' => $calendarMonthStart->format('Y-m-d').' / '.$calendarMonthEndDay->format('Y-m-d'),
                        'action_type' => 'employment_proration',
                        'action_value' => round($salaryFactor * 100, 2),
                        'action_text' => 'Salary prorated from hire date (calendar month)',
                        'amount' => $grossSalary,
                        'source' => 'employment_proration',
                    ];
                }

                $netSalary = $grossSalary
                    + $additionsTotal
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
                        'basic_salary' => $basicSalary,
                        'allowances' => $allowances,
                        'gross_salary' => $grossSalary,
                        'penalties_total' => $penaltiesTotal,
                        'additions_total' => $additionsTotal,
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
     * First and last calendar day of the payroll month (Asia/Riyadh). Used for salary amounts and hire proration only.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolveCalendarMonthRange(int $year, int $month): array
    {
        $base = Carbon::create($year, $month, 1, 0, 0, 0, self::TZ);

        return [
            'start' => $base->copy()->startOfMonth()->startOfDay(),
            'end' => $base->copy()->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * @return array{factor: float, effective_start: Carbon}
     */
    private function resolveEmploymentProration(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $periodStart = $periodStart->copy()->timezone(self::TZ)->startOfDay();
        $periodEndDay = $periodEnd->copy()->timezone(self::TZ)->startOfDay();

        if ($employee->hire_date === null) {
            return ['factor' => 1.0, 'effective_start' => $periodStart];
        }

        // Parse hire on the same calendar zone as operational payroll (avoids UTC vs Riyadh skew in diffInDays).
        $hireDate = Carbon::parse((string) $employee->hire_date, self::TZ)->startOfDay();

        if ($hireDate->lte($periodStart)) {
            return ['factor' => 1.0, 'effective_start' => $periodStart];
        }

        // Joined after this payroll period: no base salary payable in this period.
        if ($hireDate->gt($periodEndDay)) {
            return ['factor' => 0.0, 'effective_start' => $periodEndDay];
        }

        // Requested behavior: only prorate when employment age is less than one month.
        if ($hireDate->diffInDays($periodEndDay) >= 30) {
            return ['factor' => 1.0, 'effective_start' => $periodStart];
        }

        // Inclusive calendar days from hire through last day of period (both at start-of-day in TZ).
        $workedDays = $hireDate->diffInDays($periodEndDay) + 1;
        $factor = min(1.0, max(0.0, $workedDays / 30));

        return ['factor' => $factor, 'effective_start' => $hireDate];
    }

    /**
     * Calculate penalty amount based on action type
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

                if (! is_array($debtDeductions) || empty($debtDeductions)) {
                    continue;
                }

                foreach ($debtDeductions as $deduction) {
                    $debtId = $deduction['debt_id'] ?? null;
                    $deductedAmount = $deduction['amount'] ?? 0;

                    if (! $debtId || $deductedAmount <= 0) {
                        continue;
                    }

                    $debt = EmployeeDebt::find($debtId);

                    if (! $debt || $debt->employee_id !== $item->employee_id) {
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

    public function finalizeSalaryRun(SalaryRun $salaryRun): void
    {
        if ($salaryRun->status === 'finalized') {
            return;
        }

        DB::transaction(function () use ($salaryRun) {
            $this->applyDebtDeductions($salaryRun);

            $salaryRun->update([
                'status' => 'finalized',
            ]);
        });
    }
}
