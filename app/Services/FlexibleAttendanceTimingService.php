<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Single source of truth for late / required-departure minutes.
 *
 * Normal mode: late = max(0, arrivalOffset - grace); required departure = shift end.
 * Flexible mode: grace is ignored.
 * - Early window: up to flexibleBeforeMinutes before shift start (and checkout shifts earlier by the same offset).
 * - Late window: up to flexibleAfterMinutes after shift start (and checkout shifts later by the same offset).
 * Lateness starts only after the upper flexible bound (start + flexibleAfterMinutes).
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
     *     flexible_before_minutes: int,
     *     flexible_after_minutes: int,
     *     flexible_minutes: int
     * }
     */
    public function resolveDayTiming(
        CarbonInterface $expectedStart,
        CarbonInterface $expectedEnd,
        CarbonInterface $firstPunch,
        bool $flexibleEnabled,
        int $flexibleBeforeMinutes,
        int $flexibleAfterMinutes = 0,
        int $graceMinutes = 0,
    ): array {
        $expectedStart = Carbon::instance($expectedStart)->copy()->timezone(self::TZ);
        $expectedEnd = Carbon::instance($expectedEnd)->copy()->timezone(self::TZ);
        $firstPunch = Carbon::instance($firstPunch)->copy()->timezone(self::TZ);

        if ($expectedEnd->lte($expectedStart)) {
            $expectedEnd->addDay();
        }

        $flexibleBeforeMinutes = max(0, $flexibleBeforeMinutes);
        $flexibleAfterMinutes = max(0, $flexibleAfterMinutes);

        $arrivalOffsetMinutes = (int) round(($firstPunch->getTimestamp() - $expectedStart->getTimestamp()) / 60);
        $usesFlexibleTime = $flexibleEnabled && ($flexibleBeforeMinutes > 0 || $flexibleAfterMinutes > 0);

        if ($usesFlexibleTime) {
            // Late only after the "after" bound (e.g. start+60).
            $lateMinutes = max(0, $arrivalOffsetMinutes - $flexibleAfterMinutes);

            // Clamp arrival offset into [-before, +after] so checkout mirrors allowed flex.
            $effectiveOffsetMinutes = max(
                -$flexibleBeforeMinutes,
                min($flexibleAfterMinutes, $arrivalOffsetMinutes)
            );
            $requiredDeparture = $expectedEnd->copy()->addMinutes($effectiveOffsetMinutes);

            return [
                'late_minutes' => $lateMinutes,
                'arrival_offset_minutes' => $arrivalOffsetMinutes,
                'effective_offset_minutes' => $effectiveOffsetMinutes,
                'expected_start' => $expectedStart,
                'expected_end' => $expectedEnd,
                'required_departure' => $requiredDeparture,
                'uses_flexible_time' => true,
                'flexible_before_minutes' => $flexibleBeforeMinutes,
                'flexible_after_minutes' => $flexibleAfterMinutes,
                // Legacy alias: upper (after) bound.
                'flexible_minutes' => $flexibleAfterMinutes,
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
            'flexible_before_minutes' => 0,
            'flexible_after_minutes' => 0,
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
