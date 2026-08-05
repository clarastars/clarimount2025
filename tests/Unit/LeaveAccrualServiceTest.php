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
