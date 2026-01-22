<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendancePenalty;
use App\Models\Employee;
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
                    $penaltyAmount = $this->calculatePenaltyAmount($penalty, $grossSalary, $dailyWage);
                    $penaltiesTotal += $penaltyAmount;

                    $breakdown[] = [
                        'date' => $penalty->attendance_date->format('Y-m-d'),
                        'action_type' => $penalty->action_type,
                        'action_value' => $penalty->action_value,
                        'action_text' => $penalty->action_text,
                        'amount' => $penaltyAmount,
                    ];
                }

                $netSalary = $grossSalary - $penaltiesTotal;

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
                        'net_salary' => $netSalary,
                        'breakdown' => $breakdown,
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
     * @return float
     */
    public function calculatePenaltyAmount(AttendancePenalty $penalty, float $grossSalary, float $dailyWage): float
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
                $days = $penalty->action_value ?? 0;
                return $days * $dailyWage;

            case 'termination':
                // Not calculated in salary (or flag only)
                return 0;

            default:
                return 0;
        }
    }
}
