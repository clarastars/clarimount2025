<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceDailyPresentation;
use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\ZkDailyAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds and persists rows for the attendance index UI (status_ar, late_minutes, punches, virtual absences)
 * and applies absence-penalty logic. The controller reads only from attendance_daily_presentations + penalties.
 */
class AttendancePresentationRebuildService
{
    private const TZ = 'Asia/Riyadh';

    public function __construct(
        private AttendancePenaltyService $penaltyService,
        private OperationalMonthService $operationalMonthService
    ) {}

    public function rebuildDateForAllCompanies(string $attDateYmd): void
    {
        $companyIds = Employee::query()
            ->whereNotNull('company_id')
            ->distinct()
            ->pluck('company_id')
            ->filter()
            ->values();

        foreach ($companyIds as $companyId) {
            $this->rebuildCompanyDateRange((int) $companyId, $attDateYmd, $attDateYmd);
        }
    }

    public function rebuildCurrentMonthForAllCompanies(): void
    {
        $now = Carbon::now(self::TZ);
        $operationalRange = $this->operationalMonthService->resolveCurrentOperationalMonthRange($now);
        $start = $operationalRange['start']->format('Y-m-d');
        $end = Carbon::today(self::TZ)->min($operationalRange['end']->copy()->startOfDay())->format('Y-m-d');

        $companyIds = Employee::query()
            ->whereNotNull('company_id')
            ->distinct()
            ->pluck('company_id')
            ->filter()
            ->values();

        foreach ($companyIds as $companyId) {
            $this->rebuildCompanyDateRange((int) $companyId, $start, $end);
        }
    }

