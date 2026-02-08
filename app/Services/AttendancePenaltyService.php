<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendancePenalty;
use App\Models\LaborLawRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendancePenaltyService
{
    /**
     * Calculate and create penalty for an attendance record
     *
     * @param int $employeeId
     * @param string $attendanceDate Date in Y-m-d format
     * @param int $lateMinutes
     * @return AttendancePenalty|null
     */
    public function calculatePenalty(int $employeeId, string $attendanceDate, int $lateMinutes): ?AttendancePenalty
    {
        // Skip if no late minutes
        if ($lateMinutes <= 0) {
            return null;
        }

        // Determine violation type based on late minutes
        $violationType = $this->determineViolationType($lateMinutes);
        if (!$violationType) {
            return null;
        }

        // Calculate repeat number for this violation type in the same calendar year
        $repeatNumber = $this->calculateRepeatNumber($employeeId, $violationType, $attendanceDate);
        
        // Get the rule for this violation type and repeat number
        $rule = LaborLawRule::byViolationType($violationType)
            ->byRepeatNumber($repeatNumber)
            ->first();

        if (!$rule) {
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

        // Create or get existing penalty (idempotent)
        $penalty = AttendancePenalty::firstOrCreate(
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
            ]
        );

        return $penalty;
    }

    /**
     * Get penalty for a specific attendance record
     *
     * @param int $employeeId
     * @param string $attendanceDate Date in Y-m-d format
     * @return AttendancePenalty|null
     */
    public function getPenaltyForAttendance(int $employeeId, string $attendanceDate): ?AttendancePenalty
    {
        return AttendancePenalty::where('employee_id', $employeeId)
            ->where('attendance_date', $attendanceDate)
            ->first();
    }

    /**
     * Determine violation type based on late minutes
     *
     * @param int $lateMinutes
     * @return string|null
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
     * Calculate repeat number for a violation type in the same calendar year
     *
     * @param int $employeeId
     * @param string $violationType
     * @param string $attendanceDate Date in Y-m-d format
     * @return int
     */
    private function calculateRepeatNumber(int $employeeId, string $violationType, string $attendanceDate): int
    {
        $year = Carbon::parse($attendanceDate)->year;

        // Count existing penalties for the same violation type in the same calendar year
        $count = AttendancePenalty::forEmployee($employeeId)
            ->byViolationType($violationType)
            ->forYear($year)
            ->count();

        // Repeat number is count + 1, capped at 4
        return min($count + 1, 4);
    }

    /**
     * Calculate and create penalty for absence without permission
     * 
     * @deprecated Use calculateAbsenceWithoutExcusePenalty instead
     * @param int $employeeId
     * @param string $attendanceDate Date in Y-m-d format
     * @return AttendancePenalty|null
     */
    public function calculateAbsencePenalty(int $employeeId, string $attendanceDate): ?AttendancePenalty
    {
        // Use absent_without_excuse as they are the same
        return $this->calculateAbsenceWithoutExcusePenalty($employeeId, $attendanceDate);
    }

    /**
     * Calculate and create penalty for absence without excuse
     *
     * @param int $employeeId
     * @param string $attendanceDate Date in Y-m-d format
     * @return AttendancePenalty|null
     */
    public function calculateAbsenceWithoutExcusePenalty(int $employeeId, string $attendanceDate): ?AttendancePenalty
    {
        $violationType = 'absent_without_excuse';

        // Calculate repeat number for this violation type in the same calendar year
        $repeatNumber = $this->calculateRepeatNumber($employeeId, $violationType, $attendanceDate);
        
        return $this->createAbsencePenaltyWithRepeatNumber($employeeId, $attendanceDate, $repeatNumber);
    }

    /**
     * Calculate and create penalty for absence without excuse with specific repeat number
     *
     * @param int $employeeId
     * @param string $attendanceDate Date in Y-m-d format
     * @param int $repeatNumber
     * @return AttendancePenalty|null
     */
    public function calculateAbsenceWithoutExcusePenaltyWithRepeatNumber(int $employeeId, string $attendanceDate, int $repeatNumber): ?AttendancePenalty
    {
        return $this->createAbsencePenaltyWithRepeatNumber($employeeId, $attendanceDate, $repeatNumber);
    }

    /**
     * Create absence penalty with specific repeat number
     *
     * @param int $employeeId
     * @param string $attendanceDate Date in Y-m-d format
     * @param int $repeatNumber
     * @return AttendancePenalty|null
     */
    private function createAbsencePenaltyWithRepeatNumber(int $employeeId, string $attendanceDate, int $repeatNumber): ?AttendancePenalty
    {
        $violationType = 'absent_without_excuse';
        
        // Cap repeat number at 4
        $repeatNumber = min($repeatNumber, 4);
        
        // Get the rule for this violation type and repeat number
        $rule = LaborLawRule::byViolationType($violationType)
            ->byRepeatNumber($repeatNumber)
            ->first();

        if (!$rule) {
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
            ]
        );

        return $penalty;
    }

    /**
     * Generate action text based on action type and value
     *
     * @param string $actionType
     * @param int|null $actionValue
     * @param int|null $actionValueGrossDays
     * @param int|null $actionValueBasicDays
     * @return string
     */
    private function generateActionText(
        string $actionType, 
        ?int $actionValue = null,
        ?int $actionValueGrossDays = null,
        ?int $actionValueBasicDays = null
    ): string
    {
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
     *
     * @param int|null $actionValue
     * @param int|null $actionValueGrossDays
     * @param int|null $actionValueBasicDays
     * @return string
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
            return !empty($parts) ? implode(' + ', $parts) : 'خصم أجر يوم';
        }
        
        // Fallback to old behavior
        if ($actionValue !== null && $actionValue > 0) {
            return "خصم {$actionValue} يوم من الراتب";
        }
        
        return 'خصم أجر يوم';
    }
}
