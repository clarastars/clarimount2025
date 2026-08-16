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

it('sums pro-rated hire month and full later months for initialization', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual();

    $periods = $service->eligibleAccrualPeriods(
        Carbon::parse('2026-07-26', 'Asia/Riyadh'),
        Carbon::parse('2026-08-05', 'Asia/Riyadh'),
    );

    $total = 0.0;

    foreach ($periods as $period) {
        $total = round($total + $service->accrualDaysForPeriod($employee, $period), 2);
    }

    expect($periods)->toBe(['2026-07', '2026-08']);
    expect($total)->toBe(2.98);
});

it('pro-rates the departure month', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-01-10',
        'departure_date' => '2026-08-15',
    ]);

    $augustDays = $service->accrualDaysForPeriod($employee, '2026-08');

    expect($augustDays)->toBe(1.21);
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

it('projects full monthly accrual through a future start date', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-01-01',
        'annual_leave_balance' => 30,
    ]);

    $asOf = Carbon::parse('2026-11-01', 'Asia/Riyadh');
    $projected = $service->projectedAccruedBalanceAsOf($employee, $asOf);

    expect($projected)->toBe(27.5);
});

it('includes the hire-month pro-rate when projecting a future leave date', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual();

    $asOf = Carbon::parse('2026-11-01', 'Asia/Riyadh');
    $projected = $service->projectedAccruedBalanceAsOf($employee, $asOf);

    expect($projected)->toBe(10.48);
});

it('treats a UTC midnight hire date as the same calendar day in Riyadh', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => Carbon::parse('2025-11-09 00:00:00', 'UTC'),
        'annual_leave_balance' => 21,
    ]);

    expect($service->accrualDaysForPeriod($employee, '2025-11'))->toBe(1.28);
});

it('adds only later months onto stored accrual instead of rebuilding from hire date', function (): void {
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

    expect($futureDays)->toBe(2.5);
});
