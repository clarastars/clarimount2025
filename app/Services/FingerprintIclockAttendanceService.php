<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\ZkDevice;
use App\Models\ZkDailyAttendance;
use App\Services\AttendancePenaltyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Syncs daily attendance (first punch = check-in, last punch = check-out) from the
 * fingerprint device iClock API: /iclock/api/transactions/
 * Uses emp_code and date range; stores into zk_daily_attendance for today only.
 */
class FingerprintIclockAttendanceService
{
    private const API_DEVICE_SERIAL = 'FINGERPRINT_ICLOCK_API';

    public function __construct(
        private ?string $baseUrl = null,
        private ?string $token = null,
        private int $timeout = 15
    ) {
        $this->baseUrl = $this->baseUrl ?? rtrim(config('services.fingerprint_device.base_url', ''), '/');
        $this->token = $this->token ?? config('services.fingerprint_device.token');
        $this->timeout = (int) ($this->timeout ?: config('services.fingerprint_device.timeout', 15));
    }

    /**
     * Sync attendance for today from iClock API for all employees with fingerprint_device_id.
     */
    public function syncToday(): void
    {
        $today = Carbon::today('Asia/Riyadh');
        $this->syncForDate($today);
    }

    /**
     * Sync attendance for the current month from the 1st day until today (inclusive).
     * This is useful when starting the system mid‑month and needing to backfill data.
     */
    public function syncCurrentMonthUntilToday(): void
    {
        $now = Carbon::now('Asia/Riyadh');
        $startOfMonth = $now->copy()->startOfMonth();
        $today = $now->copy()->startOfDay();

        $current = $startOfMonth->copy();
        while ($current->lte($today)) {
            $this->syncForDate($current);
            $current->addDay();
        }
    }

