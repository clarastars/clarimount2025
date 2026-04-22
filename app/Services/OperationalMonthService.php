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
