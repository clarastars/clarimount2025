<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EmployeeExpiryService
{
    public const DEFAULT_DAYS_THRESHOLD = 60;

    /**
     * @param  Collection<int, int>|array<int, int>  $companyIds
     * @return Collection<int, array{
     *   employee_id:int,
     *   display_name:string,
     *   full_name:string,
     *   expiry_field:string,
     *   expiry_label_key:string,
     *   expiry_date:string,
     *   days_remaining:int
     * }>
     */
    public function getExpiringEmployees(Collection|array $companyIds, int $days = self::DEFAULT_DAYS_THRESHOLD): Collection
    {
        // Backwards-compatible name: returns ALL expiring documents as rows (not just one per employee)
        return $this->getExpiringDocumentRows($companyIds, $days);
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $companyIds
     * @return Collection<int, array{
     *   employee_id:int,
     *   display_name:string,
     *   full_name:string,
     *   expiry_field:string,
     *   expiry_label_key:string,
     *   expiry_date:string,
     *   days_remaining:int
     * }>
     */
    public function getExpiringEmployeesPreview(Collection|array $companyIds, int $days = self::DEFAULT_DAYS_THRESHOLD, int $limit = 5): Collection
    {
        return $this->getExpiringEmployees($companyIds, $days)->take($limit)->values();
    }

    /**
     * Returns a row for EACH document that is expiring within the threshold.
     *
     * @param  Collection<int, int>|array<int, int>  $companyIds
     * @return Collection<int, array{
     *   employee_id:int,
     *   display_name:string,
     *   full_name:string,
     *   expiry_field:string,
     *   expiry_label_key:string,
     *   expiry_date:string,
     *   days_remaining:int
     * }>
     */
    public function getExpiringDocumentRows(Collection|array $companyIds, int $days = self::DEFAULT_DAYS_THRESHOLD): Collection
    {
        $companyIds = $companyIds instanceof Collection ? $companyIds->values()->all() : $companyIds;

        $cutoff = now()->startOfDay()->addDays($days);

        $employees = Employee::query()
            ->active()
            ->whereIn('company_id', $companyIds)
            ->where(function ($q) use ($cutoff) {
                $q->whereDate('probation_end_date', '<=', $cutoff)
                    ->orWhereDate('residence_expiry_date', '<=', $cutoff)
                    ->orWhereDate('contract_end_date', '<=', $cutoff)
                    ->orWhereDate('exit_reentry_visa_expiry', '<=', $cutoff)
                    ->orWhereDate('passport_expiry_date', '<=', $cutoff)
                    ->orWhereDate('insurance_expiry_date', '<=', $cutoff);
            })
            ->get([
                'id',
                'employee_id',
                'first_name',
                'last_name',
                'probation_end_date',
                'residence_expiry_date',
                'contract_end_date',
                'exit_reentry_visa_expiry',
                'passport_expiry_date',
                'insurance_expiry_date',
            ]);

        $today = now()->startOfDay();

        $rows = $employees->flatMap(function (Employee $employee) use ($today, $days) {
            $displayName = (string) ($employee->display_name ?? (($employee->employee_id ?? '') . ' - ' . $employee->first_name . ' ' . $employee->last_name));
            $fullName = (string) ($employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name));

            return collect($this->getEmployeeExpiryCandidates($employee))
                ->map(function (array $candidate) use ($today, $employee, $displayName, $fullName) {
                    /** @var CarbonInterface $date */
                    $date = $candidate['date'];
                    $daysRemaining = $today->diffInDays($date->copy()->startOfDay(), false);

                    return [
                        'employee_id' => (int) $employee->id,
                        'display_name' => $displayName,
                        'full_name' => $fullName,
                        'expiry_field' => $candidate['field'],
                        'expiry_label_key' => $candidate['label_key'],
                        'expiry_date' => $date->toDateString(),
                        'days_remaining' => (int) $daysRemaining,
                    ];
                })
                ->filter(fn (array $row) => $row['days_remaining'] >= 0 && $row['days_remaining'] <= $days)
                ->values();
        })->values();

        return $rows->sortBy('days_remaining')->values();
    }

    /**
     * Returns a row for EACH document that has already expired.
     *
     * @param  Collection<int, int>|array<int, int>  $companyIds
     * @return Collection<int, array{
     *   employee_id:int,
     *   display_name:string,
     *   full_name:string,
     *   expiry_field:string,
     *   expiry_label_key:string,
     *   expiry_date:string,
     *   days_remaining:int
     * }>
     */
    public function getExpiredDocumentRows(Collection|array $companyIds): Collection
    {
        $companyIds = $companyIds instanceof Collection ? $companyIds->values()->all() : $companyIds;

        $today = now()->startOfDay();

        $employees = Employee::query()
            ->active()
            ->whereIn('company_id', $companyIds)
            ->where(function ($q) use ($today) {
                $q->whereDate('probation_end_date', '<', $today)
                    ->orWhereDate('residence_expiry_date', '<', $today)
                    ->orWhereDate('contract_end_date', '<', $today)
                    ->orWhereDate('exit_reentry_visa_expiry', '<', $today)
                    ->orWhereDate('passport_expiry_date', '<', $today)
                    ->orWhereDate('insurance_expiry_date', '<', $today);
            })
            ->get([
                'id',
                'employee_id',
                'first_name',
                'last_name',
                'probation_end_date',
                'residence_expiry_date',
                'contract_end_date',
                'exit_reentry_visa_expiry',
                'passport_expiry_date',
                'insurance_expiry_date',
            ]);

        $rows = $employees->flatMap(function (Employee $employee) use ($today) {
            $displayName = (string) ($employee->display_name ?? (($employee->employee_id ?? '') . ' - ' . $employee->first_name . ' ' . $employee->last_name));
            $fullName = (string) ($employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name));

            return collect($this->getEmployeeExpiryCandidates($employee))
                ->map(function (array $candidate) use ($today, $employee, $displayName, $fullName) {
                    /** @var CarbonInterface $date */
                    $date = $candidate['date'];
                    $daysRemaining = $today->diffInDays($date->copy()->startOfDay(), false);

                    return [
                        'employee_id' => (int) $employee->id,
                        'display_name' => $displayName,
                        'full_name' => $fullName,
                        'expiry_field' => $candidate['field'],
                        'expiry_label_key' => $candidate['label_key'],
                        'expiry_date' => $date->toDateString(),
                        'days_remaining' => (int) $daysRemaining,
                    ];
                })
                ->filter(fn (array $row) => $row['days_remaining'] < 0)
                ->values();
        })->values();

        return $rows->sortByDesc('days_remaining')->values();
    }

    /**
     * @return array<int, array{field:string,label_key:string,date:CarbonInterface}>
     */
    private function getEmployeeExpiryCandidates(Employee $employee): array
    {
        $map = [
            'probation_end_date' => 'employees.expiry.probation_end_date',
            'residence_expiry_date' => 'employees.expiry.residence_expiry_date',
            'contract_end_date' => 'employees.expiry.contract_end_date',
            'exit_reentry_visa_expiry' => 'employees.expiry.exit_reentry_visa_expiry',
            'passport_expiry_date' => 'employees.expiry.passport_expiry_date',
            'insurance_expiry_date' => 'employees.expiry.insurance_expiry_date',
        ];

        $candidates = [];

        foreach ($map as $field => $labelKey) {
            $value = $employee->getAttribute($field);
            if (!$value instanceof CarbonInterface) {
                continue;
            }

            $candidates[] = [
                'field' => $field,
                'label_key' => $labelKey,
                'date' => $value,
            ];
        }

        return $candidates;
    }
}


