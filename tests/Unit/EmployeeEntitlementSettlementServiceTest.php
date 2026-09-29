<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Services\EmployeeEntitlementSettlementService;
use App\Services\LeaveAccrualService;
use App\Services\ManualDeductionAmountService;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-23', 'Asia/Riyadh'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeSettlementEmployee(array $attributes = []): Employee
{
    return new Employee(array_merge([
        'hire_date' => '2024-08-11',
        'basic_salary' => 4000,
        'allowances' => 1400,
        'annual_leave_balance' => 21,
        'leave_accrued_balance' => 17,
        'leave_days_used' => 0,
    ], $attributes));
}

it('counts inclusive days between two dates', function (): void {
    $service = app(EmployeeEntitlementSettlementService::class);

    $days = $service->countInclusiveDays(
        Carbon::parse('2026-08-01', 'Asia/Riyadh'),
        Carbon::parse('2026-09-10', 'Asia/Riyadh'),
    );

    expect($days)->toBe(41.0);
});

it('calculates service days including the hire day', function (): void {
    $service = app(EmployeeEntitlementSettlementService::class);

    $days = $service->calculateServiceDays(
        Carbon::parse('2024-08-11', 'Asia/Riyadh'),
        Carbon::parse('2026-08-23', 'Asia/Riyadh'),
    );

    expect($days)->toBe(743);
});

it('uses gross salary for salary dues amount', function (): void {
    $employee = makeSettlementEmployee();
    $amountService = app(ManualDeductionAmountService::class);

    expect($amountService->grossMonthly($employee))->toBe(5400.0);
    expect($amountService->fromGrossDays($employee, 23))->toBe(round((5400 / 30) * 23, 2));
});

it('calculates annual leave dues only through the settlement date', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-03', 'Asia/Riyadh'));

    $employee = makeSettlementEmployee([
        'hire_date' => '2026-03-11',
        'annual_leave_balance' => 21,
        'leave_accrued_balance' => 9.92,
        'leave_days_used' => 0,
    ]);

    $service = app(EmployeeEntitlementSettlementService::class);

    $throughToday = $service->calculateAnnualLeaveDues(
        $employee,
        Carbon::parse('2026-09-03', 'Asia/Riyadh'),
    );
    // Settlement on 3 Sep: Mar 11→Sep 3 = 5 months + 23 days → 10.09
    expect($throughToday['days'])->toBe(10.09);

    $throughPastDate = $service->calculateAnnualLeaveDues(
        $employee,
        Carbon::parse('2026-08-25', 'Asia/Riyadh'),
    );

    // Settlement on 25 Aug: Mar 11→Aug 25 = 5 months + 14 days → 9.57
    expect($throughPastDate['days'])->toBe(9.57);
    expect($throughPastDate['payable_days'])->toBe(9.57);
    expect($throughPastDate['amount'])->toBe(
        app(ManualDeductionAmountService::class)->fromLeavePayDays($employee, 9.57)
    );
});

it('excludes personal car allowance from annual leave dues', function (): void {
    $employee = makeSettlementEmployee([
        'basic_salary' => 4000,
        'allowances' => 2000,
        'allowance_personal_car' => 1000,
        'hire_date' => '2024-08-11',
        'annual_leave_balance' => 21,
        'leave_accrued_balance' => 21,
        'leave_days_used' => 0,
    ]);

    $service = app(EmployeeEntitlementSettlementService::class);
    $amountService = app(ManualDeductionAmountService::class);

    expect($amountService->grossMonthly($employee))->toBe(6000.0);
    expect($amountService->grossMonthlyExcludingPersonalCar($employee))->toBe(5000.0);

    $dues = $service->calculateAnnualLeaveDues(
        $employee,
        Carbon::parse('2026-08-23', 'Asia/Riyadh'),
    );

    expect($dues['amount'])->toBe(round(($dues['payable_days'] * 5000) / 30, 2));
    expect($dues['amount'])->not->toBe(round(($dues['payable_days'] * 6000) / 30, 2));
});

it('nets used leave out of annual leave dues payable amount', function (): void {
    $employee = makeSettlementEmployee([
        'leave_accrued_balance' => 21,
        'leave_days_used' => 5,
    ]);

    $service = app(EmployeeEntitlementSettlementService::class);

    $dues = $service->calculateAnnualLeaveDues(
        $employee,
        Carbon::parse('2026-08-23', 'Asia/Riyadh'),
    );

    expect($dues['used_days'])->toBe(5.0);
    expect($dues['payable_days'])->toBe(round($dues['accrued_days'] - 5.0, 2));
    expect($dues['amount'])->toBe(
        app(ManualDeductionAmountService::class)->fromLeavePayDays($employee, $dues['payable_days'])
    );
});