    public function rebuildCompanyDateRange(int $companyId, string $startDateYmd, string $endDateYmd): void
    {
        $startDate = Carbon::parse($startDateYmd, self::TZ)->startOfDay();
        $endDate = Carbon::parse($endDateYmd, self::TZ)->endOfDay();
        $today = Carbon::today(self::TZ);

        if ($endDate->gt($today)) {
            $endDate = $today->copy()->endOfDay();
        }

        if ($startDate->gt($endDate)) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Employee> $allEmployees */
        $allEmployees = Employee::with(['shift.workdays'])
            ->where('company_id', $companyId)
            ->get();

        if ($allEmployees->isEmpty()) {
            AttendanceDailyPresentation::where('company_id', $companyId)
                ->whereBetween('att_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->delete();

            return;
        }

        $shiftWorkdayMaps = [];
        foreach ($allEmployees as $employee) {
            if ($employee->shift) {
                $shiftWorkdayMaps[$employee->id] = $employee->shift->workdays
                    ->where('is_workday', true)
                    ->pluck('weekday')
                    ->toArray();
            }
        }

        $employeesKeyed = $allEmployees->keyBy('id');

        $existingAttendance = ZkDailyAttendance::query()
            ->select([
                'zk_daily_attendance.*',
                'employees.id as employee_id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_id as emp_code',
                'employees.company_id',
                'zk_devices.name as device_name',
                'zk_devices.serial_number',
            ])
            ->leftJoin('employees', function ($join) {
                $join->on('employees.fingerprint_device_id', '=', 'zk_daily_attendance.device_pin');
            })
            ->leftJoin('zk_devices', 'zk_devices.id', '=', 'zk_daily_attendance.device_id')
            ->whereBetween('zk_daily_attendance.att_date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            ])
            ->where('employees.company_id', $companyId)
            ->orderByRaw("(zk_devices.serial_number = 'FINGERPRINT_ICLOCK_API') ASC")
            ->get()
            ->keyBy(function ($record) {
                $dateStr = $record->att_date instanceof Carbon
                    ? $record->att_date->format('Y-m-d')
                    : (string) $record->att_date;

                return ($record->employee_id ?? 'no_emp') . '_' . $dateStr;
            });

        $rows = [];
        $currentDate = $startDate->copy()->startOfDay();

        while ($currentDate->lte($endDate)) {
            if ($currentDate->isFuture()) {
                break;
            }

            $dateStr = $currentDate->format('Y-m-d');
            $weekday = $currentDate->dayOfWeek;

            foreach ($allEmployees as $employee) {
                if (! $this->isOnOrAfterHireDate($employee, $currentDate)) {
                    continue;
                }

                $workdays = $shiftWorkdayMaps[$employee->id] ?? [];
                $isWorkday = in_array($weekday, $workdays, true);
                $key = $employee->id . '_' . $dateStr;
                $existingRecord = $existingAttendance->get($key);

                if ($existingRecord) {
                    $rows[] = $this->rowFromZkRecord($existingRecord, $employee, $dateStr);
                } elseif ($isWorkday) {
                    $rows[] = $this->rowVirtual($employee, $dateStr);
                }
            }

            $currentDate->addDay();
        }

        $this->applyAbsencePenaltyLogic($rows, $employeesKeyed, $shiftWorkdayMaps);

        foreach ($rows as $i => $row) {
            $rows[$i] = $this->withStatusAndLateMinutes($row, $employeesKeyed, $shiftWorkdayMaps);
            $this->reconcileLatePenalty($rows[$i]);
        }

        $this->persistRows($companyId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $rows);
    }

    public function rebuildEmployeeDateRange(int $employeeId, string $startDateYmd, string $endDateYmd): void
    {
        $startDate = Carbon::parse($startDateYmd, self::TZ)->startOfDay();
        $endDate = Carbon::parse($endDateYmd, self::TZ)->endOfDay();
        $today = Carbon::today(self::TZ);

        if ($endDate->gt($today)) {
            $endDate = $today->copy()->endOfDay();
        }

        if ($startDate->gt($endDate)) {
            return;
        }

        $employee = Employee::with(['shift.workdays'])->find($employeeId);
        if (! $employee) {
            return;
        }

        $workdays = [];
        if ($employee->shift) {
            $workdays = $employee->shift->workdays
                ->where('is_workday', true)
                ->pluck('weekday')
                ->toArray();
        }

        $employeesKeyed = collect([$employee->id => $employee]);
        $shiftWorkdayMaps = [$employee->id => $workdays];

        $existingAttendance = ZkDailyAttendance::query()
            ->select([
                'zk_daily_attendance.*',
                'employees.id as employee_id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_id as emp_code',
                'employees.company_id',
                'zk_devices.name as device_name',
                'zk_devices.serial_number',
            ])
            ->leftJoin('employees', function ($join) {
                $join->on('employees.fingerprint_device_id', '=', 'zk_daily_attendance.device_pin');
            })
            ->leftJoin('zk_devices', 'zk_devices.id', '=', 'zk_daily_attendance.device_id')
            ->whereBetween('zk_daily_attendance.att_date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            ])
            ->where('employees.company_id', $employee->company_id)
            ->where('employees.id', $employee->id)
            ->orderByRaw("(zk_devices.serial_number = 'FINGERPRINT_ICLOCK_API') ASC")
            ->get()
            ->keyBy(function ($record) {
                $dateStr = $record->att_date instanceof Carbon
                    ? $record->att_date->format('Y-m-d')
                    : (string) $record->att_date;

                return ($record->employee_id ?? 'no_emp') . '_' . $dateStr;
            });

        $rows = [];
        $currentDate = $startDate->copy()->startOfDay();

        while ($currentDate->lte($endDate)) {
            if ($currentDate->isFuture()) {
                break;
            }

            $dateStr = $currentDate->format('Y-m-d');
            $weekday = $currentDate->dayOfWeek;

            if ($this->isOnOrAfterHireDate($employee, $currentDate)) {
                $isWorkday = in_array($weekday, $workdays, true);
                $key = $employee->id . '_' . $dateStr;
                $existingRecord = $existingAttendance->get($key);

                if ($existingRecord) {
                    $rows[] = $this->rowFromZkRecord($existingRecord, $employee, $dateStr);
                } elseif ($isWorkday) {
                    $rows[] = $this->rowVirtual($employee, $dateStr);
                }
            }

            $currentDate->addDay();
        }

        $this->applyAbsencePenaltyLogic($rows, $employeesKeyed, $shiftWorkdayMaps);

        foreach ($rows as $i => $row) {
            $rows[$i] = $this->withStatusAndLateMinutes($row, $employeesKeyed, $shiftWorkdayMaps);
            $this->reconcileLatePenalty($rows[$i]);
        }

        $this->persistRowsForEmployee(
            (int) $employee->company_id,
            (int) $employee->id,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $rows
        );
    }

    /**
     * @param  object  $r  joined zk_daily_attendance row
     * @return array<string, mixed>
     */
    private function rowFromZkRecord(object $r, Employee $employee, string $dateStr): array
    {
        $punchCount = (int) ($r->punch_count ?? 0);
        $lastPunch = $punchCount > 1 ? $r->last_punch : null;
        $lastVerifyMode = $punchCount > 1 ? $r->last_verify_mode : null;

        return [
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'att_date' => $dateStr,
            'zk_daily_attendance_id' => $r->id,
            'first_punch' => $r->first_punch,
            'last_punch' => $lastPunch,
            'punch_count' => $punchCount,
            'first_verify_mode' => $r->first_verify_mode,
            'last_verify_mode' => $lastVerifyMode,
            'device_pin' => $r->device_pin ?? $employee->fingerprint_device_id,
            'device_name' => $r->device_name ?? null,
            'serial_number' => $r->serial_number ?? null,
            'is_virtual_absence' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowVirtual(Employee $employee, string $dateStr): array
    {
        return [
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'att_date' => $dateStr,
            'zk_daily_attendance_id' => null,
            'first_punch' => null,
            'last_punch' => null,
            'punch_count' => 0,
            'first_verify_mode' => null,
            'last_verify_mode' => null,
            'device_pin' => $employee->fingerprint_device_id,
            'device_name' => null,
            'serial_number' => null,
            'is_virtual_absence' => true,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<int>>  $shiftWorkdayMaps
     */
    private function applyAbsencePenaltyLogic(array $rows, $employeesKeyed, array $shiftWorkdayMaps): void
    {
        $absentRows = collect($rows)
            ->filter(function (array $row) use ($employeesKeyed, $shiftWorkdayMaps) {
                $employee = $employeesKeyed->get($row['employee_id']);
                if (! $employee || ! $employee->shift || ! $employee->fingerprint_device_id) {
                    return false;
                }

                $attDate = Carbon::parse($row['att_date'], self::TZ);
                if ($attDate->isFuture() || ! $this->isOnOrAfterHireDate($employee, $attDate)) {
                    return false;
                }

                $weekday = $attDate->dayOfWeek;
                $workdays = $shiftWorkdayMaps[$employee->id] ?? [];

                if (! in_array($weekday, $workdays, true)) {
                    return false;
                }

                return $row['first_punch'] === null || $row['is_virtual_absence'];
            })
            ->sortBy('att_date')
            ->values();

        foreach ($absentRows->groupBy('employee_id') as $employeeId => $group) {
            $repeatNumber = 0;
            foreach ($group as $row) {
                $repeatNumber++;
                $this->penaltyService->calculateAbsenceWithoutExcusePenaltyWithRepeatNumber(
                    (int) $employeeId,
                    $row['att_date'],
                    min($repeatNumber, 4)
                );
            }
        }

        foreach ($rows as $row) {
            if ($row['first_punch'] !== null && ! $row['is_virtual_absence']) {
                AttendancePenalty::where('employee_id', $row['employee_id'])
                    ->where('attendance_date', $row['att_date'])
                    ->where('violation_type', 'absent_without_excuse')
                    ->delete();
            }
        }
    }

    /**
     * @param  array<int, array<int>>  $shiftWorkdayMaps
     * @return array<string, mixed>
     */
    private function withStatusAndLateMinutes(array $row, $employeesKeyed, array $shiftWorkdayMaps): array
    {
        $employee = $employeesKeyed->get($row['employee_id']);
        if (! $employee || ! $employee->shift) {
            $row['status_ar'] = 'غير محدد';
            $row['late_minutes'] = null;

            return $row;
        }

        if (! $employee->fingerprint_device_id) {
            $row['status_ar'] = 'غير مربوط ببصمة';
            $row['late_minutes'] = null;

            return $row;
        }

        $attDate = Carbon::parse($row['att_date'], self::TZ);
        $weekday = $attDate->dayOfWeek;
        $workdays = $shiftWorkdayMaps[$employee->id] ?? [];

        if (! in_array($weekday, $workdays, true)) {
            $row['status_ar'] = 'إجازة';
            $row['late_minutes'] = 0;

            return $row;
        }

        if ($row['first_punch'] === null || $row['is_virtual_absence']) {
            $row['status_ar'] = 'غائب';
            $row['late_minutes'] = null;

            return $row;
        }

        $dateStr = $row['att_date'];
        $expectedStartTime = $employee->shift->effectiveStartTimeStringForWeekday($weekday);
        $expectedStart = Carbon::parse($dateStr . ' ' . $expectedStartTime, self::TZ);
        $firstPunch = Carbon::parse($row['first_punch'])->setTimezone(self::TZ);
        $actualLateMinutes = (int) round(($firstPunch->timestamp - $expectedStart->timestamp) / 60);
        $grace = (int) ($employee->shift->grace_minutes ?? 0);
        $lateMinutes = max(0, $actualLateMinutes - $grace);
        $row['status_ar'] = $lateMinutes > 0 ? 'متأخر' : 'في الموعد';
        $row['late_minutes'] = $lateMinutes;

        return $row;
    }

    /**
     * Keep late penalties consistent with the computed presentation row.
     * - If late_minutes > 0, create/update late penalty.
     * - If late_minutes is null/0, remove any stale late_* penalty for that day.
     *
     * @param  array<string, mixed>  $row
     */
    private function reconcileLatePenalty(array $row): void
    {
        $lateViolationTypes = ['late_0_15', 'late_15_30', 'late_30_60', 'late_over_60'];

        $employeeId = (int) ($row['employee_id'] ?? 0);
        $attDate = (string) ($row['att_date'] ?? '');
        $lateMinutes = (int) ($row['late_minutes'] ?? 0);

        if ($employeeId <= 0 || $attDate === '') {
            return;
        }

        if ($lateMinutes > 0) {
            $this->penaltyService->calculatePenalty($employeeId, $attDate, $lateMinutes);

            return;
        }

        AttendancePenalty::where('employee_id', $employeeId)
            ->where('attendance_date', $attDate)
            ->whereIn('violation_type', $lateViolationTypes)
            ->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistRows(int $companyId, string $startStr, string $endStr, array $rows): void
    {
        DB::transaction(function () use ($companyId, $startStr, $endStr, $rows) {
            AttendanceDailyPresentation::where('company_id', $companyId)
                ->whereBetween('att_date', [$startStr, $endStr])
                ->delete();

            $this->upsertPresentationRows($this->buildInsertRows($companyId, $rows));
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistRowsForEmployee(int $companyId, int $employeeId, string $startStr, string $endStr, array $rows): void
    {
        DB::transaction(function () use ($companyId, $employeeId, $startStr, $endStr, $rows) {
            AttendanceDailyPresentation::where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->whereBetween('att_date', [$startStr, $endStr])
                ->delete();

            $this->upsertPresentationRows($this->buildInsertRows($companyId, $rows));
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildInsertRows(int $companyId, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $now = now();
        $insert = [];

        foreach ($rows as $row) {
            $insert[] = [
                'company_id' => $companyId,
                'employee_id' => $row['employee_id'],
                'att_date' => $row['att_date'],
                'status_ar' => $row['status_ar'],
                'late_minutes' => $row['late_minutes'],
                'is_virtual_absence' => $row['is_virtual_absence'] ? 1 : 0,
                'zk_daily_attendance_id' => $row['zk_daily_attendance_id'],
                'first_punch' => $this->formatDateTimeForDb($row['first_punch']),
                'last_punch' => $this->formatDateTimeForDb($row['last_punch']),
                'punch_count' => $row['punch_count'],
                'first_verify_mode' => $row['first_verify_mode'],
                'last_verify_mode' => $row['last_verify_mode'],
                'device_pin' => $row['device_pin'],
                'device_name' => $row['device_name'],
                'serial_number' => $row['serial_number'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $this->deduplicateInsertRows($insert);
    }

    /**
     * @param  array<int, array<string, mixed>>  $insert
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateInsertRows(array $insert): array
    {
        $deduped = [];

        foreach ($insert as $row) {
            $deduped[$row['employee_id'] . '-' . $row['att_date']] = $row;
        }

        return array_values($deduped);
    }

    /**
     * @param  array<int, array<string, mixed>>  $insert
     */
    private function upsertPresentationRows(array $insert): void
    {
        if ($insert === []) {
            return;
        }

        $updateColumns = [
            'company_id',
            'status_ar',
            'late_minutes',
            'is_virtual_absence',
            'zk_daily_attendance_id',
            'first_punch',
            'last_punch',
            'punch_count',
            'first_verify_mode',
            'last_verify_mode',
            'device_pin',
            'device_name',
            'serial_number',
            'updated_at',
        ];

        foreach (array_chunk($insert, 400) as $chunk) {
            DB::table('attendance_daily_presentations')->upsert(
                $chunk,
                ['employee_id', 'att_date'],
                $updateColumns,
            );
        }
    }

    private function formatDateTimeForDb(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function isOnOrAfterHireDate(Employee $employee, Carbon $date): bool
    {
        if ($employee->hire_date === null) {
            return true;
        }

        $hireDate = $employee->hire_date instanceof Carbon
            ? $employee->hire_date->copy()->startOfDay()
            : Carbon::parse($employee->hire_date, self::TZ)->startOfDay();

        return $date->copy()->startOfDay()->gte($hireDate);
    }
}
