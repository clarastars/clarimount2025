<?php

declare(strict_types=1);

use App\Services\FlexibleAttendanceTimingService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->service = new FlexibleAttendanceTimingService;
});

function shiftTimes(string $date = '2026-08-27'): array
{
    return [
        Carbon::parse($date.' 08:00:00', 'Asia/Riyadh'),
        Carbon::parse($date.' 17:00:00', 'Asia/Riyadh'),
    ];
}

it('keeps normal grace-based late minutes when flexible time is disabled', function (): void {
    [$start, $end] = shiftTimes();
    $firstPunch = Carbon::parse('2026-08-27 08:12:00', 'Asia/Riyadh');

    $timing = $this->service->resolveDayTiming($start, $end, $firstPunch, false, 30, 10);

    expect($timing['late_minutes'])->toBe(2)
        ->and($timing['uses_flexible_time'])->toBeFalse()
        ->and($timing['required_departure']->format('H:i'))->toBe('17:00');
});

it('does not count late inside the flexible upper bound and shifts required departure', function (): void {
    [$start, $end] = shiftTimes();
    $firstPunch = Carbon::parse('2026-08-27 08:25:00', 'Asia/Riyadh');

    $timing = $this->service->resolveDayTiming($start, $end, $firstPunch, true, 30, 10);

    expect($timing['late_minutes'])->toBe(0)
        ->and($timing['uses_flexible_time'])->toBeTrue()
        ->and($timing['effective_offset_minutes'])->toBe(25)
        ->and($timing['required_departure']->format('H:i'))->toBe('17:25');
});

it('counts late only beyond the flexible upper bound and caps departure offset', function (): void {
    [$start, $end] = shiftTimes();
    $firstPunch = Carbon::parse('2026-08-27 08:45:00', 'Asia/Riyadh');

    $timing = $this->service->resolveDayTiming($start, $end, $firstPunch, true, 30, 10);

    expect($timing['late_minutes'])->toBe(15)
        ->and($timing['effective_offset_minutes'])->toBe(30)
        ->and($timing['required_departure']->format('H:i'))->toBe('17:30');
});

it('allows early arrival within the flexible lower bound', function (): void {
    [$start, $end] = shiftTimes();
    $firstPunch = Carbon::parse('2026-08-27 07:30:00', 'Asia/Riyadh');

    $timing = $this->service->resolveDayTiming($start, $end, $firstPunch, true, 30, 10);

    expect($timing['late_minutes'])->toBe(0)
        ->and($timing['effective_offset_minutes'])->toBe(-30)
        ->and($timing['required_departure']->format('H:i'))->toBe('16:30');
});

it('clamps arrival earlier than the flexible lower bound', function (): void {
    [$start, $end] = shiftTimes();
    $firstPunch = Carbon::parse('2026-08-27 07:00:00', 'Asia/Riyadh');

    $timing = $this->service->resolveDayTiming($start, $end, $firstPunch, true, 30, 10);

    expect($timing['late_minutes'])->toBe(0)
        ->and($timing['effective_offset_minutes'])->toBe(-30)
        ->and($timing['required_departure']->format('H:i'))->toBe('16:30');
});

it('ignores shift grace minutes entirely in flexible mode', function (): void {
    [$start, $end] = shiftTimes();
    $firstPunch = Carbon::parse('2026-08-27 08:10:00', 'Asia/Riyadh');

    $withGraceWouldBeZero = $this->service->resolveDayTiming($start, $end, $firstPunch, false, 0, 10);
    $flexible = $this->service->resolveDayTiming($start, $end, $firstPunch, true, 30, 10);

    expect($withGraceWouldBeZero['late_minutes'])->toBe(0)
        ->and($flexible['late_minutes'])->toBe(0)
        ->and($flexible['required_departure']->format('H:i'))->toBe('17:10');
});

it('calculates early minutes against the required flexible departure', function (): void {
    [$start, $end] = shiftTimes();
    $firstPunch = Carbon::parse('2026-08-27 08:25:00', 'Asia/Riyadh');
    $timing = $this->service->resolveDayTiming($start, $end, $firstPunch, true, 30, 0);

    $lastPunchOnTime = Carbon::parse('2026-08-27 17:25:00', 'Asia/Riyadh');
    $lastPunchEarly = Carbon::parse('2026-08-27 17:10:00', 'Asia/Riyadh');

    expect($this->service->calculateEarlyMinutes($timing['required_departure'], $lastPunchOnTime))->toBe(0)
        ->and($this->service->calculateEarlyMinutes($timing['required_departure'], $lastPunchEarly))->toBe(15);
});

it('treats leaving after a capped flexible departure as not early', function (): void {
    [$start, $end] = shiftTimes();
    $firstPunch = Carbon::parse('2026-08-27 08:45:00', 'Asia/Riyadh');
    $timing = $this->service->resolveDayTiming($start, $end, $firstPunch, true, 30, 0);
    $lastPunch = Carbon::parse('2026-08-27 17:45:00', 'Asia/Riyadh');

    expect($timing['late_minutes'])->toBe(15)
        ->and($timing['required_departure']->format('H:i'))->toBe('17:30')
        ->and($this->service->calculateEarlyMinutes($timing['required_departure'], $lastPunch))->toBe(0);
});
