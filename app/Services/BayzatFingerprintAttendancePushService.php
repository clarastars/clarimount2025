<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\ZkDailyAttendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushes first and last punch times per employee per day to Bayzat (Asia/Riyadh), without a type field.
 * If first and last punch are the same instant, only one record is sent (Bayzat rejects duplicate empId+time).
 * Uses the same source as the attendance screen: for each employee+day, if multiple devices exist,
 * the iClock API row (FINGERPRINT_ICLOCK_API) wins over physical ZK devices.
 *
 * Separate from {@see BayzatSyncService} (CSV import) — uses x-api-key for integration.bayzat.com.
 */
class BayzatFingerprintAttendancePushService
{
    public const ICLOCK_API_DEVICE_SERIAL = 'FINGERPRINT_ICLOCK_API';

    public function __construct(
        private ?string $apiUrl = null,
        private ?string $apiKey = null,
        private int $timeoutSeconds = 30,
        private int $maxRecordsPerRequest = 50,
        private int $chunkDelaySeconds = 0,
    ) {
        $this->apiUrl = $this->apiUrl ?? rtrim((string) config('services.bayzat.iclock_push_url', ''), '/')
            ?: rtrim((string) config('services.bayzat.default_api_url', 'https://integration.bayzat.com/attendance'), '/');
        $this->apiKey = $this->apiKey ?? config('services.bayzat.api_key');
        $this->timeoutSeconds = (int) config('services.bayzat.iclock_push_timeout', 30);
        $this->maxRecordsPerRequest = max(2, (int) config('services.bayzat.iclock_push_max_records_per_request', 50));
        $this->chunkDelaySeconds = (int) config('services.bayzat.iclock_push_chunk_delay_seconds', 0);
    }

    /**
     * Send today's first and last punch per employee to Bayzat (two records per empId: check-in then check-out times, no type field).
     */
    public function pushToday(): void
    {
        $this->pushForDate(Carbon::today('Asia/Riyadh'));
    }

