<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Services\EmployeeEntitlementSettlementService;
use App\Services\LeaveBalanceService;
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

it('calculates annual leave dues from projected remaining days and gross salary', function (): void {
    $employee = makeSettlementEmployee();

    $leaveBalanceService = Mockery::mock(LeaveBalanceService::class);
    $leaveBalanceService->shouldReceive('forecastForDate')
        ->once()
        ->andReturn([
            'projected_remaining' => 16.975,
        ]);

    $service = new EmployeeEntitlementSettlementService(
        app(ManualDeductionAmountService::class),
        $leaveBalanceService,
    );

    $result = $service->calculateAnnualLeaveDues(
        $employee,
        Carbon::parse('2026-08-23', 'Asia/Riyadh'),
    );

    expect($result['days'])->toBe(16.98);
    expect($result['amount'])->toBe(app(ManualDeductionAmountService::class)->fromGrossDays($employee, 16.98));
});

it('calculates salary dues from an explicit unpaid start date', function (): void {
    $employee = makeSettlementEmployee();

    $service = new class(app(ManualDeductionAmountService::class), app(LeaveBalanceService::class)) extends EmployeeEntitlementSettlementService
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

    $service = new class(app(ManualDeductionAmountService::class), app(LeaveBalanceService::class)) extends EmployeeEntitlementSettlementService
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

    $service = new class(app(ManualDeductionAmountService::class), app(LeaveBalanceService::class)) extends EmployeeEntitlementSettlementService
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
