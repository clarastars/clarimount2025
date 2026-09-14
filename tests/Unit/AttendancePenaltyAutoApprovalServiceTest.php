<?php

declare(strict_types=1);

use App\Models\AttendancePenalty;
use App\Services\AttendancePenaltyApprovalNotifier;
use App\Services\AttendancePenaltyAutoApprovalService;
use App\Services\OperationalMonthService;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-14 13:00:00', 'Asia/Riyadh'));

    $this->operationalMonth = Mockery::mock(OperationalMonthService::class);
    $this->operationalMonth
        ->shouldReceive('resolveOperationalMonthRangeContainingDate')
        ->andReturn([
            'start' => Carbon::parse('2026-08-21 00:00:00', 'Asia/Riyadh'),
            'end' => Carbon::parse('2026-09-20 23:59:59', 'Asia/Riyadh'),
        ]);

    $this->notifier = Mockery::mock(AttendancePenaltyApprovalNotifier::class);

    $this->service = new AttendancePenaltyAutoApprovalService(
        $this->notifier,
        $this->operationalMonth,
    );
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makePenalty(string $attendanceDate, string $violationType): AttendancePenalty
{
    $penalty = new AttendancePenalty([
        'attendance_date' => $attendanceDate,
        'violation_type' => $violationType,
        'approval_status' => 'pending',
    ]);

    return $penalty;
}

it('allows auto-approval for late penalties on today', function (): void {
    $penalty = makePenalty('2026-09-14', 'late_0_15');

    expect($this->service->isEligibleForAutoApproval($penalty))->toBeTrue();
});

it('allows auto-approval for late penalties on yesterday', function (): void {
    $penalty = makePenalty('2026-09-13', 'late_0_15');

    expect($this->service->isEligibleForAutoApproval($penalty))->toBeTrue();
});

it('rejects auto-approval for historical late penalties inside the operational month', function (): void {
    $penalty = makePenalty('2026-08-23', 'late_0_15');

    expect($this->service->isEligibleForAutoApproval($penalty))->toBeFalse();
});

it('allows auto-approval for early departure only on yesterday', function (): void {
    $yesterday = makePenalty('2026-09-13', 'early_departure_over_1');
    $today = makePenalty('2026-09-14', 'early_departure_over_1');
    $old = makePenalty('2026-08-30', 'early_departure_over_1');

    expect($this->service->isEligibleForAutoApproval($yesterday))->toBeTrue()
        ->and($this->service->isEligibleForAutoApproval($today))->toBeFalse()
        ->and($this->service->isEligibleForAutoApproval($old))->toBeFalse();
});

it('rejects auto-approval outside the operational month even if recent', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-21 10:00:00', 'Asia/Riyadh'));

    // Fresh relative to "now", but operational month in the mock still ends Sep 20.
    $penalty = makePenalty('2026-09-20', 'late_0_15');

    expect($this->service->isEligibleForAutoApproval($penalty))->toBeTrue();

    $outside = makePenalty('2026-09-21', 'late_0_15');

    expect($this->service->isEligibleForAutoApproval($outside))->toBeFalse();
});