it('does not count future leave as elapsed used deduction', function (): void {
    $futureLeave = new \App\Models\Leave([
        'leave_type' => \App\Models\Leave::TYPE_ANNUAL,
        'start_date' => '2026-09-20',
        'end_date' => '2026-10-07',
        'days' => 18,
        'deduct_from_balance' => true,
        'is_paid' => true,
    ]);

    $leavesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $leavesQuery->shouldReceive('where')->with('deduct_from_balance', true)->andReturnSelf();
    $leavesQuery->shouldReceive('get')->andReturn(collect([$futureLeave]));

    $employee = Mockery::mock(Employee::class)->makePartial();
    $employee->id = 1;
    $employee->leave_days_used = 0;
    $employee->shouldReceive('leaves')->andReturn($leavesQuery);

    $service = app(EmployeeEntitlementSettlementService::class);

    $used = $service->calculateUsedAnnualLeaveDeduction(
        $employee,
        Carbon::parse('2026-09-13', 'Asia/Riyadh'),
    );

    expect($used['days'])->toBe(0.0);
    expect($used['amount'])->toBe(0.0);
});

it('nets future approved leave into annual leave payable days like the profile remaining', function (): void {
    $employee = makeSettlementEmployee([
        'hire_date' => '2021-06-14',
        'basic_salary' => 4000,
        'allowances' => 1400,
        'annual_leave_balance' => 30,
        'leave_accrued_balance' => 158.75,
        'leave_days_used' => 83.42,
    ]);

    $amount = app(ManualDeductionAmountService::class);
    $accrual = Mockery::mock(LeaveAccrualService::class);
    $accrual->shouldReceive('projectedAccruedBalanceThroughDate')->andReturn(158.75);

    $service = new class($amount, $accrual) extends EmployeeEntitlementSettlementService
    {
        public function previouslySettledLeaveDays(Employee $employee): float
        {
            return 0.0;
        }

        public function calculateBalanceCommittedLeaveDays(Employee $employee): float
        {
            return 115.42;
        }
    };

    $dues = $service->calculateAnnualLeaveDues(
        $employee,
        Carbon::parse('2026-09-29', 'Asia/Riyadh'),
    );

    expect($dues['accrued_days'])->toBe(158.75);
    expect($dues['used_days'])->toBe(115.42);
    expect($dues['payable_days'])->toBe(43.33);
    expect($dues['settle_days'])->toBe(43.33);
    expect($dues['amount'])->toBe($amount->fromLeavePayDays($employee, 43.33));
});

it('allows settling only part of the available annual leave days', function (): void {
    $employee = makeSettlementEmployee([
        'leave_accrued_balance' => 21,
        'leave_days_used' => 0,
    ]);

    $amount = app(ManualDeductionAmountService::class);
    $accrual = Mockery::mock(LeaveAccrualService::class);
    $accrual->shouldReceive('projectedAccruedBalanceThroughDate')->andReturn(43.33);

    $service = new class($amount, $accrual) extends EmployeeEntitlementSettlementService
    {
        public function previouslySettledLeaveDays(Employee $employee): float
        {
            return 0.0;
        }

        public function calculateBalanceCommittedLeaveDays(Employee $employee): float
        {
            return 0.0;
        }
    };

    $dues = $service->calculateAnnualLeaveDues(
        $employee,
        Carbon::parse('2026-09-29', 'Asia/Riyadh'),
        19,
    );

    expect($dues['payable_days'])->toBe(43.33);
    expect($dues['settle_days'])->toBe(19.0);
    expect($dues['amount'])->toBe($amount->fromLeavePayDays($employee, 19));
});

it('clamps settle days above the available payable balance', function (): void {
    $service = app(EmployeeEntitlementSettlementService::class);

    expect($service->normalizeSettleLeaveDays(50, 43.33))->toBe(43.33);
    expect($service->normalizeSettleLeaveDays(null, 43.33))->toBe(43.33);
    expect($service->normalizeSettleLeaveDays(-5, 43.33))->toBe(0.0);
});

it('counts only elapsed days for leave spanning the settlement date', function (): void {
    $leave = new \App\Models\Leave([
        'leave_type' => \App\Models\Leave::TYPE_ANNUAL,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-20',
        'days' => 11,
        'deduct_from_balance' => true,
        'is_paid' => true,
    ]);

    $leavesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $leavesQuery->shouldReceive('where')->with('deduct_from_balance', true)->andReturnSelf();
    $leavesQuery->shouldReceive('get')->andReturn(collect([$leave]));

    $employee = Mockery::mock(Employee::class)->makePartial();
    $employee->id = 1;
    $employee->shouldReceive('leaves')->andReturn($leavesQuery);

    $service = app(EmployeeEntitlementSettlementService::class);

    $elapsed = $service->calculateElapsedDeductibleLeaveDays(
        $employee,
        Carbon::parse('2026-09-13', 'Asia/Riyadh'),
    );

    expect($elapsed)->toBe(4.0);
});

