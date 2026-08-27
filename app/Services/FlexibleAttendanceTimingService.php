<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Single source of truth for late / required-departure minutes.
 *
 * Normal mode: late = max(0, arrivalOffset - grace); required departure = shift end.
 * Flexible mode: grace is ignored. Arrival may shift within ±flexibleMinutes of shift start;
 * required departure shifts by the same clamped offset. Lateness starts only after the
 * upper flexible bound (start + flexibleMinutes).
 */
class FlexibleAttendanceTimingService
{
    public const TZ = 'Asia/Riyadh';

    /**
     * @return array{
     *     late_minutes: int,
     *     arrival_offset_minutes: int,
     *     effective_offset_minutes: int,
     *     expected_start: Carbon,
     *     expected_end: Carbon,
     *     required_departure: Carbon,
     *     uses_flexible_time: bool,
     *     flexible_minutes: int
     * }
     */
    public function resolveDayTiming(
        CarbonInterface $expectedStart,
        CarbonInterface $expectedEnd,
        CarbonInterface $firstPunch,
        bool $flexibleEnabled,
        int $flexibleMinutes,
        int $graceMinutes = 0,
    ): array {
        $expectedStart = Carbon::instance($expectedStart)->copy()->timezone(self::TZ);
        $expectedEnd = Carbon::instance($expectedEnd)->copy()->timezone(self::TZ);
        $firstPunch = Carbon::instance($firstPunch)->copy()->timezone(self::TZ);

        if ($expectedEnd->lte($expectedStart)) {
            $expectedEnd->addDay();
        }

        $arrivalOffsetMinutes = (int) round(($firstPunch->getTimestamp() - $expectedStart->getTimestamp()) / 60);
        $usesFlexibleTime = $flexibleEnabled && $flexibleMinutes > 0;

        if ($usesFlexibleTime) {
            $lateMinutes = max(0, $arrivalOffsetMinutes - $flexibleMinutes);
            $effectiveOffsetMinutes = max(-$flexibleMinutes, min($flexibleMinutes, $arrivalOffsetMinutes));
            $requiredDeparture = $expectedEnd->copy()->addMinutes($effectiveOffsetMinutes);

            return [
                'late_minutes' => $lateMinutes,
                'arrival_offset_minutes' => $arrivalOffsetMinutes,
                'effective_offset_minutes' => $effectiveOffsetMinutes,
                'expected_start' => $expectedStart,
                'expected_end' => $expectedEnd,
                'required_departure' => $requiredDeparture,
                'uses_flexible_time' => true,
                'flexible_minutes' => $flexibleMinutes,
            ];
        }

        $lateMinutes = max(0, $arrivalOffsetMinutes - max(0, $graceMinutes));

        return [
            'late_minutes' => $lateMinutes,
            'arrival_offset_minutes' => $arrivalOffsetMinutes,
            'effective_offset_minutes' => 0,
            'expected_start' => $expectedStart,
            'expected_end' => $expectedEnd,
            'required_departure' => $expectedEnd->copy(),
            'uses_flexible_time' => false,
            'flexible_minutes' => 0,
        ];
    }

    public function calculateEarlyMinutes(CarbonInterface $requiredDeparture, CarbonInterface $lastPunch): int
    {
        $required = Carbon::instance($requiredDeparture)->copy()->timezone(self::TZ);
        $last = Carbon::instance($lastPunch)->copy()->timezone(self::TZ);

        return max(0, (int) round(($required->getTimestamp() - $last->getTimestamp()) / 60));
    }
}
