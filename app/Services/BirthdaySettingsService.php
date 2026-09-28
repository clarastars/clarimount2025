<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;

class BirthdaySettingsService
{
    public const SETTING_KEY = 'birthday_widget';

    public const SCOPE_ALL_COMPANIES = 'all_companies';

    public const SCOPE_COMPANY = 'company';

    public const SCOPE_DEPARTMENT = 'department';

    public const SCOPES = [
        self::SCOPE_ALL_COMPANIES,
        self::SCOPE_COMPANY,
        self::SCOPE_DEPARTMENT,
    ];

    public const DEFAULT_DAYS_AHEAD = 5;

    /**
     * @return array{enabled: bool, scope: string, days_ahead: int}
     */
    public function settings(): array
    {
        $raw = SystemSetting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $scope = (string) ($decoded['scope'] ?? self::SCOPE_COMPANY);
        if (! in_array($scope, self::SCOPES, true)) {
            $scope = self::SCOPE_COMPANY;
        }

        $daysAhead = (int) ($decoded['days_ahead'] ?? self::DEFAULT_DAYS_AHEAD);
        $daysAhead = max(0, min(365, $daysAhead));

        return [
            'enabled' => $this->toBool($decoded['enabled'] ?? false),
            'scope' => $scope,
            'days_ahead' => $daysAhead,
        ];
    }

    public function isEnabled(): bool
    {
        return $this->settings()['enabled'];
    }

    /**
     * @param  array{enabled?: bool, scope?: string, days_ahead?: int}  $payload
     */
    public function update(array $payload): void
    {
        $current = $this->settings();

        $scope = (string) ($payload['scope'] ?? $current['scope']);
        if (! in_array($scope, self::SCOPES, true)) {
            $scope = self::SCOPE_COMPANY;
        }

        $daysAhead = (int) ($payload['days_ahead'] ?? $current['days_ahead']);
        $daysAhead = max(0, min(365, $daysAhead));

        SystemSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'value' => json_encode([
                    'enabled' => (bool) ($payload['enabled'] ?? $current['enabled']),
                    'scope' => $scope,
                    'days_ahead' => $daysAhead,
                ], JSON_THROW_ON_ERROR),
            ],
        );
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}
