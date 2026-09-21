<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\LeaveTypeRuleExemption;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaveTypeService
{
    /** @var array<int, bool> */
    private array $exemptionCache = [];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeOptions(?string $locale = null): array
    {
        return LeaveType::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (LeaveType $leaveType): array => $this->mapOption($leaveType, $locale))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForSettings(): array
    {
        return LeaveType::query()
            ->ordered()
            ->get()
            ->map(fn (LeaveType $leaveType): array => [
                'id' => $leaveType->id,
                'key' => $leaveType->key,
                'name_en' => $leaveType->name_en,
                'name_ar' => $leaveType->name_ar,
                'min_notice_days' => $leaveType->min_notice_days,
                'allow_past_dates' => (bool) $leaveType->allow_past_dates,
                'sort_order' => $leaveType->sort_order,
                'is_active' => $leaveType->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function activeKeys(): array
    {
        return LeaveType::query()
            ->active()
            ->ordered()
            ->pluck('key')
            ->values()
            ->all();
    }

    public function findActiveByKey(string $key): ?LeaveType
    {
        return LeaveType::query()
            ->active()
            ->where('key', $key)
            ->first();
    }

    public function findByKey(string $key): ?LeaveType
    {
        return LeaveType::query()
            ->withTrashed()
            ->where('key', $key)
            ->first();
    }

    public function labelForKey(?string $key, ?string $locale = null): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        $leaveType = $this->findByKey($key);

        return $leaveType?->displayName($locale) ?? $key;
    }

    public function ensureStartDateAllowed(LeaveType $leaveType, string $startDate, ?Employee $employee = null): void
    {
        if ($employee !== null && $this->employeeIsExemptFromRules($employee)) {
            return;
        }

        if ($leaveType->allow_past_dates) {
            return;
        }

        $today = now()->startOfDay();
        $requestedStart = Carbon::parse($startDate)->startOfDay();

        if ($requestedStart->lt($today)) {
            throw ValidationException::withMessages([
                'start_date' => [__('messages.leaves.start_date_must_be_today_or_later')],
            ]);
        }
    }

    public function ensureMinimumNoticeDays(LeaveType $leaveType, string $startDate, ?Employee $employee = null): void
    {
        if ($employee !== null && $this->employeeIsExemptFromRules($employee)) {
            return;
        }

        $minNoticeDays = max(0, (int) $leaveType->min_notice_days);

        if ($minNoticeDays === 0) {
            return;
        }

        $today = now()->startOfDay();
        $requestedStart = Carbon::parse($startDate)->startOfDay();

        // Past-dated requests are governed by allow_past_dates; skip advance-notice rules for them.
        if ($leaveType->allow_past_dates && $requestedStart->lt($today)) {
            return;
        }

        $noticeDays = (int) $today->diffInDays($requestedStart, false);

        if ($noticeDays < $minNoticeDays) {
            throw ValidationException::withMessages([
                'start_date' => [__('messages.leaves.min_notice_days_not_met', [
                    'leave_type' => $leaveType->displayName(),
                    'days' => $minNoticeDays,
                ])],
            ]);
        }
    }

    public function employeeIsExemptFromRules(int|Employee $employee): bool
    {
        $employeeId = $employee instanceof Employee ? (int) $employee->id : $employee;

        if (array_key_exists($employeeId, $this->exemptionCache)) {
            return $this->exemptionCache[$employeeId];
        }

        return $this->exemptionCache[$employeeId] = LeaveTypeRuleExemption::query()
            ->where('employee_id', $employeeId)
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exemptionsForSettings(): array
    {
        return LeaveTypeRuleExemption::query()
            ->with(['employee.company'])
            ->orderByDesc('id')
            ->get()
            ->map(function (LeaveTypeRuleExemption $exemption): array {
                $employee = $exemption->employee;

                return [
                    'id' => $exemption->id,
                    'employee_id' => $exemption->employee_id,
                    'full_name' => $employee?->full_name ?? ('#'.$exemption->employee_id),
                    'employee_code' => $employee?->employee_id,
                    'company_name' => $employee?->company?->name_ar ?: $employee?->company?->name_en,
                    'created_at' => $exemption->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function exemptEmployeeIds(): array
    {
        return LeaveTypeRuleExemption::query()
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, full_name: string, employee_id: string|null, company_name: string|null}>
     */
    public function searchEmployeesForExemption(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $like = '%'.$query.'%';
        $exemptIds = LeaveTypeRuleExemption::query()->pluck('employee_id');

        return Employee::query()
            ->with('company')
            ->whereNotIn('id', $exemptIds)
            ->where(function ($q) use ($like, $query): void {
                $q->where('first_name', 'like', $like)
                    ->orWhere('father_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('employee_id', 'like', $like)
                    ->orWhere('id_number', 'like', $like)
                    ->orWhereRaw(
                        "CONCAT_WS(' ', first_name, father_name, last_name) LIKE ?",
                        [$like]
                    );

                if (ctype_digit($query)) {
                    $q->orWhere('id', (int) $query);
                }
            })
            ->orderBy('first_name')
            ->orderBy('father_name')
            ->orderBy('last_name')
            ->limit($limit)
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id !== null ? (string) $employee->employee_id : null,
                'company_name' => $employee->company?->name_ar ?: $employee->company?->name_en,
            ])
            ->values()
            ->all();
    }

    public function addExemption(Employee $employee, ?User $actor = null): LeaveTypeRuleExemption
    {
        $existing = LeaveTypeRuleExemption::query()
            ->where('employee_id', $employee->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $exemption = LeaveTypeRuleExemption::query()->create([
            'employee_id' => $employee->id,
            'created_by' => $actor?->id,
        ]);

        $this->exemptionCache[(int) $employee->id] = true;

        return $exemption;
    }

    public function removeExemption(LeaveTypeRuleExemption $exemption): void
    {
        $employeeId = (int) $exemption->employee_id;
        $exemption->delete();
        unset($this->exemptionCache[$employeeId]);
    }

    public function buildKeyFromName(string $name): string
    {
        $normalized = trim(Str::of($name)->ascii()->lower()->slug('-')->value());

        return $normalized !== '' ? $normalized : 'leave-type';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOption(LeaveType $leaveType, ?string $locale = null): array
    {
        return [
            'key' => $leaveType->key,
            'name_en' => $leaveType->name_en,
            'name_ar' => $leaveType->name_ar,
            'label' => $leaveType->displayName($locale),
            'min_notice_days' => $leaveType->min_notice_days,
            'allow_past_dates' => (bool) $leaveType->allow_past_dates,
        ];
    }
}
