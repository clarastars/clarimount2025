<?php

declare(strict_types=1);

namespace App\Exports\Concerns;

use App\Models\Department;
use App\Models\Employee;
use App\Services\ManualDeductionAmountService;

trait ResolvesEmployeeProfileExportFields
{
    /** @var list<string> */
    public const ALLOWED_FIELDS = [
        'full_name',
        'id_number',
        'work_phone',
        'work_email',
        'basic_salary',
        'gross_salary',
        'job_title',
        'company',
        'department',
        'nationality',
        'hire_date',
    ];

    /** @var list<string> */
    protected const TEXT_FIELDS = [
        'id_number',
        'work_phone',
        'work_email',
    ];

    /** @var list<string> */
    protected const MONEY_FIELDS = [
        'basic_salary',
        'gross_salary',
    ];

    /**
     * @param  list<mixed>  $fields
     * @return list<string>
     */
    public static function normalizeFields(array $fields): array
    {
        $allowed = array_flip(self::ALLOWED_FIELDS);
        $normalized = [];

        foreach ($fields as $field) {
            if (! is_string($field)) {
                continue;
            }

            if (isset($allowed[$field]) && ! in_array($field, $normalized, true)) {
                $normalized[] = $field;
            }
        }

        return $normalized !== [] ? $normalized : self::ALLOWED_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function allowedFields(): array
    {
        return self::ALLOWED_FIELDS;
    }

    /**
     * @return list<string>
     */
    protected function fieldHeaders(): array
    {
        $headers = [];

        foreach ($this->fields as $field) {
            $headers[] = __('messages.employees.export_profile_fields.'.$field);
        }

        return $headers;
    }

    protected function resolveFieldValue(Employee $employee, string $field, ManualDeductionAmountService $amountService): string|float|int|null
    {
        return match ($field) {
            'full_name' => (string) ($employee->full_name ?? ''),
            'id_number' => (string) ($employee->id_number ?? ''),
            'work_phone' => (string) ($employee->work_phone ?? ''),
            'work_email' => (string) ($employee->work_email ?? ''),
            'basic_salary' => round((float) ($employee->basic_salary ?? 0), 2),
            'gross_salary' => $amountService->grossMonthly($employee),
            'job_title' => (string) ($employee->job_title ?? ''),
            'company' => (string) ($employee->company?->name_ar ?: $employee->company?->name_en ?: ''),
            'department' => $this->resolveDepartmentLabel($employee),
            'nationality' => (string) ($employee->nationality?->name ?? ''),
            'hire_date' => $employee->hire_date?->format('Y-m-d') ?? '',
            default => '',
        };
    }

    protected function resolveDepartmentLabel(Employee $employee): string
    {
        if ($employee->department instanceof Department) {
            return (string) $employee->department->name;
        }

        $legacy = $employee->getAttributes()['department'] ?? null;

        return is_string($legacy) ? $legacy : '';
    }
}
