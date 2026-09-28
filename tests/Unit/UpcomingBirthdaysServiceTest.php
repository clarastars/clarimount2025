<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Services\BirthdaySettingsService;
use App\Services\UpcomingBirthdaysService;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00', UpcomingBirthdaysService::TZ));
});

afterEach(function (): void {
    Carbon::setTestNow();
    \Mockery::close();
});

it('returns no upcoming birthdays when the widget is disabled', function (): void {
    $settings = \Mockery::mock(BirthdaySettingsService::class);
    $settings->shouldReceive('settings')->once()->andReturn([
        'enabled' => false,
        'scope' => BirthdaySettingsService::SCOPE_COMPANY,
        'days_ahead' => 5,
    ]);

    $service = new UpcomingBirthdaysService($settings);
    $viewer = new Employee([
        'id' => 1,
        'company_id' => 10,
        'department_id' => 20,
    ]);

    expect($service->forViewer($viewer))->toBe([]);
});

it('calculates the next birthday including year wrap and leap-day fallback', function (): void {
    $settings = \Mockery::mock(BirthdaySettingsService::class);
    $service = new UpcomingBirthdaysService($settings);
    $method = new ReflectionMethod(UpcomingBirthdaysService::class, 'nextBirthdayOccurrence');
    $method->setAccessible(true);

    $asOf = Carbon::parse('2026-12-30', UpcomingBirthdaysService::TZ)->startOfDay();
    $next = $method->invoke(
        $service,
        Carbon::parse('1990-01-02', UpcomingBirthdaysService::TZ)->startOfDay(),
        $asOf,
    );
    expect($next->toDateString())->toBe('2027-01-02');

    $leapAsOf = Carbon::parse('2027-02-28', UpcomingBirthdaysService::TZ)->startOfDay();
    $leapNext = $method->invoke(
        $service,
        Carbon::parse('2000-02-29', UpcomingBirthdaysService::TZ)->startOfDay(),
        $leapAsOf,
    );
    expect($leapNext->toDateString())->toBe('2027-02-28');
});

it('exposes the expected birthday widget scopes and default days', function (): void {
    expect(BirthdaySettingsService::SCOPES)->toBe([
        BirthdaySettingsService::SCOPE_ALL_COMPANIES,
        BirthdaySettingsService::SCOPE_COMPANY,
        BirthdaySettingsService::SCOPE_DEPARTMENT,
    ])->and(BirthdaySettingsService::DEFAULT_DAYS_AHEAD)->toBe(5);
});
