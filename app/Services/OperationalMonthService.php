<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class OperationalMonthService
{
    private const TZ = 'Asia/Riyadh';

    private const START_DAY_KEY = 'operational_month_start_day';

    private const END_DAY_KEY = 'operational_month_end_day';

    public function getSettings(): array
    {
        $startDay = $this->getIntSetting(self::START_DAY_KEY);
        $endDay = $this->getIntSetting(self::END_DAY_KEY);
        $isCustom = $this->isCustomMonthEnabled($startDay, $endDay);

        return [
            'start_day' => $isCustom ? $startDay : null,
            'end_day' => $isCustom ? $endDay : null,
            'is_custom' => $isCustom,
        ];
    }

    public function saveSettings(?int $startDay, ?int $endDay): void
    {
        if ($startDay === null || $endDay === null) {
            $this->forgetSetting(self::START_DAY_KEY);
            $this->forgetSetting(self::END_DAY_KEY);

            return;
        }

        $this->putSetting(self::START_DAY_KEY, (string) $startDay);
        $this->putSetting(self::END_DAY_KEY, (string) $endDay);
    }

    public function resolveCurrentOperationalMonthRange(?Carbon $referenceDate = null): array
    {
        $referenceDate = ($referenceDate ?? Carbon::now(self::TZ))->copy()->setTimezone(self::TZ);

        return $this->resolveRangeForPayrollMonth(
            (int) $referenceDate->format('Y'),
            (int) $referenceDate->format('n')
        );
    }

    /**
     * Normalize ?month=YYYY-MM into payroll year/month and canonical string (Asia/Riyadh).
     *
     * @return array{year: int, month: int, ym: string}
     */
    public function parseCanonicalPayrollYm(?string $ym): array
    {
        $now = Carbon::now(self::TZ);
        $defaultYear = (int) $now->format('Y');
        $defaultMonth = (int) $now->format('n');

        if ($ym === null || trim($ym) === '') {
            return [
                'year' => $defaultYear,
                'month' => $defaultMonth,
                'ym' => sprintf('%04d-%02d', $defaultYear, $defaultMonth),
            ];
        }

        $parts = explode('-', trim($ym), 2);
        $year = (int) ($parts[0] ?? $defaultYear);
        $month = (int) ($parts[1] ?? $defaultMonth);

        if ($year < 1970 || $year > 2100) {
            $year = $defaultYear;
        }
        if ($month < 1 || $month > 12) {
            $month = $defaultMonth;
        }

        return [
            'year' => $year,
            'month' => $month,
            'ym' => sprintf('%04d-%02d', $year, $month),
        ];
    }

    public function resolveRangeForPayrollMonth(int $year, int $month): array
    {
        $baseMonth = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TZ);
        $settings = $this->getSettings();

        if (! $settings['is_custom']) {
            return [
                'start' => $baseMonth->startOfMonth()->toMutable(),
                'end' => $baseMonth->endOfMonth()->endOfDay()->toMutable(),
            ];
        }

        $startDay = (int) $settings['start_day'];
        $endDay = (int) $settings['end_day'];

        return [
            'start' => $this->createDateWithClampedDay($baseMonth->subMonthNoOverflow()->startOfMonth(), $startDay)->startOfDay()->toMutable(),
            'end' => $this->createDateWithClampedDay($baseMonth->startOfMonth(), $endDay)->endOfDay()->toMutable(),
        ];
    }

    /**
     * Payroll month label (year + month) whose operational range contains $date.
     * Matches calendar month when custom operational month is disabled.
     *
     * @return array{year: int, month: int}
     */
    public function resolvePayrollMonthForDate(Carbon|string $date): array
    {
        $day = $date instanceof Carbon
            ? $date->copy()->setTimezone(self::TZ)->startOfDay()
            : Carbon::parse((string) $date, self::TZ)->startOfDay();

        $settings = $this->getSettings();
        if (! $settings['is_custom']) {
            return [
                'year' => (int) $day->format('Y'),
                'month' => (int) $day->format('n'),
            ];
        }

        $y = (int) $day->format('Y');
        $m = (int) $day->format('n');
        $previousMonth = $day->copy()->subMonthNoOverflow();
        $nextMonth = $day->copy()->addMonthNoOverflow();
        $candidates = [
            [$y, $m],
            [(int) $previousMonth->format('Y'), (int) $previousMonth->format('n')],
            [(int) $nextMonth->format('Y'), (int) $nextMonth->format('n')],
        ];

        $seen = [];
        foreach ($candidates as [$cy, $cm]) {
            $key = $cy.'-'.$cm;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $range = $this->resolveRangeForPayrollMonth($cy, $cm);
            $start = $range['start']->copy()->startOfDay();
            $end = $range['end']->copy()->endOfDay();
            if ($day->gte($start) && $day->lte($end)) {
                return ['year' => $cy, 'month' => $cm];
            }
        }

        return ['year' => $y, 'month' => $m];
    }

    /**
     * Operational [start, end] range that contains $date (custom boundaries when enabled).
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function resolveOperationalMonthRangeContainingDate(Carbon|string $date): array
    {
        $payroll = $this->resolvePayrollMonthForDate($date);

        return $this->resolveRangeForPayrollMonth($payroll['year'], $payroll['month']);
    }

    private function isCustomMonthEnabled(?int $startDay, ?int $endDay): bool
    {
        if ($startDay === null || $endDay === null) {
            return false;
        }

        return $startDay >= 1 && $startDay <= 31
            && $endDay >= 1 && $endDay <= 31
            && $startDay > $endDay;
    }

    private function getIntSetting(string $key): ?int
    {
        $value = SystemSetting::query()->where('key', $key)->value('value');

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function putSetting(string $key, string $value): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    private function forgetSetting(string $key): void
    {
        SystemSetting::query()->where('key', $key)->delete();
    }

    private function createDateWithClampedDay(CarbonImmutable $month, int $day): CarbonImmutable
    {
        $safeDay = min($day, $month->daysInMonth);

        return $month->setDay($safeDay);
    }
}
