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

    public const COMPANY_FILTER_ALL = 'all';

    public const COMPANY_FILTER_INCLUDE = 'include';

    public const COMPANY_FILTER_EXCLUDE = 'exclude';

    public const COMPANY_FILTERS = [
        self::COMPANY_FILTER_ALL,
        self::COMPANY_FILTER_INCLUDE,
        self::COMPANY_FILTER_EXCLUDE,
    ];

    public const DEFAULT_DAYS_AHEAD = 5;

    /**
     * @return array{
     *     enabled: bool,
     *     scope: string,
     *     days_ahead: int,
     *     company_filter_mode: string,
     *     company_ids: list<int>
     * }
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

        $companyFilterMode = (string) ($decoded['company_filter_mode'] ?? self::COMPANY_FILTER_ALL);
        if (! in_array($companyFilterMode, self::COMPANY_FILTERS, true)) {
            $companyFilterMode = self::COMPANY_FILTER_ALL;
        }

        $companyIds = $this->normalizeCompanyIds($decoded['company_ids'] ?? []);
        if ($companyFilterMode === self::COMPANY_FILTER_ALL) {
            $companyIds = [];
        }

        return [
            'enabled' => $this->toBool($decoded['enabled'] ?? false),
            'scope' => $scope,
            'days_ahead' => $daysAhead,
            'company_filter_mode' => $companyFilterMode,
            'company_ids' => $companyIds,
        ];
    }

    public function isEnabled(): bool
    {
        return $this->settings()['enabled'];
    }

    /**
     * Whether a company id is allowed by the global company filter.
     */
    public function allowsCompanyId(?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        $settings = $this->settings();
        $mode = (string) $settings['company_filter_mode'];
        /** @var list<int> $companyIds */
        $companyIds = $settings['company_ids'];

        if ($mode === self::COMPANY_FILTER_ALL || $companyIds === []) {
            return true;
        }

        if ($mode === self::COMPANY_FILTER_INCLUDE) {
            return in_array($companyId, $companyIds, true);
        }

        if ($mode === self::COMPANY_FILTER_EXCLUDE) {
            return ! in_array($companyId, $companyIds, true);
        }

        return true;
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     scope?: string,
     *     days_ahead?: int,
     *     company_filter_mode?: string,
     *     company_ids?: list<int|string>
     * }  $payload
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

        $companyFilterMode = (string) ($payload['company_filter_mode'] ?? $current['company_filter_mode']);
        if (! in_array($companyFilterMode, self::COMPANY_FILTERS, true)) {
            $companyFilterMode = self::COMPANY_FILTER_ALL;
        }

        $companyIds = array_key_exists('company_ids', $payload)
            ? $this->normalizeCompanyIds($payload['company_ids'])
            : $current['company_ids'];

        if ($companyFilterMode === self::COMPANY_FILTER_ALL) {
            $companyIds = [];
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'value' => json_encode([
                    'enabled' => (bool) ($payload['enabled'] ?? $current['enabled']),
                    'scope' => $scope,
                    'days_ahead' => $daysAhead,
                    'company_filter_mode' => $companyFilterMode,
                    'company_ids' => $companyIds,
                ], JSON_THROW_ON_ERROR),
            ],
        );
    }

    /**
     * @param  mixed  $value
     * @return list<int>
     */
    private function normalizeCompanyIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ids[] = $intId;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}
