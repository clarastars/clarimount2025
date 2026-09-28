<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UpcomingBirthdaysService
{
    public const TZ = 'Asia/Riyadh';

    public function __construct(
        private BirthdaySettingsService $settingsService,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     full_name: string,
     *     company_name: string|null,
     *     department_name: string|null,
     *     birth_date: string,
     *     birthday_month_day: string,
     *     days_until: int,
     *     is_today: bool,
     *     is_self: bool
     * }>
     */
    public function forViewer(?Employee $viewer, ?Carbon $asOf = null): array
    {
        $settings = $this->settingsService->settings();
        if (! $settings['enabled']) {
            return [];
        }

        $asOf = ($asOf ?? now(self::TZ))->copy()->timezone(self::TZ)->startOfDay();
        $daysAhead = (int) $settings['days_ahead'];
        $scope = (string) $settings['scope'];

        $query = Employee::query()
            ->with(['company:id,name_ar,name_en', 'department:id,name'])
            ->where('employment_status', 'active')
            ->whereNotNull('birth_date');

        $this->applyCompanyFilter($query, $settings);

        if ($scope === BirthdaySettingsService::SCOPE_COMPANY) {
            if ($viewer === null || $viewer->company_id === null) {
                return $this->maybeOnlySelfReminder($viewer, $asOf, $daysAhead);
            }
            if (! $this->settingsService->allowsCompanyId((int) $viewer->company_id)) {
                return [];
            }
            $query->where('company_id', $viewer->company_id);
        } elseif ($scope === BirthdaySettingsService::SCOPE_DEPARTMENT) {
            if ($viewer === null || $viewer->company_id === null || $viewer->department_id === null) {
                return $this->maybeOnlySelfReminder($viewer, $asOf, $daysAhead);
            }
            if (! $this->settingsService->allowsCompanyId((int) $viewer->company_id)) {
                return [];
            }
            $query->where('company_id', $viewer->company_id)
                ->where('department_id', $viewer->department_id);
        }

        $this->applyMonthDayWindow($query, $asOf, $daysAhead);

        /** @var Collection<int, Employee> $employees */
        $employees = $query->get(['id', 'first_name', 'father_name', 'last_name', 'birth_date', 'company_id', 'department_id', 'settings']);

        $viewerId = $viewer?->id;
        $rows = [];
        foreach ($employees as $employee) {
            $isSelf = $viewerId !== null && (int) $employee->id === (int) $viewerId;

            // Hidden birthday: never show to others; also hide personal reminder.
            if ($employee->hidesBirthday()) {
                continue;
            }

            $row = $this->buildRow($employee, $asOf, $daysAhead, $isSelf);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        usort($rows, static function (array $a, array $b): int {
            if ($a['is_self'] !== $b['is_self']) {
                return $a['is_self'] ? -1 : 1;
            }

            if ($a['days_until'] !== $b['days_until']) {
                return $a['days_until'] <=> $b['days_until'];
            }

            return strcmp($a['full_name'], $b['full_name']);
        });

        return $rows;
    }

    /**
     * When company/department scope cannot resolve peers, still allow a personal reminder.
     *
     * @return list<array<string, mixed>>
     */
    private function maybeOnlySelfReminder(?Employee $viewer, Carbon $asOf, int $daysAhead): array
    {
        if ($viewer === null || $viewer->hidesBirthday() || $viewer->birth_date === null) {
            return [];
        }

        if ($viewer->employment_status !== 'active') {
            return [];
        }

        if (! $this->settingsService->allowsCompanyId($viewer->company_id !== null ? (int) $viewer->company_id : null)) {
            return [];
        }

        $viewer->loadMissing(['company:id,name_ar,name_en', 'department:id,name']);
        $row = $this->buildRow($viewer, $asOf, $daysAhead, true);

        return $row !== null ? [$row] : [];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Employee>  $query
     * @param  array{company_filter_mode: string, company_ids: list<int>}  $settings
     */
    private function applyCompanyFilter($query, array $settings): void
    {
        $mode = (string) ($settings['company_filter_mode'] ?? BirthdaySettingsService::COMPANY_FILTER_ALL);
        /** @var list<int> $companyIds */
        $companyIds = $settings['company_ids'] ?? [];

        if ($mode === BirthdaySettingsService::COMPANY_FILTER_ALL || $companyIds === []) {
            return;
        }

        if ($mode === BirthdaySettingsService::COMPANY_FILTER_INCLUDE) {
            $query->whereIn('company_id', $companyIds);

            return;
        }

        if ($mode === BirthdaySettingsService::COMPANY_FILTER_EXCLUDE) {
            $query->whereNotIn('company_id', $companyIds);
        }
    }

    /**
     * @return array{
     *     id: int,
     *     full_name: string,
     *     company_name: string|null,
     *     department_name: string|null,
     *     birth_date: string,
     *     birthday_month_day: string,
     *     days_until: int,
     *     is_today: bool,
     *     is_self: bool
     * }|null
     */
    private function buildRow(Employee $employee, Carbon $asOf, int $daysAhead, bool $isSelf): ?array
    {
        $birthDate = $employee->birth_date;
        if ($birthDate === null) {
            return null;
        }

        $nextBirthday = $this->nextBirthdayOccurrence(
            Carbon::parse($birthDate->format('Y-m-d'), self::TZ)->startOfDay(),
            $asOf,
        );
        $daysUntil = (int) $asOf->diffInDays($nextBirthday, false);

        if ($daysUntil < 0 || $daysUntil > $daysAhead) {
            return null;
        }

        $company = $employee->company;
        $department = $employee->department;

        return [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'company_name' => $company?->getName(),
            'department_name' => $this->departmentDisplayName($department),
            'birth_date' => $birthDate->format('Y-m-d'),
            'birthday_month_day' => $nextBirthday->format('m-d'),
            'days_until' => $daysUntil,
            'is_today' => $daysUntil === 0,
            'is_self' => $isSelf,
        ];
    }

    private function nextBirthdayOccurrence(Carbon $birthDate, Carbon $asOf): Carbon
    {
        $month = (int) $birthDate->month;
        $day = (int) $birthDate->day;

        $candidate = $this->safeDate((int) $asOf->year, $month, $day);
        if ($candidate->lt($asOf)) {
            $candidate = $this->safeDate((int) $asOf->year + 1, $month, $day);
        }

        return $candidate;
    }

    /**
     * Narrow candidates in SQL so we do not load every active employee.
     * Year-wrap windows (e.g. Dec 30 → Jan 3) use an OR range.
     */
    private function applyMonthDayWindow($query, Carbon $asOf, int $daysAhead): void
    {
        $startMd = (int) $asOf->format('md');
        $endMd = (int) $asOf->copy()->addDays($daysAhead)->format('md');

        if ($startMd <= $endMd) {
            $query->whereRaw(
                'CAST(DATE_FORMAT(birth_date, "%m%d") AS UNSIGNED) BETWEEN ? AND ?',
                [$startMd, $endMd],
            );

            return;
        }

        $query->where(function ($inner) use ($startMd, $endMd): void {
            $inner->whereRaw('CAST(DATE_FORMAT(birth_date, "%m%d") AS UNSIGNED) >= ?', [$startMd])
                ->orWhereRaw('CAST(DATE_FORMAT(birth_date, "%m%d") AS UNSIGNED) <= ?', [$endMd]);
        });
    }

    private function safeDate(int $year, int $month, int $day): Carbon
    {
        $maxDay = (int) Carbon::create($year, $month, 1, 0, 0, 0, self::TZ)->daysInMonth;
        $safeDay = min($day, $maxDay);

        return Carbon::create($year, $month, $safeDay, 0, 0, 0, self::TZ)->startOfDay();
    }

    private function departmentDisplayName(mixed $department): ?string
    {
        if ($department === null) {
            return null;
        }

        $value = trim((string) ($department->name ?? ''));

        return $value !== '' ? $value : null;
    }
}
