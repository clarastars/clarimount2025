<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EmployeesMissingHireDateExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, object>  $employees
     */
    public function __construct(
        private readonly Collection $employees,
    ) {}

    public function sheets(): array
    {
        $grouped = $this->employees
            ->groupBy(fn ($row) => (string) ($row->company_id ?? 'none'))
            ->sortKeys();

        $summaryRows = $grouped
            ->map(function (Collection $employees, string $companyKey) {
                $first = $employees->first();

                return [
                    'company_id' => $companyKey === 'none' ? null : (int) $companyKey,
                    'company_name_en' => (string) ($first->company_name_en ?: 'No Company'),
                    'company_name_ar' => (string) ($first->company_name_ar ?: 'بدون شركة'),
                    'employees_count' => $employees->count(),
                ];
            })
            ->values();

        $sheets = [
            new EmployeesMissingHireDateSummarySheet($summaryRows, $this->employees->count()),
        ];

        $usedTitles = ['Summary' => true];

        foreach ($grouped as $employees) {
            $first = $employees->first();
            $rawTitle = trim((string) ($first->company_name_en ?: $first->company_name_ar ?: 'No Company'));
            $sheets[] = new EmployeesMissingHireDateCompanySheet(
                $this->uniqueSheetTitle($rawTitle, $usedTitles),
                $employees->values(),
            );
        }

        return $sheets;
    }

    /**
     * Excel sheet titles are limited to 31 characters and must be unique.
     *
     * @param  array<string, true>  $usedTitles
     */
    private function uniqueSheetTitle(string $rawTitle, array &$usedTitles): string
    {
        $sanitized = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $rawTitle) ?? 'Company';
        $sanitized = trim(preg_replace('/\s+/', ' ', $sanitized) ?? 'Company');
        if ($sanitized === '') {
            $sanitized = 'Company';
        }

        $base = mb_substr($sanitized, 0, 28);
        $title = $base;
        $suffix = 2;

        while (isset($usedTitles[$title])) {
            $title = mb_substr($base, 0, 28 - strlen((string) $suffix)).' '.$suffix;
            $suffix++;
        }

        $usedTitles[$title] = true;

        return $title;
    }
}
