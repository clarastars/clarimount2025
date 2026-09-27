<?php

declare(strict_types=1);

use App\Services\AdvanceAmountService;
use App\Services\ManualDeductionAmountService;

beforeEach(function (): void {
    $this->service = new AdvanceAmountService(new ManualDeductionAmountService);
});

it('offers the full advance ladder up to 10000', function (): void {
    expect($this->service->allAmountOptions())->toBe([
        200, 500, 1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000,
    ]);
});

it('only offers monthly amounts strictly below the gross monthly salary', function (): void {
    expect($this->service->optionsForGross(3000.0))->toBe([200, 500, 1000, 2000]);
});

it('excludes a monthly amount equal to the gross monthly salary', function (): void {
    expect($this->service->optionsForGross(1000.0))->toBe([200, 500]);
});

it('returns no monthly options when the gross salary is below the smallest amount', function (): void {
    expect($this->service->optionsForGross(150.0))->toBe([]);
});

it('builds an even repayment schedule when the amount divides exactly', function (): void {
    $plan = $this->service->buildRepaymentSchedule(3000.0, 1000.0);

    expect($plan['months_count'])->toBe(3)
        ->and($plan['monthly_deduction'])->toBe(1000.0)
        ->and($plan['schedule'])->toBe([
            ['month_index' => 1, 'amount' => 1000.0],
            ['month_index' => 2, 'amount' => 1000.0],
            ['month_index' => 3, 'amount' => 1000.0],
        ]);
});

it('pays the remainder in the final month', function (): void {
    $plan = $this->service->buildRepaymentSchedule(5000.0, 2000.0);

    expect($plan['months_count'])->toBe(3)
        ->and($plan['schedule'])->toBe([
            ['month_index' => 1, 'amount' => 2000.0],
            ['month_index' => 2, 'amount' => 2000.0],
            ['month_index' => 3, 'amount' => 1000.0],
        ]);
});

it('repays in a single month when the deduction covers the whole advance', function (): void {
    $plan = $this->service->buildRepaymentSchedule(500.0, 500.0);

    expect($plan['months_count'])->toBe(1)
        ->and($plan['schedule'])->toBe([
            ['month_index' => 1, 'amount' => 500.0],
        ]);
});

it('caps the monthly deduction at the advance amount', function (): void {
    $plan = $this->service->buildRepaymentSchedule(500.0, 2000.0);

    expect($plan['months_count'])->toBe(1)
        ->and($plan['schedule'][0]['amount'])->toBe(500.0);
});

it('returns an empty plan for non-positive input', function (): void {
    expect($this->service->buildRepaymentSchedule(0.0, 500.0))
        ->toBe(['months_count' => 0, 'schedule' => [], 'monthly_deduction' => 0.0]);
});

it('builds equal installments from a fixed installment count', function (): void {
    $plan = $this->service->buildRepaymentScheduleForInstallments(300.0, 3);

    expect($plan['months_count'])->toBe(3)
        ->and($plan['monthly_deduction'])->toBe(100.0)
        ->and($plan['schedule'])->toBe([
            ['month_index' => 1, 'amount' => 100.0],
            ['month_index' => 2, 'amount' => 100.0],
            ['month_index' => 3, 'amount' => 100.0],
        ]);
});

it('puts the remainder in the last installment when amount does not divide evenly', function (): void {
    $plan = $this->service->buildRepaymentScheduleForInstallments(1000.0, 3);

    expect($plan['months_count'])->toBe(3)
        ->and($plan['schedule'][0]['amount'])->toBe(333.33)
        ->and($plan['schedule'][1]['amount'])->toBe(333.33)
        ->and($plan['schedule'][2]['amount'])->toBe(333.34);
});