    public function pushForDate(Carbon $date): void
    {
        $phaseStart = microtime(true);
        if (empty($this->apiKey)) {
            Log::channel('daily')->info('[BayzatFingerprintPush] Skipped: BAYZAT_API_KEY not set', [
                'date' => $date->format('Y-m-d'),
            ]);

            return;
        }

        $attDate = $date->format('Y-m-d');

        $url = $this->apiUrl;
        if (! str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }
        $urlHost = (string) parse_url($url, PHP_URL_HOST);

        /** @var Collection<int, Employee> $employeesWithPin */
        $employeesWithPin = Employee::query()
            ->whereNotNull('fingerprint_device_id')
            ->where('fingerprint_device_id', '!=', '')
            ->get();

        if ($employeesWithPin->isEmpty()) {
            Log::channel('daily')->info('[BayzatFingerprintPush] No employees with fingerprint_device_id for ' . $attDate, [
                'date' => $attDate,
            ]);

            return;
        }

        $pins = $employeesWithPin
            ->map(fn (Employee $e) => $this->normalizeDevicePin($e->fingerprint_device_id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var Collection<string, ZkDailyAttendance> $attendanceByPin */
        $attendanceByPin = $this->loadPreferredDailyAttendanceByPin($this->expandPinsForWhereIn($pins), $attDate);

        $buildStats = [
            'skipped_empty_emp_id' => 0,
            'skipped_duplicate_emp' => 0,
            'skipped_no_attendance_row' => 0,
            'skipped_no_checkin' => 0,
        ];
        $sampleNoRow = [];
        $sampleNoCheckin = [];

        $pairs = [];
        $seenEmpIds = [];
        foreach ($employeesWithPin as $employee) {
            $empId = $this->normalizeDevicePin($employee->fingerprint_device_id);
            if ($empId === '') {
                $buildStats['skipped_empty_emp_id']++;

                continue;
            }

            if (isset($seenEmpIds[$empId])) {
                $buildStats['skipped_duplicate_emp']++;

                continue;
            }

            $row = $this->resolveAttendanceForEmployeePin($attendanceByPin, $empId);
            if ($row === null) {
                $buildStats['skipped_no_attendance_row']++;
                if (count($sampleNoRow) < 5) {
                    $sampleNoRow[] = $empId;
                }

                continue;
            }

            $checkIn = $this->punchToRiyadhFromModel($row, 'first_punch');
            if ($checkIn === null) {
                $buildStats['skipped_no_checkin']++;
                if (count($sampleNoCheckin) < 5) {
                    $sampleNoCheckin[] = $empId;
                }

                continue;
            }

            $checkOut = $this->punchToRiyadhFromModel($row, 'last_punch') ?? $checkIn;

            $pairs[] = [
                'empId' => $empId,
                'checkIn' => $checkIn,
                'checkOut' => $checkOut,
            ];
            $seenEmpIds[$empId] = true;
        }

        Log::channel('daily')->info('[BayzatFingerprintPush] Prepared payload context', [
            'date' => $attDate,
            'target_host' => $urlHost,
            'employees_with_pin' => $employeesWithPin->count(),
            'distinct_pins' => count($pins),
            'zk_rows_loaded_keys' => $attendanceByPin->count(),
            'pairs_to_send' => count($pairs),
            'build_stats' => $buildStats,
            'sample_emp_ids_no_attendance_row' => $sampleNoRow,
            'sample_emp_ids_no_checkin_time' => $sampleNoCheckin,
            'max_records_per_request' => $this->maxRecordsPerRequest,
            'chunk_delay_seconds' => $this->chunkDelaySeconds,
        ]);

        if ($pairs === []) {
            Log::channel('daily')->warning('[BayzatFingerprintPush] No valid pairs to send; aborting HTTP calls', [
                'date' => $attDate,
                'build_stats' => $buildStats,
            ]);

            return;
        }

        $maxEmployeesPerChunk = max(1, intdiv($this->maxRecordsPerRequest, 2));
        $chunks = array_chunk($pairs, $maxEmployeesPerChunk);
        $chunkCount = count($chunks);

        $httpStats = [
            'chunks_total' => $chunkCount,
            'chunks_ok' => 0,
            'chunks_failed' => 0,
        ];

        foreach ($chunks as $index => $pairChunk) {
            if ($index > 0 && $this->chunkDelaySeconds > 0) {
                sleep($this->chunkDelaySeconds);
            }

            $records = [];
            foreach ($pairChunk as $p) {
                $records[] = [
                    'empId' => $p['empId'],
                    'time' => $p['checkIn'],
                ];
                if ($p['checkOut'] !== $p['checkIn']) {
                    $records[] = [
                        'empId' => $p['empId'],
                        'time' => $p['checkOut'],
                    ];
                }
            }

            try {
                $response = Http::timeout($this->timeoutSeconds)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'x-api-key' => $this->apiKey,
                    ])
                    ->post($url, ['records' => $records]);

                $body = $response->json();
                $ok = $response->successful()
                    && is_array($body)
                    && (($body['success'] ?? false) === true);

                if (! $ok) {
                    $httpStats['chunks_failed']++;
                    $bodyPreview = mb_substr($response->body(), 0, 1200);
                    Log::channel('daily')->warning('[BayzatFingerprintPush] Request not successful', [
                        'date' => $attDate,
                        'chunk_index' => $index,
                        'chunks_total' => $chunkCount,
                        'status' => $response->status(),
                        'response_success_flag' => is_array($body) ? ($body['success'] ?? null) : null,
                        'response_message' => is_array($body) ? ($body['message'] ?? null) : null,
                        'body_preview' => $bodyPreview,
                        'employees_in_chunk' => count($pairChunk),
                        'records_in_chunk' => count($records),
                    ]);

                    continue;
                }

                $httpStats['chunks_ok']++;
                Log::channel('daily')->info('[BayzatFingerprintPush] Chunk sent', [
                    'date' => $attDate,
                    'chunk_index' => $index,
                    'chunks_total' => $chunkCount,
                    'employees' => count($pairChunk),
                    'records' => count($records),
                ]);
            } catch (\Throwable $e) {
                $httpStats['chunks_failed']++;
                Log::channel('daily')->error('[BayzatFingerprintPush] Exception', [
                    'date' => $attDate,
                    'chunk_index' => $index,
                    'chunks_total' => $chunkCount,
                    'error' => $e->getMessage(),
                    'exception_class' => $e::class,
                ]);
            }
        }

        $durationMs = (int) round((microtime(true) - $phaseStart) * 1000);
        Log::channel('daily')->info('[BayzatFingerprintPush] Push phase summary', [
            'date' => $attDate,
            'duration_ms' => $durationMs,
            'pairs_total' => count($pairs),
            'http' => $httpStats,
        ]);
    }

