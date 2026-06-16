<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\LaborLawRule;
use App\Models\ZkDailyAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendancePenaltyService
{
    public function __construct(
        private OperationalMonthService $operationalMonthService,
        private AttendancePenaltyAutoApprovalService $autoApprovalService,
    ) {}

    /**
     * Calculate and create penalty for an attendance record
     *
     * @param  string  $attendanceDate  Date in Y-m-d format
     */
    public function calculatePenalty(int $employeeId, string $attendanceDate, int $lateMinutes): ?AttendancePenalty
    {
        // Skip if no late minutes
        if ($lateMinutes <= 0) {
            return null;
        }

        // Determine violation type based on late minutes
        $violationType = $this->determineViolationType($lateMinutes);
        if (! $violationType) {
            return null;
        }

        // Repeat number within payroll period (operational month when configured)
        $repeatNumber = $this->calculateRepeatNumber($employeeId, $violationType, $attendanceDate);

        // Get the rule for this violation type and repeat number
        $rule = LaborLawRule::byViolationType($violationType)
            ->byRepeatNumber($repeatNumber)
            ->first();

        if (! $rule) {
            Log::warning('No labor law rule found', [
                'violation_type' => $violationType,
                'repeat_number' => $repeatNumber,
            ]);

            return null;
        }

        // Generate action text
        $actionText = $this->generateActionText(
            $rule->action_type,
            $rule->action_value,
            $rule->action_value_gross_days,
            $rule->action_value_basic_days
        );

        // Calculate late minutes deduction amount: late_minutes × minute_rate (from basic salary, work days, shift)
        $lateMinutesDeductionAmount = $this->calculateLateMinutesDeductionAmount($employeeId, $lateMinutes);

        // Use updateOrCreate so that when re-running month sync (--month), repeat_number and
        // rule-based fields are recalculated in chronological order (day 2 = first, day 5 = second).
        $penalty = AttendancePenalty::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'attendance_date' => $attendanceDate,
            ],
            [
                'late_minutes' => $lateMinutes,
                'violation_type' => $violationType,
                'repeat_number' => $repeatNumber,
                'action_type' => $rule->action_type,
                'action_value' => $rule->action_value,
                'action_value_gross_days' => $rule->action_value_gross_days,
                'action_value_basic_days' => $rule->action_value_basic_days,
                'action_text' => $actionText,
                'reason_text' => $rule->reason_text,
                'late_minutes_deduction_amount' => $lateMinutesDeductionAmount,
            ]
        );

        return $this->autoApprovalService->applyForPenalty($penalty);
    }

    /**
     * Calculate and create penalty for a daily attendance record (e.g. from iClock API or device ingest).
     * Uses employee shift to compute late minutes and then create/update penalty.
     *
     * @param  string  $attDate  Date in Y-m-d format
     * @return int|null Late minutes (when a penalty is created/updated), otherwise null
     */
    public function calculatePenaltyForDailyAttendance(ZkDailyAttendance $attendance, string $attDate): ?int
    {
        $employee = Employee::with('shift.workdays')->where('fingerprint_device_id', $attendance->device_pin)->first();

        if (! $employee || ! $employee->shift) {
            return null;
        }

        $attDateCarbon = Carbon::parse($attDate, 'Asia/Riyadh');
        $weekday = $attDateCarbon->dayOfWeek;

        $workdays = $employee->shift->workdays()->where('is_workday', true)->pluck('weekday')->toArray();
        if (! in_array($weekday, $workdays)) {
            return null;
        }

        if (! $attendance->first_punch) {
            return null;
        }

        $expectedStartTime = $employee->shift->effectiveStartTimeStringForWeekday($weekday);
        $expectedStart = Carbon::parse($attDate.' '.$expectedStartTime, 'Asia/Riyadh');
        $firstPunch = Carbon::parse($attendance->first_punch)->setTimezone('Asia/Riyadh');
        $actualLateMinutes = (int) round(($firstPunch->timestamp - $expectedStart->timestamp) / 60);
        $graceMinutes = (int) ($employee->shift->grace_minutes ?? 0);
        $lateMinutes = max(0, $actualLateMinutes - $graceMinutes);

        if ($lateMinutes > 0) {
            $this->calculatePenalty($employee->id, $attDate, $lateMinutes);

            return $lateMinutes;
        }

        return null;
    }

    /**
     * Get penalty for a specific attendance record
     *
     * @param  string  $attendanceDate  Date in Y-m-d format
     */
    public function getPenaltyForAttendance(int $employeeId, string $attendanceDate): ?AttendancePenalty
    {
        return AttendancePenalty::where('employee_id', $employeeId)
            ->where('attendance_date', $attendanceDate)
            ->first();
    }

    /**
     * Determine violation type based on late minutes
     */
    private function determineViolationType(int $lateMinutes): ?string
    {
        if ($lateMinutes >= 0 && $lateMinutes < 15) {
            return 'late_0_15';
        } elseif ($lateMinutes >= 15 && $lateMinutes < 30) {
            return 'late_15_30';
        } elseif ($lateMinutes >= 30 && $lateMinutes < 60) {
            return 'late_30_60';
        } elseif ($lateMinutes >= 60) {
            return 'late_over_60';
        }

        return null;
    }

    /**
     * Calculate repeat number for a violation type within the same payroll period:
     * custom operational month boundaries when configured; otherwise calendar month.
     *
     * @param  string  $attendanceDate  Date in Y-m-d format
     */
    private function calculateRepeatNumber(int $employeeId, string $violationType, string $attendanceDate): int
    {
        $range = $this->operationalMonthService->resolveOperationalMonthRangeContainingDate($attendanceDate);
        $periodStart = $range['start']->format('Y-m-d');
        $periodEnd = $range['end']->format('Y-m-d');

        // Count only previous penalties (strictly before this date) in the same payroll period.
        $count = AttendancePenalty::forEmployee($employeeId)
            ->byViolationType($violationType)
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->whereDate('attendance_date', '<', $attendanceDate)
            ->where('approval_status', '!=', 'rejected')
            ->count();

        // Actual repeat number for this occurrence
        $repeatNumber = $count + 1;

        // Find the maximum defined repeat_number for this violation_type in labor law rules
        $maxDefinedRepeat = (int) (LaborLawRule::byViolationType($violationType)->max('repeat_number') ?? 1);
        $maxDefinedRepeat = max(1, $maxDefinedRepeat);

        // If actual repeat exceeds the maximum defined, cycle back from 1 (e.g. 1,2,1,2,1,2…)
        return (int) ((($repeatNumber - 1) % $maxDefinedRepeat) + 1);
    }

    /**
     * Re-sequence repeat_number for one employee/violation_type/payroll period after state changes
     * (e.g. when one penalty is rejected) so later rows shift correctly.
     * Rejected rows are excluded from the counting sequence.
     * Payroll period follows custom operational month when configured.
     *
     * @param  string  $anchorAttendanceDateYmd  Any Y-m-d inside the period to re-sequence (e.g. rejected penalty date)
     */
    public function resequenceMonthlyPenaltiesAfterRejection(
        int $employeeId,
        string $violationType,
        string $anchorAttendanceDateYmd
    ): void {
        $range = $this->operationalMonthService->resolveOperationalMonthRangeContainingDate($anchorAttendanceDateYmd);
        $periodStart = $range['start']->format('Y-m-d');
        $periodEnd = $range['end']->format('Y-m-d');

        DB::transaction(function () use ($employeeId, $violationType, $periodStart, $periodEnd): void {
            /** @var \Illuminate\Database\Eloquent\Collection<int, AttendancePenalty> $activePenalties */
            $activePenalties = AttendancePenalty::query()
                ->forEmployee($employeeId)
                ->byViolationType($violationType)
                ->whereBetween('attendance_date', [$periodStart, $periodEnd])
                ->where('approval_status', '!=', 'rejected')
                ->orderBy('attendance_date')
                ->orderBy('id')
                ->get();

            if ($activePenalties->isEmpty()) {
                return;
            }

            $maxDefinedRepeat = (int) (LaborLawRule::byViolationType($violationType)->max('repeat_number') ?? 1);
            $maxDefinedRepeat = max(1, $maxDefinedRepeat);

            $seq = 0;
            foreach ($activePenalties as $penalty) {
                $seq++;
                $repeatNumber = (int) ((($seq - 1) % $maxDefinedRepeat) + 1);

                $rule = LaborLawRule::byViolationType($violationType)
                    ->byRepeatNumber($repeatNumber)
                    ->first();

                if (! $rule) {
                    continue;
                }

                $actionText = $this->generateActionText(
                    $rule->action_type,
                    $rule->action_value,
                    $rule->action_value_gross_days,
                    $rule->action_value_basic_days
                );

                $penalty->update([
                    'repeat_number' => $repeatNumber,
                    'action_type' => $rule->action_type,
                    'action_value' => $rule->action_value,
                    'action_value_gross_days' => $rule->action_value_gross_days,
                    'action_value_basic_days' => $rule->action_value_basic_days,
                    'action_text' => $actionText,
                    'reason_text' => $rule->reason_text,
                ]);
            }
        });
    }

    /**
     * Calculate and create penalty for absence without permission
     *
     * @deprecated Use calculateAbsenceWithoutExcusePenalty instead
     *
     * @param  string  $attendanceDate  Date in Y-m-d format
     */
    public function calculateAbsencePenalty(int $employeeId, string $attendanceDate): ?AttendancePenalty
    {
        // Use absent_without_excuse as they are the same
        return $this->calculateAbsenceWithoutExcusePenalty($employeeId, $attendanceDate);
    }

    /**
     * Calculate and create penalty for absence without excuse
     *
     * @param  string  $attendanceDate  Date in Y-m-d format
     */
    public function calculateAbsenceWithoutExcusePenalty(int $employeeId, string $attendanceDate): ?AttendancePenalty
    {
        $violationType = 'absent_without_excuse';

        // Repeat number within payroll period (operational month when configured)
        $repeatNumber = $this->calculateRepeatNumber($employeeId, $violationType, $attendanceDate);

        return $this->createAbsencePenaltyWithRepeatNumber($employeeId, $attendanceDate, $repeatNumber);
    }

    /**
     * Calculate and create penalty for absence without excuse with specific repeat number
     *
     * @param  string  $attendanceDate  Date in Y-m-d format
     */
    public function calculateAbsenceWithoutExcusePenaltyWithRepeatNumber(int $employeeId, string $attendanceDate, int $repeatNumber): ?AttendancePenalty
    {
        return $this->createAbsencePenaltyWithRepeatNumber($employeeId, $attendanceDate, $repeatNumber);
    }

    /**
     * Create absence penalty with specific repeat number
     *
     * @param  string  $attendanceDate  Date in Y-m-d format
     */
    private function createAbsencePenaltyWithRepeatNumber(int $employeeId, string $attendanceDate, int $repeatNumber): ?AttendancePenalty
    {
        $violationType = 'absent_without_excuse';

        // Find the maximum defined repeat_number for this violation_type in labor law rules
        $maxDefinedRepeat = (int) (LaborLawRule::byViolationType($violationType)->max('repeat_number') ?? 1);
        $maxDefinedRepeat = max(1, $maxDefinedRepeat);

        // If repeat exceeds the maximum defined, cycle back from 1 (e.g. 1,2,1,2,1,2…)
        $repeatNumber = (int) ((($repeatNumber - 1) % $maxDefinedRepeat) + 1);

        // Get the rule for this violation type and repeat number
        $rule = LaborLawRule::byViolationType($violationType)
            ->byRepeatNumber($repeatNumber)
            ->first();

        if (! $rule) {
            Log::warning('No labor law rule found for absence without excuse', [
                'violation_type' => $violationType,
                'repeat_number' => $repeatNumber,
            ]);

            return null;
        }

        // Generate action text
        $actionText = $this->generateActionText(
            $rule->action_type,
            $rule->action_value,
            $rule->action_value_gross_days,
            $rule->action_value_basic_days
        );

        // Create or update penalty (update if exists to ensure correct repeat_number)
        $penalty = AttendancePenalty::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'attendance_date' => $attendanceDate,
            ],
            [
                'late_minutes' => 0, // No late minutes for absence
                'violation_type' => $violationType,
                'repeat_number' => $repeatNumber,
                'action_type' => $rule->action_type,
                'action_value' => $rule->action_value,
                'action_value_gross_days' => $rule->action_value_gross_days,
                'action_value_basic_days' => $rule->action_value_basic_days,
                'action_text' => $actionText,
                'reason_text' => $rule->reason_text,
                'late_minutes_deduction_amount' => null,
            ]
        );

        return $this->autoApprovalService->applyForPenalty($penalty);
    }

    /**
     * Calculate deduction amount for late minutes: late_minutes × minute_rate.
     * Minute rate = basic_salary / (work_days_per_month × work_minutes_per_day).
     * Work minutes per day = shift end_time - start_time. Work days per month from shift workdays.
     *
     * @return float|null Amount in currency, or null if not calculable
     */
    private function calculateLateMinutesDeductionAmount(int $employeeId, int $lateMinutes): ?float
    {
        if ($lateMinutes <= 0) {
            return null;
        }

        $employee = Employee::with(['shift.workdays'])->find($employeeId);
        if (! $employee || ! $employee->shift) {
            return null;
        }

        $basicSalary = $employee->basic_salary ? (float) $employee->basic_salary : null;
        if ($basicSalary === null || $basicSalary <= 0) {
            return null;
        }

        $shift = $employee->shift;

        // Fair minute-rate: use effective work minutes per configured workday
        // (including per-weekday overrides), not only the base shift start/end.
        $workDaysPerWeek = $shift->workdays()->where('is_workday', true)->count();
        if ($workDaysPerWeek <= 0) {
            return null;
        }

        $averageWorkMinutesPerDay = $shift->averageWorkMinutesPerWorkday();
        if ($averageWorkMinutesPerDay === null || $averageWorkMinutesPerDay <= 0) {
            return null;
        }

        // Keep current monthly scaling convention (30/7), but with accurate per-day minutes.
        $workDaysPerMonth = $workDaysPerWeek * (30 / 7);
        $minuteRate = $basicSalary / ($workDaysPerMonth * $averageWorkMinutesPerDay);
        $amount = round($lateMinutes * $minuteRate, 2);

        return $amount > 0 ? $amount : null;
    }

    /**
     * Generate action text based on action type and value
     */
    private function generateActionText(
        string $actionType,
        ?int $actionValue = null,
        ?int $actionValueGrossDays = null,
        ?int $actionValueBasicDays = null
    ): string {
        return match ($actionType) {
            'warning' => 'إنذار كتابي',
            'deduction_percentage' => "خصم {$actionValue}% من الأجر اليومي",
            'deduction_days' => $this->generateDeductionDaysText($actionValue, $actionValueGrossDays, $actionValueBasicDays),
            'absent_deduction' => 'خصم يوم من الراتب الإجمالي + خصم من الراتب الأساسي لليوم التالي',
            'termination' => 'إنهاء الخدمة',
            default => 'غير محدد',
        };
    }

    /**
     * Generate text for deduction_days action type
     */
    private function generateDeductionDaysText(?int $actionValue, ?int $actionValueGrossDays, ?int $actionValueBasicDays): string
    {
        // If we have separate gross and basic days, use them
        if ($actionValueGrossDays !== null || $actionValueBasicDays !== null) {
            $parts = [];
            if ($actionValueGrossDays !== null && $actionValueGrossDays > 0) {
                $parts[] = "خصم {$actionValueGrossDays} يوم من الراتب الإجمالي";
            }
            if ($actionValueBasicDays !== null && $actionValueBasicDays > 0) {
                $parts[] = "خصم {$actionValueBasicDays} يوم من الراتب الأساسي";
            }

            return ! empty($parts) ? implode(' + ', $parts) : 'خصم أجر يوم';
        }

        // Fallback to old behavior
        if ($actionValue !== null && $actionValue > 0) {
            return "خصم {$actionValue} يوم من الراتب";
        }

        return 'خصم أجر يوم';
    }
}
