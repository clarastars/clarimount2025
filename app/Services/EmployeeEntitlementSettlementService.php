<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDebt;
use App\Models\EmployeeEntitlementSettlement;
use App\Models\Leave;
use App\Models\SalaryRunItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeEntitlementSettlementService
{
    private const TZ = 'Asia/Riyadh';

    public function __construct(
        private ManualDeductionAmountService $amountService,
        private LeaveAccrualService $leaveAccrualService,
    ) {}

    /**
     * @param  array{
     *     end_of_service_bonus?: float|int|string|null,
     *     travel_tickets?: float|int|string|null,
     *     due_commissions?: float|int|string|null,
     *     other_dues?: float|int|string|null,
     *     custody_deduction?: float|int|string|null,
     *     excess_leave_deduction?: float|int|string|null,
     *     social_insurance_deduction?: float|int|string|null,
     *     notes?: string|null,
     * }  $manualInput
     * @return array<string, mixed>
     */
    public function buildPreview(Employee $employee, Carbon|string $settlementDate, array $manualInput = []): array
    {
        $employee->loadMissing(['nationality', 'department', 'company', 'debts']);
        $employee->append('remaining_annual_leave_balance');

        $settlementDate = $this->parseDate($settlementDate);
        $hireDate = $this->parseDate($employee->hire_date);
        $lastSettlementDate = $this->resolveLastSettlementDate($employee);
        $serviceDays = $this->calculateServiceDays($hireDate, $settlementDate);
        $salaryDues = $this->calculateSalaryDues($employee, $settlementDate);
        $annualLeave = $this->calculateAnnualLeaveDues($employee, $settlementDate);
        $usedLeave = $this->calculateUsedAnnualLeaveDeduction($employee, $settlementDate);
        $advances = $this->calculateAdvancesTotal($employee);

        $manual = $this->normalizeManualInput($manualInput);

        $totalDues = round(
            $manual['end_of_service_bonus']
            + $manual['travel_tickets']
            + $manual['due_commissions']
            + $salaryDues['amount']
            + $annualLeave['amount']
            + $manual['other_dues'],
            2
        );

        $totalDeductions = round(
            $advances
            + $manual['custody_deduction']
            + $manual['excess_leave_deduction']
            + $manual['social_insurance_deduction']
            + $usedLeave['amount'],
            2
        );

        $departmentLabel = $employee->department instanceof \App\Models\Department
            ? $employee->department->name
            : ($employee->getAttributes()['department'] ?? null);

        return [
            'settlement_date' => $settlementDate->toDateString(),
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
                'nationality' => $employee->nationality?->name_ar ?: $employee->nationality?->name_en,
                'department' => $departmentLabel,
                'company_name' => $employee->company?->name_ar ?: $employee->company?->name_en,
                'hire_date' => $hireDate?->toDateString(),
                'annual_leave_balance' => (int) ($employee->annual_leave_balance ?? 0),
            ],
            'last_settlement_date' => $lastSettlementDate?->toDateString(),
            'service_days' => $serviceDays,
            'salary_breakdown' => [
                'basic_salary' => round((float) ($employee->basic_salary ?? 0), 2),
                'allowance_housing' => round((float) ($employee->allowance_housing ?? 0), 2),
                'allowance_transportation' => round((float) ($employee->allowance_transportation ?? 0), 2),
                'allowance_food' => round((float) ($employee->allowance_food ?? 0), 2),
                'allowance_personal_car' => round((float) ($employee->allowance_personal_car ?? 0), 2),
                'allowance_other' => round((float) ($employee->allowance_other ?? 0), 2),
                'allowances' => round((float) ($employee->allowances ?? 0), 2),
                'gross_salary' => round($this->amountService->grossMonthly($employee), 2),
                'gross_daily_wage' => $this->amountService->grossDailyWage($employee),
            ],
            'dues' => [
                'end_of_service_bonus' => $manual['end_of_service_bonus'],
                'travel_tickets' => $manual['travel_tickets'],
                'due_commissions' => $manual['due_commissions'],
                'salary_dues' => $salaryDues['amount'],
                'salary_unpaid_days' => $salaryDues['days'],
                'salary_unpaid_from' => $salaryDues['from'],
                'salary_unpaid_to' => $salaryDues['to'],
                'annual_leave_dues' => $annualLeave['amount'],
                'remaining_leave_days' => $annualLeave['days'],
                'other_dues' => $manual['other_dues'],
                'total_dues' => $totalDues,
            ],
            'deductions' => [
                'advances' => $advances,
                'custody' => $manual['custody_deduction'],
                'excess_leave' => $manual['excess_leave_deduction'],
                'social_insurance' => $manual['social_insurance_deduction'],
                'used_annual_leave_days' => $usedLeave['days'],
                'used_annual_leave_deduction' => $usedLeave['amount'],
                'total_deductions' => $totalDeductions,
            ],
            'net_due' => round($totalDues - $totalDeductions, 2),
            'notes' => $manual['notes'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Employee $employee, array $payload, ?int $createdBy = null): EmployeeEntitlementSettlement
    {
        $preview = $this->buildPreview(
            $employee,
            (string) $payload['settlement_date'],
            $payload,
        );

        return EmployeeEntitlementSettlement::query()->create([
            'employee_id' => $employee->id,
            'created_by' => $createdBy,
            'settlement_date' => $preview['settlement_date'],
            'reason' => trim((string) $payload['reason']),
            'last_settlement_date' => $preview['last_settlement_date'],
            'service_days' => $preview['service_days'],
            'basic_salary' => $preview['salary_breakdown']['basic_salary'],
            'allowances' => $preview['salary_breakdown']['allowances'],
            'gross_salary' => $preview['salary_breakdown']['gross_salary'],
            'remaining_leave_days' => $preview['dues']['remaining_leave_days'],
            'salary_unpaid_days' => $preview['dues']['salary_unpaid_days'],
            'used_annual_leave_days' => $preview['deductions']['used_annual_leave_days'],
            'end_of_service_bonus' => $preview['dues']['end_of_service_bonus'],
            'travel_tickets' => $preview['dues']['travel_tickets'],
            'due_commissions' => $preview['dues']['due_commissions'],
            'salary_dues' => $preview['dues']['salary_dues'],
            'annual_leave_dues' => $preview['dues']['annual_leave_dues'],
            'other_dues' => $preview['dues']['other_dues'],
            'total_dues' => $preview['dues']['total_dues'],
            'advances_deduction' => $preview['deductions']['advances'],
            'custody_deduction' => $preview['deductions']['custody'],
            'excess_leave_deduction' => $preview['deductions']['excess_leave'],
            'social_insurance_deduction' => $preview['deductions']['social_insurance'],
            'used_annual_leave_deduction' => $preview['deductions']['used_annual_leave_deduction'],
            'total_deductions' => $preview['deductions']['total_deductions'],
            'net_due' => $preview['net_due'],
            'notes' => $preview['notes'],
            'status' => $payload['status'] ?? EmployeeEntitlementSettlement::STATUS_PENDING,
            'reviewed_by' => $payload['reviewed_by'] ?? null,
            'reviewed_at' => $payload['reviewed_at'] ?? null,
            'review_notes' => $payload['review_notes'] ?? null,
        ]);
    }

    public function resolveLastSettlementDate(Employee $employee): ?Carbon
    {
        $previous = EmployeeEntitlementSettlement::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeEntitlementSettlement::STATUS_APPROVED)
            ->orderByDesc('settlement_date')
            ->orderByDesc('id')
            ->value('settlement_date');

        if ($previous !== null) {
            return $this->parseDate($previous);
        }

        if ($employee->hire_date === null) {
            return null;
        }

        return $this->parseDate($employee->hire_date);
    }

    public function calculateServiceDays(?Carbon $hireDate, Carbon $settlementDate): int
    {
        if ($hireDate === null || $hireDate->gt($settlementDate)) {
            return 0;
        }

        return (int) $hireDate->diffInDays($settlementDate) + 1;
    }

    /**
     * @return array{days: float, amount: float, from: ?string, to: ?string}
     */
    public function calculateSalaryDues(Employee $employee, Carbon $settlementDate): array
    {
        $grossDaily = $this->amountService->grossDailyWage($employee);
        if ($grossDaily === null) {
            return ['days' => 0.0, 'amount' => 0.0, 'from' => null, 'to' => null];
        }

        $unpaidStart = $this->resolveUnpaidSalaryStartDate($employee, $settlementDate);
        if ($unpaidStart === null || $unpaidStart->gt($settlementDate)) {
            return [
                'days' => 0.0,
                'amount' => 0.0,
                'from' => $unpaidStart?->toDateString(),
                'to' => $settlementDate->toDateString(),
            ];
        }

        $totalDays = $this->countInclusiveDays($unpaidStart, $settlementDate);
        $unpaidLeaveDays = $this->calculateUnpaidLeaveDaysInPeriod($employee, $unpaidStart, $settlementDate);
        $days = max(0.0, round($totalDays - $unpaidLeaveDays, 2));
        $amount = $this->amountService->fromGrossDays($employee, $days) ?? 0.0;

        return [
            'days' => $days,
            'amount' => $amount,
            'from' => $unpaidStart->toDateString(),
            'to' => $settlementDate->toDateString(),
        ];
    }

    public function calculateUnpaidLeaveDaysInPeriod(Employee $employee, Carbon $periodStart, Carbon $periodEnd): float
    {
        if ($employee->getKey() === null) {
            return 0.0;
        }

        $periodStart = $this->parseDate($periodStart) ?? $periodStart->copy()->timezone(self::TZ)->startOfDay();
        $periodEnd = $this->parseDate($periodEnd) ?? $periodEnd->copy()->timezone(self::TZ)->startOfDay();

        $total = 0.0;

        foreach ($employee->leaves()->where('is_paid', false)->get() as $leave) {
            if (! $leave->overlapsDateRange($periodStart, $periodEnd)) {
                continue;
            }

            $leaveStart = $this->parseDate($leave->start_date);
            $leaveEnd = $this->parseDate($leave->end_date);

            if ($leaveStart === null || $leaveEnd === null) {
                continue;
            }

            // Use the recorded leave days when the full leave sits inside the salary window.
            // This matches the approved request (e.g. 7 days) and avoids date-cast drift.
            if ($leaveStart->gte($periodStart) && $leaveEnd->lte($periodEnd)) {
                $total += max(0.0, (float) $leave->days);

                continue;
            }

            $total += $this->countInclusiveDays(
                $leaveStart->gt($periodStart) ? $leaveStart : $periodStart,
                $leaveEnd->lt($periodEnd) ? $leaveEnd : $periodEnd,
            );
        }

        return round($total, 2);
    }

    /**
     * Pay out annual leave earned through the settlement date only.
     * Later accrued days stay on the employee balance (typical for a back-dated settlement).
     *
     * @return array{days: float, amount: float}
     */
    public function calculateAnnualLeaveDues(Employee $employee, Carbon $settlementDate): array
    {
        $accruedAsOf = $this->leaveAccrualService->projectedAccruedBalanceAsOf($employee, $settlementDate);
        $previouslyPaid = $this->previouslySettledLeaveDays($employee);
        $days = max(0, round($accruedAsOf - $previouslyPaid, 2));
        $amount = $this->amountService->fromGrossDays($employee, $days) ?? 0.0;

        return [
            'days' => $days,
            'amount' => $amount,
        ];
    }

    public function previouslySettledLeaveDays(Employee $employee): float
    {
        if ($employee->getKey() === null) {
            return 0.0;
        }

        return round((float) EmployeeEntitlementSettlement::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeEntitlementSettlement::STATUS_APPROVED)
            ->sum('remaining_leave_days'), 2);
    }

    /**
     * Deduct leave days that were actually consumed on or before the settlement date.
     * Approved future leave is excluded — it is paid via annual leave dues, not deducted.
     *
     * @return array{days: float, amount: float}
     */
    public function calculateUsedAnnualLeaveDeduction(Employee $employee, Carbon $settlementDate): array
    {
        $legacyUsed = (float) ($employee->leave_days_used ?? 0);
        $elapsedFromRecords = $this->calculateElapsedDeductibleLeaveDays($employee, $settlementDate);
        $days = round($legacyUsed + $elapsedFromRecords, 2);
        $amount = $this->amountService->fromGrossDays($employee, $days) ?? 0.0;

        return [
            'days' => $days,
            'amount' => $amount,
        ];
    }

    public function calculateElapsedDeductibleLeaveDays(Employee $employee, Carbon $settlementDate): float
    {
        $total = 0.0;

        foreach ($employee->leaves()->where('deduct_from_balance', true)->get() as $leave) {
            $leaveStart = $this->parseDate($leave->start_date);
            $leaveEnd = $this->parseDate($leave->end_date);

            if ($leaveStart === null || $leaveEnd === null || $leaveStart->gt($settlementDate)) {
                continue;
            }

            $elapsedEnd = $leaveEnd->gt($settlementDate) ? $settlementDate : $leaveEnd;
            $total += (float) $leave->daysInDateRange($leaveStart, $elapsedEnd);
        }

        return round($total, 2);
    }

    public function calculateAdvancesTotal(Employee $employee): float
    {
        return round((float) $employee->debts()->sum('amount'), 2);
    }

    public function applyApprovedSettlementAdjustments(EmployeeEntitlementSettlement $settlement): void
    {
        if (! $settlement->isApproved()) {
            return;
        }

        DB::transaction(function () use ($settlement): void {
            $lockedSettlement = EmployeeEntitlementSettlement::query()
                ->lockForUpdate()
                ->findOrFail($settlement->id);

            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($lockedSettlement->employee_id);

            $settlementDate = $this->parseDate($lockedSettlement->settlement_date)?->endOfDay();
            $settlementCreatedAt = $lockedSettlement->created_at?->copy();

            $paidLeaveDays = max(0.0, round((float) $lockedSettlement->remaining_leave_days, 2));
            $currentAccrued = round((float) ($employee->leave_accrued_balance ?? 0), 2);
            $remainingAccrued = max(0.0, round($currentAccrued - $paidLeaveDays, 2));

            $employee->update([
                'leave_accrued_balance' => $remainingAccrued,
                'leave_days_used' => 0,
            ]);

            if ($settlementDate !== null) {
                Leave::query()
                    ->where('employee_id', $employee->id)
                    ->where('deduct_from_balance', true)
                    ->whereDate('start_date', '<=', $settlementDate->toDateString())
                    ->update(['deduct_from_balance' => false]);
            }

            $debtQuery = EmployeeDebt::query()
                ->where('employee_id', $employee->id);

            if ($settlementCreatedAt !== null) {
                $debtQuery->where('created_at', '<=', $settlementCreatedAt);
            }

            $debtQuery->delete();
        });
    }

    public function resolveUnpaidSalaryStartDate(Employee $employee, Carbon $settlementDate): ?Carbon
    {
        $hireDate = $employee->hire_date !== null
            ? $this->parseDate($employee->hire_date)
            : null;

        $lastRunItem = SalaryRunItem::query()
            ->where('employee_id', $employee->id)
            ->whereHas('salaryRun', function ($query) use ($employee): void {
                $query
                    ->where('company_id', $employee->company_id)
                    ->where('status', 'finalized');
            })
            ->join('salary_runs', 'salary_runs.id', '=', 'salary_run_items.salary_run_id')
            ->orderByDesc('salary_runs.year')
            ->orderByDesc('salary_runs.month')
            ->select('salary_runs.year', 'salary_runs.month')
            ->get()
            ->first(function ($run) use ($settlementDate): bool {
                // Only treat a month as paid once its calendar month has fully ended
                // on or before the settlement date (avoids mid-month zero dues).
                $monthEnd = Carbon::createFromDate(
                    (int) $run->year,
                    (int) $run->month,
                    1,
                    self::TZ,
                )->endOfMonth()->startOfDay();

                return $monthEnd->lte($settlementDate);
            });

        if ($lastRunItem !== null) {
            $unpaidStart = Carbon::createFromDate(
                (int) $lastRunItem->year,
                (int) $lastRunItem->month,
                1,
                self::TZ,
            )->addMonth()->startOfMonth();

            if ($hireDate !== null && $unpaidStart->lt($hireDate)) {
                return $hireDate->copy();
            }

            return $unpaidStart;
        }

        if ($hireDate !== null) {
            return $hireDate->copy();
        }

        // No hire date and no finalized payroll: settle the current settlement month only.
        return $settlementDate->copy()->startOfMonth();
    }

    public function countInclusiveDays(Carbon $start, Carbon $end): float
    {
        $start = $this->parseDate($start) ?? $start->copy()->timezone(self::TZ)->startOfDay();
        $end = $this->parseDate($end) ?? $end->copy()->timezone(self::TZ)->startOfDay();

        if ($start->gt($end)) {
            return 0.0;
        }

        return (float) ($start->diffInDays($end) + 1);
    }

    /**
     * @param  array<string, mixed>  $manualInput
     * @return array{
     *     end_of_service_bonus: float,
     *     travel_tickets: float,
     *     due_commissions: float,
     *     other_dues: float,
     *     custody_deduction: float,
     *     excess_leave_deduction: float,
     *     social_insurance_deduction: float,
     *     notes: ?string
     * }
     */
    private function normalizeManualInput(array $manualInput): array
    {
        return [
            'end_of_service_bonus' => $this->normalizeMoney($manualInput['end_of_service_bonus'] ?? 0),
            'travel_tickets' => $this->normalizeMoney($manualInput['travel_tickets'] ?? 0),
            'due_commissions' => $this->normalizeMoney($manualInput['due_commissions'] ?? 0),
            'other_dues' => $this->normalizeMoney($manualInput['other_dues'] ?? 0),
            'custody_deduction' => $this->normalizeMoney($manualInput['custody_deduction'] ?? 0),
            'excess_leave_deduction' => $this->normalizeMoney($manualInput['excess_leave_deduction'] ?? 0),
            'social_insurance_deduction' => $this->normalizeMoney($manualInput['social_insurance_deduction'] ?? 0),
            'notes' => isset($manualInput['notes']) && trim((string) $manualInput['notes']) !== ''
                ? trim((string) $manualInput['notes'])
                : null,
        ];
    }

    private function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round(max(0, (float) $value), 2);
    }

    private function parseDate(Carbon|string|null $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->timezone(self::TZ)->startOfDay();
        }

        $raw = trim((string) $value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $matches) === 1) {
            return Carbon::createFromFormat('Y-m-d', $matches[1], self::TZ)->startOfDay();
        }

        return Carbon::parse($raw, self::TZ)->startOfDay();
    }
}
