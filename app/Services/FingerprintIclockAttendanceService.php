<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\ZkDailyAttendance;
use App\Models\ZkDevice;
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

    private bool $progressEnabled = false;

    public function setProgressEnabled(bool $enabled): void
    {
        $this->progressEnabled = $enabled;
    }

    public function __construct(
        private OperationalMonthService $operationalMonthService,
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
    public function syncToday(?int $companyId = null): void
    {
        $today = Carbon::today('Asia/Riyadh');
        $this->syncForDate($today, $companyId);
    }

    /**
     * Sync attendance for the current month from the 1st day until today (inclusive).
     * This is useful when starting the system mid‑month and needing to backfill data.
     */
    public function syncCurrentMonthUntilToday(?int $companyId = null): void
    {
        $now = Carbon::now('Asia/Riyadh');
        $operationalRange = $this->operationalMonthService->resolveCurrentOperationalMonthRange($now);
        $startOfMonth = $operationalRange['start']->copy()->startOfDay();
        $periodEnd = $operationalRange['end']->copy()->startOfDay();
        $today = $now->copy()->startOfDay()->min($periodEnd);

        $current = $startOfMonth->copy();
        while ($current->lte($today)) {
            if ($this->progressEnabled) {
                Log::channel('stderr')->info('[FingerprintIclock] Sync day: '.$current->format('Y-m-d'));
            }
            $this->syncForDate($current, $companyId);
            $current->addDay();
        }
    }

    /**
     * Sync attendance for a specific date from iClock API for all employees with fingerprint_device_id.
     */
    public function syncForDate(Carbon $date, ?int $companyId = null): void
    {
        if (empty($this->baseUrl) || empty($this->token)) {
            Log::channel('daily')->info('[FingerprintIclock] Sync skipped: base_url or token not configured', [
                'att_date' => $date->format('Y-m-d'),
                'base_url_configured' => ! empty($this->baseUrl),
                'token_configured' => ! empty($this->token),
            ]);
            if ($this->progressEnabled) {
                Log::channel('stderr')->error('[FingerprintIclock] Sync skipped: base_url or token not configured');
            }

            return;
        }

        $startedAt = microtime(true);
        $device = $this->getOrCreateApiDevice();
        $startTime = $date->copy()->startOfDay()->format('Y-m-d H:i:s');
        $endTime = $date->copy()->endOfDay()->format('Y-m-d H:i:s');
        $attDate = $date->format('Y-m-d');

        $employees = Employee::whereNotNull('fingerprint_device_id')
            ->where('fingerprint_device_id', '!=', '')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->get();

        $stats = [
            'employees_total' => $employees->count(),
            'attendance_rows_saved' => 0,
            'no_transactions' => 0,
            'exceptions' => 0,
        ];

        Log::channel('daily')->info('[FingerprintIclock] Sync started', [
            'att_date' => $attDate,
            'range' => ['start_time' => $startTime, 'end_time' => $endTime],
            'company_id' => $companyId,
            'employees_total' => $stats['employees_total'],
            'device_id' => $device->id,
            'api_base_host' => (string) parse_url((string) $this->baseUrl, PHP_URL_HOST),
        ]);

        foreach ($employees as $employee) {
            try {
                $result = $this->syncEmployeeForDate($device, $employee->fingerprint_device_id, $attDate, $startTime, $endTime);
                if ($result['stored']) {
                    $stats['attendance_rows_saved']++;
                } else {
                    $stats['no_transactions']++;
                }
                if ($this->progressEnabled) {
                    $pin = $employee->fingerprint_device_id;
                    $msg = '[FingerprintIclock] Employee pin '.$pin.' on '.$attDate;
                    if ($result['stored']) {
                        $msg .= $result['late_minutes'] !== null
                            ? ' lateMinutes='.$result['late_minutes']
                            : ' stored, no late penalty';
                    } else {
                        $msg .= ' no transactions / no row saved';
                    }
                    Log::channel('stderr')->info($msg);
                }
            } catch (\Throwable $e) {
                $stats['exceptions']++;
                Log::channel('daily')->warning('[FingerprintIclock] Error syncing employee', [
                    'fingerprint_device_id' => $employee->fingerprint_device_id,
                    'att_date' => $attDate,
                    'error' => $e->getMessage(),
                    'exception_class' => $e::class,
                ]);
                if ($this->progressEnabled) {
                    Log::channel('stderr')->error(
                        '[FingerprintIclock] Error syncing employee pin '.$employee->fingerprint_device_id.' on '.$attDate.
                        ': '.$e->getMessage()
                    );
                }
            }
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Log::channel('daily')->info('[FingerprintIclock] Sync summary', [
            'att_date' => $attDate,
            'duration_ms' => $durationMs,
            ...$stats,
        ]);
    }

    /**
     * Sync attendance for a specific employee (by employee id) for the current month until today (inclusive).
     */
    public function syncCurrentMonthUntilTodayForEmployeeId(int $employeeId): void
    {
        $now = Carbon::now('Asia/Riyadh');
        $operationalRange = $this->operationalMonthService->resolveCurrentOperationalMonthRange($now);
        $startOfMonth = $operationalRange['start']->copy()->startOfDay();
        $periodEnd = $operationalRange['end']->copy()->startOfDay();
        $today = $now->copy()->startOfDay()->min($periodEnd);

        $current = $startOfMonth->copy();
        while ($current->lte($today)) {
            if ($this->progressEnabled) {
                Log::channel('stderr')->info('[FingerprintIclock] Sync day for employeeId='.$employeeId.' : '.$current->format('Y-m-d'));
            }
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
            if ($this->progressEnabled) {
                Log::channel('stderr')->error('[FingerprintIclock] Sync skipped (employeeId='.$employeeId.'): base_url or token not configured');
            }

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
     *
     * @return array{stored: bool, late_minutes: ?int}
     */
    public function syncEmployeeForDate(
        ZkDevice $device,
        string $empCode,
        string $attDate,
        string $startTime,
        string $endTime
    ): array {
        $punchTimes = $this->fetchTransactions($empCode, $startTime, $endTime);
        if ($punchTimes === []) {
            return ['stored' => false, 'late_minutes' => null];
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
        $lateMinutes = app(AttendancePenaltyService::class)->calculatePenaltyForDailyAttendance($attendance, $attDate);

        return ['stored' => true, 'late_minutes' => $lateMinutes];
    }

    /**
     * Call iClock API and return list of punch_time strings (Y-m-d H:i:s) for the given emp_code and range.
     *
     * @return array<int, string>
     */
    public function fetchTransactions(string $empCode, string $startTime, string $endTime): array
    {
        $url = $this->baseUrl.'/iclock/api/transactions/';
        $params = [
            'emp_code' => $empCode,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];

        $allPunchTimes = [];
        $currentUrl = $url.'?'.http_build_query($params);

        do {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Token '.$this->token,
                ])
                ->get($currentUrl);

            if (! $response->successful()) {
                $bodySample = mb_substr($response->body(), 0, 800);
                Log::channel('daily')->warning('[FingerprintIclock] API error', [
                    'emp_code' => $empCode,
                    'url' => $currentUrl,
                    'status' => $response->status(),
                    'body_preview' => $bodySample,
                ]);

                return $allPunchTimes;
            }

            $body = $response->json();
            $data = $body['data'] ?? [];
            if (! is_array($data)) {
                Log::channel('daily')->warning('[FingerprintIclock] API response missing or invalid data array', [
                    'emp_code' => $empCode,
                    'att_date_range' => ['start_time' => $startTime, 'end_time' => $endTime],
                    'body_keys' => is_array($body) ? array_keys($body) : null,
                ]);

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