    /**
     * Normalize fingerprint / device pin for comparison (Bayzat empId and zk_daily_attendance.device_pin).
     */
    private function normalizeDevicePin(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * Include numeric variants (e.g. "18" and "018") so whereIn matches DB storage.
     *
     * @param  array<int, string>  $pins
     * @return array<int, string>
     */
    private function expandPinsForWhereIn(array $pins): array
    {
        $out = [];
        foreach ($pins as $p) {
            $out[] = $p;
            if ($p !== '' && ctype_digit($p)) {
                $out[] = (string) (int) $p;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Map normalized employee pin to attendance row (handles leading-zero mismatch).
     *
     * @param  Collection<string, ZkDailyAttendance>  $byCanonicalPin
     */
    private function resolveAttendanceForEmployeePin(Collection $byCanonicalPin, string $empId): ?ZkDailyAttendance
    {
        foreach ($this->pinLookupKeys($empId) as $key) {
            $row = $byCanonicalPin->get($key);
            if ($row !== null) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function pinLookupKeys(string $pin): array
    {
        $pin = $this->normalizeDevicePin($pin);
        $keys = [$pin];
        if ($pin !== '' && ctype_digit($pin)) {
            $keys[] = (string) (int) $pin;
        }

        return array_values(array_unique($keys));
    }

    /**
     * One preferred row per logical pin for the date (iClock API over physical devices), same as attendance UI.
     *
     * @param  array<int, string>  $pins
     * @return Collection<string, ZkDailyAttendance>
     */
    private function loadPreferredDailyAttendanceByPin(array $pins, string $attDate): Collection
    {
        if ($pins === []) {
            return collect();
        }

        $uniqueRows = ZkDailyAttendance::query()
            ->join('zk_devices', 'zk_devices.id', '=', 'zk_daily_attendance.device_id')
            ->whereIn('zk_daily_attendance.device_pin', $pins)
            ->whereDate('zk_daily_attendance.att_date', $attDate)
            ->whereNotNull('zk_daily_attendance.first_punch')
            ->orderBy('zk_daily_attendance.device_pin')
            ->orderByRaw('(zk_devices.serial_number = ?) DESC', [self::ICLOCK_API_DEVICE_SERIAL])
            ->select('zk_daily_attendance.*')
            ->get()
            ->unique('device_pin');

        $byKey = collect();
        foreach ($uniqueRows as $row) {
            $canonical = $this->normalizeDevicePin($row->device_pin);
            foreach ($this->pinLookupKeys($canonical) as $key) {
                if (! $byKey->has($key)) {
                    $byKey->put($key, $row);
                }
            }
        }

        return $byKey;
    }

    /**
     * DB stores punch instants in UTC (see FingerprintIclockAttendanceService / Zk ingest).
     * Use raw column value to avoid double timezone interpretation from Eloquent casts.
     */
    private function punchToRiyadhFromModel(ZkDailyAttendance $row, string $column): ?string
    {
        try {
            $raw = $row->getRawOriginal($column);
            if ($raw === null || $raw === '') {
                return null;
            }

            return Carbon::parse((string) $raw, 'UTC')->timezone('Asia/Riyadh')->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
