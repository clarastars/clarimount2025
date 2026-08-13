<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;

class SalaryCertificateFeeService
{
    public const SETTING_KEY = 'salary_certificate_chamber_fee';

    public function chamberFee(): float
    {
        $value = SystemSetting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        if ($value === null || trim((string) $value) === '') {
            return 0.0;
        }

        return round(max(0, (float) $value), 2);
    }

    public function setChamberFee(float $amount): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => number_format(max(0, $amount), 2, '.', '')],
        );
    }
}
