<?php

declare(strict_types=1);

use App\Models\AdvanceEntitlementTier;
use App\Models\Employee;
use App\Services\AdvanceEntitlementService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->service = new AdvanceEntitlementService;
    Carbon::setTestNow(Carbon::parse('2026-09-26 12:00:00', AdvanceEntitlementService::TZ));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeEntitlementEmployee(?string $hireDate): Employee
{
    $employee = new Employee;
    $employee->hire_date = $hireDate;

    return $employee;
}

it('returns null months of service when hire date is missing', function (): void {
    $employee = makeEntitlementEmployee(null);

    expect($this->service->monthsOfService($employee))->toBeNull()
        ->and($this->service->anniversaryPeriod($employee))->toBeNull();
});

it('blocks entitlement when hire date is missing', function (): void {
    $employee = makeEntitlementEmployee(null);

    $entitlement = $this->service->entitlementFor($employee);

    expect($entitlement['has_hire_date'])->toBeFalse()
        ->and($entitlement['can_request'])->toBeFalse()
        ->and($entitlement['block_reason'])->toBe('missing_hire_date');
});

it('calculates completed months of service from the hire date', function (): void {
    $employee = makeEntitlementEmployee('2026-01-01');

    expect($this->service->monthsOfService($employee))->toBe(8);
});

it('builds the current hire-anniversary window after the anniversary date', function (): void {
    $employee = makeEntitlementEmployee('2024-03-01');

    $period = $this->service->anniversaryPeriod($employee);

    expect($period)->not->toBeNull()
        ->and($period['start']->toDateString())->toBe('2026-03-01')
        ->and($period['end']->toDateString())->toBe('2027-03-01');
});

it('builds the current hire-anniversary window before the anniversary date', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-02-15', AdvanceEntitlementService::TZ));

    $employee = makeEntitlementEmployee('2024-03-01');
    $period = $this->service->anniversaryPeriod($employee);

    expect($period['start']->toDateString())->toBe('2025-03-01')
        ->and($period['end']->toDateString())->toBe('2026-03-01');
});

it('clamps february 29 anniversaries in non-leap years', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-03-01', AdvanceEntitlementService::TZ));

    $employee = makeEntitlementEmployee('2024-02-29');
    $period = $this->service->anniversaryPeriod($employee);

    expect($period['start']->toDateString())->toBe('2025-02-28')
        ->and($period['end']->toDateString())->toBe('2026-02-28');
});

it('matches inclusive tenure ranges on entitlement tiers', function (): void {
    $tier = new AdvanceEntitlementTier([
        'min_months' => 3,
        'max_months' => 11,
    ]);

    expect($tier->matchesTenure(2))->toBeFalse()
        ->and($tier->matchesTenure(3))->toBeTrue()
        ->and($tier->matchesTenure(11))->toBeTrue()
        ->and($tier->matchesTenure(12))->toBeFalse();
});

it('matches open-ended tenure ranges', function (): void {
    $tier = new AdvanceEntitlementTier([
        'min_months' => 24,
        'max_months' => null,
    ]);

    expect($tier->matchesTenure(23))->toBeFalse()
        ->and($tier->matchesTenure(24))->toBeTrue()
        ->and($tier->matchesTenure(120))->toBeTrue();
});

it('allows adjacent tenure ranges that only share a boundary month', function (): void {
    $service = new class extends AdvanceEntitlementService
    {
        public function overlapsPublic(int $aMin, ?int $aMax, int $bMin, ?int $bMax): bool
        {
            $method = new \ReflectionMethod(AdvanceEntitlementService::class, 'rangesOverlap');
            $method->setAccessible(true);

            return (bool) $method->invoke($this, $aMin, $aMax, $bMin, $bMax);
        }
    };

    expect($service->overlapsPublic(0, 3, 3, 12))->toBeFalse()
        ->and($service->overlapsPublic(3, 12, 0, 3))->toBeFalse()
        ->and($service->overlapsPublic(0, 3, 4, 12))->toBeFalse()
        ->and($service->overlapsPublic(0, 3, 2, 12))->toBeTrue()
        ->and($service->overlapsPublic(0, 3, 0, 3))->toBeTrue();
});
