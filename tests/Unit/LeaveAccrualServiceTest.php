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

it('pro-rates the hire month using a fixed 30-day divisor', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual();

    // Jul 26→31 inclusive = 6 leftover days: (6/30)*2.5 = 0.5
    expect($service->accrualDaysForPeriod($employee, '2026-07'))->toBe(0.5);
    expect($service->accrualDaysForPeriod($employee, '2026-08'))->toBe(2.5);
});

it('defaults monthly jobs to the last completed month, not the in-progress month', function (): void {
    $service = new LeaveAccrualService;

    expect($service->resolveLastCompletedAccrualPeriod())->toBe('2026-07');
    expect($service->resolveLastCompletedAccrualDate()->toDateString())->toBe('2026-07-31');
});

it('earns leave only through the last completed month for completed-month projections', function (): void {
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
    expect($total)->toBe(0.5);
    expect($service->projectedAccruedBalanceAsOf($employee, Carbon::now('Asia/Riyadh')))->toBe(0.5);
});

it('includes the in-progress month when projecting live accrued leave for today', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-17', 'Asia/Riyadh'));

    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-07-26',
        'annual_leave_balance' => 30,
    ]);

    // Jul 26→Sep 17 inclusive = 1 month + 23 days: (1 + 23/30)*2.5 = 4.42
    expect($service->projectedLiveAccruedBalanceThroughDate(
        $employee,
        Carbon::now('Asia/Riyadh'),
    ))->toBe(4.42);

    expect($service->resolveLiveAccruedThroughDate($employee)->toDateString())->toBe('2026-09-17');
});

it('excludes the in-progress month when projecting earned leave for today', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-03', 'Asia/Riyadh'));

    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-03-11',
        'annual_leave_balance' => 21,
    ]);

    $projected = $service->projectedAccruedBalanceAsOf($employee, Carbon::now('Asia/Riyadh'));

    // Earned through 31 Aug inclusive: Mar 11→Aug 31 = 5 months + 21 days → 9.98
    expect($projected)->toBe(9.98);
});

it('prorates the settlement month through a past date instead of dropping that month', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-03', 'Asia/Riyadh'));

    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-03-11',
        'annual_leave_balance' => 21,
        'leave_accrued_balance' => 9.98,
    ]);

    $asOf = Carbon::parse('2026-08-25', 'Asia/Riyadh');

    // Completed months as of 25 Aug still stop at July
    expect($service->projectedAccruedBalanceAsOf($employee, $asOf))->toBe(8.23);

    // Settlement on 25 Aug inclusive: Mar 11→Aug 25 = 5 months + 15 days → 9.63
    expect($service->projectedAccruedBalanceThroughDate($employee, $asOf))->toBe(9.63);

    // Settlement dated today (3 Sep) inclusive: Mar 11→Sep 3 = 5 months + 24 days → 10.15
    expect($service->projectedAccruedBalanceThroughDate(
        $employee,
        Carbon::now('Asia/Riyadh'),
    ))->toBe(10.15);
});

it('credits the settlement date itself for short service spans', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-09-12',
        'annual_leave_balance' => 21,
    ]);

    // Sep 12→28 inclusive = 17 days: (17/30)*1.75 = 0.99
    expect($service->projectedAccruedBalanceThroughDate(
        $employee,
        Carbon::parse('2026-09-28', 'Asia/Riyadh'),
    ))->toBe(0.99);
});

it('pro-rates the departure month immediately even if that month is still in progress', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-20', 'Asia/Riyadh'));

    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-01-10',
        'departure_date' => '2026-08-15',
    ]);

    expect($service->resolveEarnedThroughDate($employee)->toDateString())->toBe('2026-08-15');
    expect($service->accrualDaysForPeriod($employee, '2026-08'))->toBe(1.17);
});

it('returns zero when the hire date is after the accrual period', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-09-01',
    ]);

    expect($service->accrualDaysForPeriod($employee, '2026-08'))->toBe(0.0);
});

it('returns the continuous month delta for a mid-year calendar month', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-01-01',
    ]);

    // Jan 1→Jun 30 inclusive minus Jan 1→May 31 inclusive under the 30-day leftover rule
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

    // Last completed month as of 1 Nov is October: Jan 1→Oct 31 = 10 months → 25.0
    expect($projected)->toBe(25.0);
});

it('includes the hire-month pro-rate when projecting a future leave date', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual();

    $asOf = Carbon::parse('2026-11-01', 'Asia/Riyadh');
    $projected = $service->projectedAccruedBalanceAsOf($employee, $asOf);

    // Last completed as of 1 Nov is October: Jul 26→Oct 31 inclusive = 3 months + 6 days → 8.0
    expect($projected)->toBe(8.0);
});

it('treats a UTC midnight hire date as the same calendar day in Riyadh', function (): void {
    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => Carbon::parse('2025-11-09 00:00:00', 'UTC'),
        'annual_leave_balance' => 21,
    ]);

    // Nov 9→30 inclusive = 22 leftover days: (22/30)*1.75 = 1.28
    expect($service->accrualDaysForPeriod($employee, '2025-11'))->toBe(1.28);
});

it('adds live pro-rated days from today through a future leave date', function (): void {
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

    expect($futureDays)->toBe(2.83);

    $throughOctober = $service->futureAccrualDaysUntil(
        $employee,
        Carbon::parse('2026-10-20', 'Asia/Riyadh'),
    );

    expect($throughOctober)->toBe(5.33);
});

it('matches hire-anniversary months with a 30-day leftover for mid-month hires', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-27', 'Asia/Riyadh'));

    $service = new LeaveAccrualService;
    $employee = makeEmployeeForAccrual([
        'hire_date' => '2026-07-12',
        'annual_leave_balance' => 30,
    ]);

    // Jul 12→Sep 27 inclusive = 2 months + 16 days: (2 + 16/30)*2.5 = 6.33
    expect($service->projectedLiveAccruedBalanceThroughDate(
        $employee,
        Carbon::now('Asia/Riyadh'),
    ))->toBe(6.33);
});
