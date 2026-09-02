<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceDailyPresentation;
use App\Models\AttendancePenalty;
use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AttendancePenaltyEmailContextBuilder
{
    private const TZ = 'Asia/Riyadh';

    public function __construct(
        private FlexibleAttendanceTimingService $flexibleAttendanceTimingService,
    ) {}

    /**
     * @return array{
     *     scenario: string,
     *     date: ?string,
     *     scheduled_check_in: ?string,
     *     scheduled_check_out: ?string,
     *     actual_check_in: ?string,
     *     actual_check_out: ?string,
     *     late_minutes: ?int,
     *     early_minutes: ?int
     * }
     */
    public function build(AttendancePenalty $penalty): array
    {
        $penalty->loadMissing(['employee.shift.workdays', 'employee.company']);

        $scenario = $this->resolveScenario($penalty);
        $presentation = $this->resolvePresentation($penalty);
        $timing = $this->resolveTiming($penalty, $penalty->employee, $presentation);

        return [
            'scenario' => $scenario,
            'date' => $this->formatDate($penalty->attendance_date),
            'scheduled_check_in' => $timing['scheduled_check_in'],
            'scheduled_check_out' => $timing['scheduled_check_out'],
            'actual_check_in' => $timing['actual_check_in'],
            'actual_check_out' => $timing['actual_check_out'],
            'late_minutes' => $scenario === 'late' ? max(0, (int) $penalty->late_minutes) : null,
            'early_minutes' => $scenario === 'early_departure' ? $timing['early_minutes'] : null,
        ];
    }

    private function resolveScenario(AttendancePenalty $penalty): string
    {
        if ($penalty->isLateViolation()) {
            return 'late';
        }

        if ($penalty->isEarlyDepartureViolation()) {
            return 'early_departure';
        }

        return 'generic';
    }

    private function resolvePresentation(AttendancePenalty $penalty): ?AttendanceDailyPresentation
    {
        if ($penalty->employee_id === null || $penalty->attendance_date === null) {
            return null;
        }

        return AttendanceDailyPresentation::query()
            ->where('employee_id', $penalty->employee_id)
            ->whereDate('att_date', $penalty->attendance_date)
            ->first();
    }

    /**
     * @return array{
     *     scheduled_check_in: ?string,
     *     scheduled_check_out: ?string,
     *     actual_check_in: ?string,
     *     actual_check_out: ?string,
     *     early_minutes: ?int
     * }
     */
    private function resolveTiming(
        AttendancePenalty $penalty,
        ?Employee $employee,
        ?AttendanceDailyPresentation $presentation,
    ): array {
        $result = [
            'scheduled_check_in' => null,
            'scheduled_check_out' => null,
            'actual_check_in' => null,
            'actual_check_out' => null,
            'early_minutes' => null,
        ];

        if ($presentation === null) {
            return $result;
        }

        if ($presentation->first_punch !== null) {
            $result['actual_check_in'] = $this->formatTime(Carbon::parse((string) $presentation->first_punch));
        }

        if ($presentation->last_punch !== null) {
            $result['actual_check_out'] = $this->formatTime(Carbon::parse((string) $presentation->last_punch));
        }

        if ($employee?->shift === null || $penalty->attendance_date === null) {
            return $result;
        }

        $attendanceDate = $penalty->attendance_date->format('Y-m-d');
        $weekday = Carbon::parse($attendanceDate, self::TZ)->dayOfWeek;

        $expectedStart = Carbon::parse(
            $attendanceDate.' '.$employee->shift->effectiveStartTimeStringForWeekday($weekday),
            self::TZ
        );
        $expectedEnd = Carbon::parse(
            $attendanceDate.' '.$employee->shift->effectiveEndTimeStringForWeekday($weekday),
            self::TZ
        );

        $dayTiming = null;
        if ($presentation->first_punch !== null) {
            $firstPunch = Carbon::parse((string) $presentation->first_punch)->setTimezone(self::TZ);
            $company = $employee->company;

            $dayTiming = $this->flexibleAttendanceTimingService->resolveDayTiming(
                $expectedStart,
                $expectedEnd,
                $firstPunch,
                $company?->flexibleTimeEnabled() ?? false,
                $company?->flexibleTimeMinutes() ?? 0,
                (int) ($employee->shift->grace_minutes ?? 0),
            );
        }

        $result['scheduled_check_in'] = $this->formatTime($dayTiming['expected_start'] ?? $expectedStart);
        $result['scheduled_check_out'] = $this->formatTime($dayTiming['required_departure'] ?? $expectedEnd);

        if (
            $penalty->isEarlyDepartureViolation()
            && $presentation->last_punch !== null
            && $dayTiming !== null
        ) {
            $lastPunch = Carbon::parse((string) $presentation->last_punch)->setTimezone(self::TZ);
            $result['early_minutes'] = $this->flexibleAttendanceTimingService->calculateEarlyMinutes(
                $dayTiming['required_departure'],
                $lastPunch,
            );
        }

        return $result;
    }

    private function formatTime(CarbonInterface $time): string
    {
        $localized = Carbon::instance($time)->copy()->timezone(self::TZ);
        $hour24 = (int) $localized->format('H');
        $minute = $localized->format('i');
        $hour12 = $hour24 % 12;

        if ($hour12 === 0) {
            $hour12 = 12;
        }

        $period = app()->getLocale() === 'ar'
            ? ($hour24 < 12 ? 'ص' : 'م')
            : ($hour24 < 12 ? 'AM' : 'PM');

        return sprintf('%02d:%s %s', $hour12, $minute, $period);
    }

    private function formatDate(mixed $attendanceDate): ?string
    {
        if ($attendanceDate === null) {
            return null;
        }

        $date = $attendanceDate instanceof CarbonInterface
            ? Carbon::instance($attendanceDate)->copy()->timezone(self::TZ)
            : Carbon::parse((string) $attendanceDate, self::TZ);

        if (app()->getLocale() === 'ar') {
            return $date->locale('ar')->translatedFormat('l j F Y');
        }

        return $date->format('Y-m-d');
    }
}
