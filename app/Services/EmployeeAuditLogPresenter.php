<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Country;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;
use OwenIt\Auditing\Contracts\Audit;

class EmployeeAuditLogPresenter
{
    private const TZ = 'Asia/Riyadh';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forEmployee(Employee $employee, int $limit = 100): array
    {
        return $employee->audits()
            ->with('user')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Audit $audit): array => $this->transformAudit($audit))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function transformAudit(Audit $audit): array
    {
        $oldValues = collect($audit->getAttribute('old_values') ?? []);
        $newValues = collect($audit->getAttribute('new_values') ?? []);
        $fieldKeys = $oldValues->keys()->merge($newValues->keys())->unique()->values();

        return [
            'id' => (int) $audit->getAttribute('id'),
            'event' => (string) $audit->getAttribute('event'),
            'event_label' => $this->eventLabel((string) $audit->getAttribute('event')),
            'performed_at' => $this->formatDateTime($audit->getAttribute('created_at')),
            'user' => [
                'id' => $audit->user?->getKey(),
                'name' => $audit->user?->name,
                'email' => $audit->user?->email,
            ],
            'changes' => $fieldKeys
                ->map(fn (string $field): array => [
                    'field' => $field,
                    'label' => $this->fieldLabel($field),
                    'old' => $this->formatFieldValue($field, $oldValues->get($field)),
                    'new' => $this->formatFieldValue($field, $newValues->get($field)),
                ])
                ->filter(fn (array $change): bool => $change['old'] !== $change['new'])
                ->values()
                ->all(),
        ];
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'created' => __('messages.employees.audit_event_created'),
            'updated' => __('messages.employees.audit_event_updated'),
            'deleted' => __('messages.employees.audit_event_deleted'),
            'restored' => __('messages.employees.audit_event_restored'),
            default => $event,
        };
    }

    private function fieldLabel(string $field): string
    {
        $key = 'messages.employees.'.$field;

        return __($key) !== $key ? __($key) : str_replace('_', ' ', $field);
    }

    private function formatFieldValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? __('messages.common.yes') : __('messages.common.no');
        }

        if (in_array($value, [0, '0', 1, '1'], true) && $this->isBooleanField($field)) {
            return (int) $value === 1 ? __('messages.common.yes') : __('messages.common.no');
        }

        return match ($field) {
            'company_id' => $this->resolveCompanyName((int) $value),
            'department_id' => $this->resolveDepartmentName((string) $value),
            'shift_id' => $this->resolveShiftName((int) $value),
            'nationality_id', 'residence_country_id' => $this->resolveCountryName((int) $value),
            'employment_status' => __('messages.employees.status_'.$value) !== 'messages.employees.status_'.$value
                ? __('messages.employees.status_'.$value)
                : (string) $value,
            'basic_salary', 'allowances', 'allowance_housing', 'allowance_transportation',
            'allowance_other', 'allowance_food', 'allowance_personal_car',
            'leave_accrued_balance', 'leave_days_used' => number_format((float) $value, 2),
            'social_insurance_deduction_rate' => number_format((float) $value, 2).'%',
            default => $this->formatGenericValue($field, $value),
        };
    }

    private function formatGenericValue(string $field, mixed $value): string
    {
        if ($this->isDateField($field)) {
            return $this->formatDate($value);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) $value;
    }

    private function isDateField(string $field): bool
    {
        return str_ends_with($field, '_date')
            || str_ends_with($field, '_expiry')
            || $field === 'birth_date';
    }

    private function isBooleanField(string $field): bool
    {
        return $field === 'excluded_from_salary';
    }

    private function formatDate(mixed $value): string
    {
        try {
            return Carbon::parse((string) $value, self::TZ)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->timezone(self::TZ)->toIso8601String();
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function resolveCompanyName(int $companyId): string
    {
        if ($companyId <= 0) {
            return '-';
        }

        $company = Company::query()->find($companyId);

        if ($company === null) {
            return (string) $companyId;
        }

        return trim(($company->name_ar ?? '').' '.($company->name_en ?? '')) ?: (string) $companyId;
    }

    private function resolveDepartmentName(string $departmentId): string
    {
        if ($departmentId === '') {
            return '-';
        }

        return Department::query()->find($departmentId)?->name ?? $departmentId;
    }

    private function resolveShiftName(int $shiftId): string
    {
        if ($shiftId <= 0) {
            return '-';
        }

        return Shift::query()->find($shiftId)?->name ?? (string) $shiftId;
    }

    private function resolveCountryName(int $countryId): string
    {
        if ($countryId <= 0) {
            return '-';
        }

        $country = Country::query()->find($countryId);

        return $country?->name_ar ?? $country?->name_en ?? (string) $countryId;
    }
}