it('deducts all recorded unpaid leave days when leave is fully inside salary period', function (): void {
    $unpaidLeave = new \App\Models\Leave([
        'leave_type' => \App\Models\Leave::TYPE_EMERGENCY,
        'start_date' => '2026-09-13',
        'end_date' => '2026-09-19',
        'days' => 7,
        'deduct_from_balance' => false,
        'is_paid' => false,
    ]);

    $leavesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $leavesQuery->shouldReceive('where')->with('is_paid', false)->andReturnSelf();
    $leavesQuery->shouldReceive('get')->andReturn(collect([$unpaidLeave]));

    $employee = Mockery::mock(Employee::class)->makePartial();
    $employee->id = 1;
    $employee->basic_salary = 4000;
    $employee->allowances = 1400;
    $employee->shouldReceive('leaves')->andReturn($leavesQuery);
    $employee->shouldReceive('getKey')->andReturn(1);

    $service = new class(app(ManualDeductionAmountService::class), app(LeaveAccrualService::class)) extends EmployeeEntitlementSettlementService
    {
        public function resolveUnpaidSalaryStartDate(Employee $employee, Carbon $settlementDate): ?Carbon
        {
            return Carbon::parse('2026-09-01', 'Asia/Riyadh');
        }
    };

    $result = $service->calculateSalaryDues(
        $employee,
        Carbon::parse('2026-09-19', 'Asia/Riyadh'),
    );

    expect($result['days'])->toBe(12.0);
    expect($result['amount'])->toBe(round((5400 / 30) * 12, 2));
});

it('excludes unpaid leave days from salary dues during the settlement period', function (): void {
    $employee = makeSettlementEmployee();

    $unpaidLeave = new \App\Models\Leave([
        'leave_type' => \App\Models\Leave::TYPE_EMERGENCY,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-14',
        'days' => 5,
        'deduct_from_balance' => false,
        'is_paid' => false,
    ]);

    $leavesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $leavesQuery->shouldReceive('where')->with('is_paid', false)->andReturnSelf();
    $leavesQuery->shouldReceive('get')->andReturn(collect([$unpaidLeave]));

    $employee = Mockery::mock(Employee::class)->makePartial();
    $employee->id = 1;
    $employee->basic_salary = 4000;
    $employee->allowances = 1400;
    $employee->shouldReceive('leaves')->andReturn($leavesQuery);
    $employee->shouldReceive('getKey')->andReturn(1);

    $service = new class(app(ManualDeductionAmountService::class), app(LeaveAccrualService::class)) extends EmployeeEntitlementSettlementService
    {
        public function resolveUnpaidSalaryStartDate(Employee $employee, Carbon $settlementDate): ?Carbon
        {
            return Carbon::parse('2026-08-01', 'Asia/Riyadh');
        }
    };

    $result = $service->calculateSalaryDues(
        $employee,
        Carbon::parse('2026-08-23', 'Asia/Riyadh'),
    );

    expect($result['days'])->toBe(18.0);
    expect($result['amount'])->toBe(round((5400 / 30) * 18, 2));
});

it('calculates salary dues from an explicit unpaid start date', function (): void {
    $employee = makeSettlementEmployee();

    $service = new class(app(ManualDeductionAmountService::class), app(LeaveAccrualService::class)) extends EmployeeEntitlementSettlementService
    {
        public function resolveUnpaidSalaryStartDate(Employee $employee, Carbon $settlementDate): ?Carbon
        {
            return Carbon::parse('2026-08-01', 'Asia/Riyadh');
        }
    };

    $result = $service->calculateSalaryDues(
        $employee,
        Carbon::parse('2026-08-23', 'Asia/Riyadh'),
    );

    expect($result['from'])->toBe('2026-08-01');
    expect($result['to'])->toBe('2026-08-23');
    expect($result['days'])->toBe(23.0);
    expect($result['amount'])->toBe(round((5400 / 30) * 23, 2));
});

it('calculates salary dues for a future settlement date across months', function (): void {
    $employee = makeSettlementEmployee();

    $service = new class(app(ManualDeductionAmountService::class), app(LeaveAccrualService::class)) extends EmployeeEntitlementSettlementService
    {
        public function resolveUnpaidSalaryStartDate(Employee $employee, Carbon $settlementDate): ?Carbon
        {
            return Carbon::parse('2026-08-01', 'Asia/Riyadh');
        }
    };

    $result = $service->calculateSalaryDues(
        $employee,
        Carbon::parse('2026-09-10', 'Asia/Riyadh'),
    );

    expect($result['days'])->toBe(41.0);
    expect($result['amount'])->toBe(round((5400 / 30) * 41, 2));
});

it('falls back to start of settlement month when hire date is missing', function (): void {
    $employee = makeSettlementEmployee([
        'hire_date' => null,
    ]);

    $service = new class(app(ManualDeductionAmountService::class), app(LeaveAccrualService::class)) extends EmployeeEntitlementSettlementService
    {
        public function resolveUnpaidSalaryStartDate(Employee $employee, Carbon $settlementDate): ?Carbon
        {
            return $settlementDate->copy()->startOfMonth();
        }
    };

    $result = $service->calculateSalaryDues(
        $employee,
        Carbon::parse('2026-09-10', 'Asia/Riyadh'),
    );

    expect($result['from'])->toBe('2026-09-01');
    expect($result['to'])->toBe('2026-09-10');
    expect($result['days'])->toBe(10.0);
    expect($result['amount'])->toBe(round((5400 / 30) * 10, 2));
});
