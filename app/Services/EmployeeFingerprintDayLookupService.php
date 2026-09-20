<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceDailyPresentation;
use App\Models\Employee;
use App\Models\ZkAttendanceRaw;
use App\Models\ZkDailyAttendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeeFingerprintDayLookupService
{
    private const TZ = 'Asia/Riyadh';

    public function __construct(
        private readonly FingerprintIclockAttendanceService $iclockAttendanceService,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     full_name: string,
     *     employee_id: string|null,
     *     company_name: string|null,
     *     fingerprint_device_id: string|null,
     *     employment_status: string|null
     * }>
     */
    public function searchEmployees(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $like = '%'.$query.'%';

        return Employee::query()
            ->with('company')
            ->where(function ($q) use ($like, $query): void {
                $q->where('first_name', 'like', $like)
                    ->orWhere('father_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('employee_id', 'like', $like)
                    ->orWhere('id_number', 'like', $like)
                    ->orWhere('fingerprint_device_id', 'like', $like)
                    ->orWhere('work_email', 'like', $like)
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
            ->map(function (Employee $employee): array {
                $fullName = trim(implode(' ', array_filter([
                    $employee->first_name,
                    $employee->father_name,
                    $employee->last_name,
                ])));

                return [
                    'id' => (int) $employee->id,
                    'full_name' => $fullName !== '' ? $fullName : (string) $employee->id,
                    'employee_id' => $employee->employee_id !== null ? (string) $employee->employee_id : null,
                    'company_name' => $employee->company?->name_ar ?: $employee->company?->name_en,
                    'fingerprint_device_id' => $employee->fingerprint_device_id !== null && $employee->fingerprint_device_id !== ''
                        ? (string) $employee->fingerprint_device_id
                        : null,
                    'employment_status' => $employee->employment_status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     employee: array{
     *         id: int,
     *         full_name: string,
     *         employee_id: string|null,
     *         company_name: string|null,
     *         fingerprint_device_id: string|null
     *     },
     *     date: string,
     *     punches: list<array{
     *         id: int|null,
     *         punch_time: string,
     *         punch_time_riyadh: string,
     *         verify_mode: int|null,
     *         device_id: int|null,
     *         device_name: string|null,
     *         serial_number: string|null,
     *         source: string
     *     }>,
     *     daily_summary: array{
     *         first_punch: string|null,
     *         last_punch: string|null,
     *         punch_count: int,
     *         device_name: string|null
     *     }|null,
     *     presentation: array{
     *         status_ar: string|null,
     *         late_minutes: int|null,
     *         punch_count: int|null,
     *         first_punch: string|null,
     *         last_punch: string|null
     *     }|null
     * }
     */
    public function lookup(Employee $employee, Carbon|string $date): array
    {
        $day = $this->parseDate($date);
        $pin = trim((string) ($employee->fingerprint_device_id ?? ''));

        $punches = $pin === ''
            ? []
            : $this->rawPunchesForPin($pin, $day);

        return [
            'employee' => [
                'id' => (int) $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id !== null ? (string) $employee->employee_id : null,
                'company_name' => $employee->company?->name_ar ?: $employee->company?->name_en,
                'fingerprint_device_id' => $pin !== '' ? $pin : null,
            ],
            'date' => $day->toDateString(),
            'punches' => $punches,
            'daily_summary' => $pin !== '' ? $this->dailySummaryForPin($pin, $day) : null,
            'presentation' => $this->presentationForEmployee($employee, $day),
        ];
    }

    /**
     * Fetch live punches from iClock API for the employee/day (does not require raw DB rows).
     *
     * @return list<array{
     *     id: null,
     *     punch_time: string,
     *     punch_time_riyadh: string,
     *     verify_mode: null,
     *     device_id: null,
     *     device_name: null,
     *     serial_number: null,
     *     source: string
     * }>
     */
    public function fetchFromApi(Employee $employee, Carbon|string $date): array
    {
        $day = $this->parseDate($date);
        $pin = trim((string) ($employee->fingerprint_device_id ?? ''));

        if ($pin === '') {
            return [];
        }

        $start = $day->copy()->startOfDay()->format('Y-m-d H:i:s');
        $end = $day->copy()->endOfDay()->format('Y-m-d H:i:s');
        $times = $this->iclockAttendanceService->fetchTransactions($pin, $start, $end);
        sort($times);

        return array_values(array_map(static function (string $punchTime): array {
            $riyadh = Carbon::parse($punchTime, self::TZ);

            return [
                'id' => null,
                'punch_time' => $riyadh->copy()->utc()->toDateTimeString(),
                'punch_time_riyadh' => $riyadh->format('Y-m-d H:i:s'),
                'verify_mode' => null,
                'device_id' => null,
                'device_name' => null,
                'serial_number' => null,
                'source' => 'iclock_api',
            ];
        }, $times));
    }

    /**
     * @return list<array{
     *     id: int,
     *     punch_time: string,
     *     punch_time_riyadh: string,
     *     verify_mode: int|null,
     *     device_id: int|null,
     *     device_name: string|null,
     *     serial_number: string|null,
     *     source: string
     * }>
     */
    private function rawPunchesForPin(string $pin, Carbon $day): array
    {
        $startUtc = $day->copy()->startOfDay()->utc();
        $endUtc = $day->copy()->endOfDay()->utc();

        /** @var Collection<int, ZkAttendanceRaw> $rows */
        $rows = ZkAttendanceRaw::query()
            ->with('device')
            ->where('device_pin', $pin)
            ->whereBetween('punch_time', [$startUtc->toDateTimeString(), $endUtc->toDateTimeString()])
            ->orderBy('punch_time')
            ->get();

        return $rows->map(function (ZkAttendanceRaw $row): array {
            $utc = Carbon::parse($row->punch_time)->utc();
            $riyadh = $utc->copy()->timezone(self::TZ);

            return [
                'id' => (int) $row->id,
                'punch_time' => $utc->toDateTimeString(),
                'punch_time_riyadh' => $riyadh->format('Y-m-d H:i:s'),
                'verify_mode' => $row->verify_mode !== null ? (int) $row->verify_mode : null,
                'device_id' => $row->device_id !== null ? (int) $row->device_id : null,
                'device_name' => $row->device?->name,
                'serial_number' => $row->device?->serial_number,
                'source' => 'zk_attendance_raw',
            ];
        })->values()->all();
    }

    /**
     * @return array{
     *     first_punch: string|null,
     *     last_punch: string|null,
     *     punch_count: int,
     *     device_name: string|null
     * }|null
     */
    private function dailySummaryForPin(string $pin, Carbon $day): ?array
    {
        $daily = ZkDailyAttendance::query()
            ->with('device')
            ->where('device_pin', $pin)
            ->whereDate('att_date', $day->toDateString())
            ->orderByDesc('id')
            ->first();

        if ($daily === null) {
            return null;
        }

        return [
            'first_punch' => $daily->first_punch
                ? Carbon::parse($daily->first_punch)->timezone(self::TZ)->format('Y-m-d H:i:s')
                : null,
            'last_punch' => $daily->last_punch
                ? Carbon::parse($daily->last_punch)->timezone(self::TZ)->format('Y-m-d H:i:s')
                : null,
            'punch_count' => (int) ($daily->punch_count ?? 0),
            'device_name' => $daily->device?->name ?: $daily->device?->serial_number,
        ];
    }

    /**
     * @return array{
     *     status_ar: string|null,
     *     late_minutes: int|null,
     *     punch_count: int|null,
     *     first_punch: string|null,
     *     last_punch: string|null
     * }|null
     */
    private function presentationForEmployee(Employee $employee, Carbon $day): ?array
    {
        $presentation = AttendanceDailyPresentation::query()
            ->where('employee_id', $employee->id)
            ->whereDate('att_date', $day->toDateString())
            ->first();

        if ($presentation === null) {
            return null;
        }

        return [
            'status_ar' => $presentation->status_ar,
            'late_minutes' => $presentation->late_minutes !== null ? (int) $presentation->late_minutes : null,
            'punch_count' => $presentation->punch_count !== null ? (int) $presentation->punch_count : null,
            'first_punch' => $presentation->first_punch
                ? Carbon::parse($presentation->first_punch)->timezone(self::TZ)->format('Y-m-d H:i:s')
                : null,
            'last_punch' => $presentation->last_punch
                ? Carbon::parse($presentation->last_punch)->timezone(self::TZ)->format('Y-m-d H:i:s')
                : null,
        ];
    }

    private function parseDate(Carbon|string $date): Carbon
    {
        if ($date instanceof Carbon) {
            return Carbon::createFromFormat('Y-m-d', $date->format('Y-m-d'), self::TZ)->startOfDay();
        }

        return Carbon::createFromFormat('Y-m-d', trim($date), self::TZ)->startOfDay();
    }
}