    /**
     * Sync attendance for a specific date from iClock API for all employees with fingerprint_device_id.
     */
    public function syncForDate(Carbon $date): void
    {
        if (empty($this->baseUrl) || empty($this->token)) {
            Log::channel('daily')->info('[FingerprintIclock] Sync skipped: base_url or token not configured');
            return;
        }

        $device = $this->getOrCreateApiDevice();
        $startTime = $date->copy()->startOfDay()->format('Y-m-d H:i:s');
        $endTime = $date->copy()->endOfDay()->format('Y-m-d H:i:s');
        $attDate = $date->format('Y-m-d');

        $employees = Employee::whereNotNull('fingerprint_device_id')
            ->where('fingerprint_device_id', '!=', '')
            ->get();

        foreach ($employees as $employee) {
            try {
                $this->syncEmployeeForDate($device, $employee->fingerprint_device_id, $attDate, $startTime, $endTime);
            } catch (\Throwable $e) {
                Log::channel('daily')->warning('[FingerprintIclock] Error syncing employee', [
                    'fingerprint_device_id' => $employee->fingerprint_device_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Sync attendance for a specific employee (by employee id) for the current month until today (inclusive).
     */
    public function syncCurrentMonthUntilTodayForEmployeeId(int $employeeId): void
    {
        $now = Carbon::now('Asia/Riyadh');
        $startOfMonth = $now->copy()->startOfMonth();
        $today = $now->copy()->startOfDay();

        $current = $startOfMonth->copy();
        while ($current->lte($today)) {
            $this->syncForEmployeeIdAndDate($employeeId, $current);
            $current->addDay();
        }
    }

    /**
     * Sync attendance for a specific employee (by employee id) for a specific date.
     */
    public function syncForEmployeeIdAndDate(int $employeeId, Carbon $date): void
    {
        if (empty($this->baseUrl) || empty($this->token)) {
            Log::channel('daily')->info('[FingerprintIclock] Sync skipped: base_url or token not configured');
            return;
        }

        $employee = Employee::query()->find($employeeId);
        if (! $employee) {
            Log::channel('daily')->warning('[FingerprintIclock] Employee not found', [
                'employee_id' => $employeeId,
            ]);
            return;
        }

        $empCode = (string) ($employee->fingerprint_device_id ?? '');
        if ($empCode === '') {
            Log::channel('daily')->info('[FingerprintIclock] Sync skipped: employee has no fingerprint_device_id', [
                'employee_id' => $employeeId,
            ]);
            return;
        }

        $device = $this->getOrCreateApiDevice();
        $startTime = $date->copy()->startOfDay()->format('Y-m-d H:i:s');
        $endTime = $date->copy()->endOfDay()->format('Y-m-d H:i:s');
        $attDate = $date->format('Y-m-d');

        $this->syncEmployeeForDate($device, $empCode, $attDate, $startTime, $endTime);
    }

    /**
     * Fetch transactions from API for one employee and one day, then upsert zk_daily_attendance.
     */
    public function syncEmployeeForDate(
        ZkDevice $device,
        string $empCode,
        string $attDate,
        string $startTime,
        string $endTime
    ): void {
        $punchTimes = $this->fetchTransactions($empCode, $startTime, $endTime);
        if ($punchTimes === []) {
            return;
        }

        sort($punchTimes);
        $firstPunch = Carbon::parse($punchTimes[0], 'Asia/Riyadh')->utc();
        $lastPunch = Carbon::parse($punchTimes[count($punchTimes) - 1], 'Asia/Riyadh')->utc();

        $attendance = ZkDailyAttendance::updateOrCreate(
            [
                'device_id' => $device->id,
                'device_pin' => $empCode,
                'att_date' => $attDate,
            ],
            [
                'first_punch' => $firstPunch,
                'last_punch' => $lastPunch,
                'first_verify_mode' => null,
                'last_verify_mode' => null,
                'punch_count' => count($punchTimes),
            ]
        );

        // Remove absence penalty if employee has punched (same as ZkAttlogIngestService)
        $employee = Employee::where('fingerprint_device_id', $empCode)->first();
        if ($employee) {
            AttendancePenalty::where('employee_id', $employee->id)
                ->where('attendance_date', $attDate)
                ->where('violation_type', 'absent_without_excuse')
                ->delete();
        }

        // Calculate late penalty so "الإجراء المتخذ / سبب الإجراء / اعتماد الجزاء" appear in UI
        $penaltyService = new AttendancePenaltyService();
        $penaltyService->calculatePenaltyForDailyAttendance($attendance, $attDate);
    }

    /**
     * Call iClock API and return list of punch_time strings (Y-m-d H:i:s) for the given emp_code and range.
     *
     * @return array<int, string>
     */
    public function fetchTransactions(string $empCode, string $startTime, string $endTime): array
    {
        $url = $this->baseUrl . '/iclock/api/transactions/';
        $params = [
            'emp_code' => $empCode,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];

        $allPunchTimes = [];
        $currentUrl = $url . '?' . http_build_query($params);

        do {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Token ' . $this->token,
                ])
                ->get($currentUrl);

            if (! $response->successful()) {
                Log::channel('daily')->warning('[FingerprintIclock] API error', [
                    'url' => $currentUrl,
                    'status' => $response->status(),
                ]);
                return $allPunchTimes;
            }

            $body = $response->json();
            $data = $body['data'] ?? [];
            if (! is_array($data)) {
                return $allPunchTimes;
            }

            foreach ($data as $item) {
                $punchTime = $item['punch_time'] ?? null;
                if (is_string($punchTime) && $punchTime !== '') {
                    $allPunchTimes[] = $punchTime;
                }
            }

            $next = $body['next'] ?? null;
            $currentUrl = is_string($next) && $next !== '' ? $next : null;
        } while ($currentUrl);

        return $allPunchTimes;
    }

    private function getOrCreateApiDevice(): ZkDevice
    {
        return ZkDevice::firstOrCreate(
            ['serial_number' => self::API_DEVICE_SERIAL],
            ['name' => 'Fingerprint iClock API']
        );
    }
}
