<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Services\LeaveAccrualService;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-05', 'Asia/Riyadh'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeEmployeeForAccrual(array $attributes = []): Employee
{
    return new Employee(array_merge([
        'annual_leave_balance' => 30,
        'hire_date' => '2026-07-26',
    ], $attributes));
}

it('pro-rates the hire month and accrues full months thereafter', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual();

    $julyDays = $service->accrualDaysForPeriod($employee, '2026-07');
    $augustDays = $service->accrualDaysForPeriod($employee, '2026-08');

    expect($julyDays)->toBe(0.48);
    expect($augustDays)->toBe(2.5);
});

it('defaults monthly jobs to the last completed month, not the in-progress month', function (): void {
    $service = new LeaveAccrualService;

    expect($service->resolveLastCompletedAccrualPeriod())->toBe('2026-07');
    expect($service->resolveLastCompletedAccrualDate()->toDateString())->toBe('2026-07-31');
});

it('earns leave only through the last completed month', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual();

    $earnedThrough = $service->resolveEarnedThroughDate($employee);
    $periods = $service->eligibleAccrualPeriods(
        Carbon::parse('2026-07-26', 'Asia/Riyadh'),
        $earnedThrough,
    );

    $total = 0.0;
    foreach ($periods as $period) {
        $total = round($total + $service->accrualDaysForPeriod($employee, $period, null, $earnedThrough), 2);
    }

    expect($earnedThrough->toDateString())->toBe('2026-07-31');
    expect($periods)->toBe(['2026-07']);
    expect($total)->toBe(0.48);
    expect($service->projectedAccruedBalanceAsOf($employee, Carbon::now('Asia/Riyadh')))->toBe(0.48);
});

it('excludes the in-progress month when projecting earned leave for today', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-03', 'Asia/Riyadh'));

    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-03-11',
        'annual_leave_balance' => 21,
    ]);

    $projected = $service->projectedAccruedBalanceAsOf($employee, Carbon::now('Asia/Riyadh'));

    // March 11–31: (21/31)*1.75 = 1.19, plus Apr–Aug full months (5 × 1.75)
    expect($projected)->toBe(9.94);
});

it('projects accrued leave only through a past settlement date', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-03', 'Asia/Riyadh'));

    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-03-11',
        'annual_leave_balance' => 21,
        'leave_accrued_balance' => 9.94,
    ]);

    $projected = $service->projectedAccruedBalanceAsOf(
        $employee,
        Carbon::parse('2026-08-25', 'Asia/Riyadh'),
    );

    expect($projected)->toBe(8.19);
});

it('pro-rates the departure month immediately even if that month is still in progress', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-20', 'Asia/Riyadh'));

    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-01-10',
        'departure_date' => '2026-08-15',
    ]);

    expect($service->resolveEarnedThroughDate($employee)->toDateString())->toBe('2026-08-15');
    expect($service->accrualDaysForPeriod($employee, '2026-08'))->toBe(1.21);
});

it('returns zero when the hire date is after the accrual period', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-09-01',
    ]);

    expect($service->accrualDaysForPeriod($employee, '2026-08'))->toBe(0.0);
});

it('returns the full monthly amount for a complete month', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-01-01',
    ]);

    expect($service->accrualDaysForPeriod($employee, '2026-06'))->toBe(2.5);
});

it('projects completed months only through a future start date', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-01-01',
        'annual_leave_balance' => 30,
    ]);

    $asOf = Carbon::parse('2026-11-01', 'Asia/Riyadh');
    $projected = $service->projectedAccruedBalanceAsOf($employee, $asOf);

    // Last completed month as of 1 Nov is October: Jan–Oct = 10 × 2.5
    expect($projected)->toBe(25.0);
});

it('includes the hire-month pro-rate when projecting a future leave date', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual();

    $asOf = Carbon::parse('2026-11-01', 'Asia/Riyadh');
    $projected = $service->projectedAccruedBalanceAsOf($employee, $asOf);

    // Last completed as of 1 Nov is October: Jul 0.48 + Aug–Oct 7.5
    expect($projected)->toBe(7.98);
});

it('treats a UTC midnight hire date as the same calendar day in Riyadh', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => Carbon::parse('2025-11-09 00:00:00', 'UTC'),
        'annual_leave_balance' => 21,
    ]);

    expect($service->accrualDaysForPeriod($employee, '2025-11'))->toBe(1.28);
});

it('adds only later completed months onto stored accrual', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-16', 'Asia/Riyadh'));

    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2024-08-02',
        'annual_leave_balance' => 30,
        'leave_accrued_balance' => 26,
    ]);

    $futureDays = $service->futureAccrualDaysUntil(
        $employee,
        Carbon::parse('2026-09-20', 'Asia/Riyadh'),
    );

    // Stored is through July; by 20 Sep only August has completed
    expect($futureDays)->toBe(2.5);

    $throughOctober = $service->futureAccrualDaysUntil(
        $employee,
        Carbon::parse('2026-10-20', 'Asia/Riyadh'),
    );

    // August + September; October is still in progress
    expect($throughOctober)->toBe(5.0);
});
