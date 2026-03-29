<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ZkDevice;
use App\Models\ZkAttendanceRaw;
use App\Models\ZkDailyAttendance;
use App\Models\Employee;
use App\Models\AttendancePenalty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Service for ingesting ZKTeco attendance log data from /iclock/cdata endpoint
 * 
 * Timezone Handling:
 * - Incoming timestamps are assumed to be in Asia/Riyadh timezone
 * - All timestamps are converted to UTC before storage (Laravel best practice)
 */
class ZkAttlogIngestService
{
    /**
     * Ingest attendance log data from ZKTeco device
     *
     * @param string $serialNumber Device serial number from query parameter SN
     * @param string $rawBody Raw request body containing TAB-separated lines
     * @param \DateTime $receivedAt Timestamp when the request was received
     * @return void
     */
    public function ingest(string $serialNumber, string $rawBody, \DateTime $receivedAt): void
    {
        // Get or create device
        $device = ZkDevice::firstOrCreate(
            ['serial_number' => $serialNumber],
            ['name' => null]
        );

        // Update device last_seen_at
        $device->update(['last_seen_at' => $receivedAt]);

        // Split body into lines and filter empty lines
        $lines = array_filter(
            explode("\n", $rawBody),
            fn($line) => trim($line) !== ''
        );

        // Process each line
        foreach ($lines as $line) {
            try {
                $this->processLine($device, trim($line), $receivedAt);
            } catch (\Exception $e) {
                // Log warning but continue processing other lines
                Log::channel('daily')->warning('[ZKTeco] Error processing attendance line', [
                    'device_id' => $device->id,
                    'serial_number' => $serialNumber,
                    'line' => $line,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process a single attendance log line
     *
     * @param ZkDevice $device
     * @param string $line TAB-separated line
     * @param \DateTime $receivedAt
     * @return void
     */
    private function processLine(ZkDevice $device, string $line, \DateTime $receivedAt): void
    {
        // Split by TAB
        $columns = explode("\t", $line);

        // Validate minimum columns required
        if (count($columns) < 2) {
            throw new \InvalidArgumentException('Line does not have enough columns');
        }

        // Extract data
        $devicePin = trim($columns[0]);
        $punchTimeStr = trim($columns[1]);
        $verifyMode = isset($columns[3]) && $columns[3] !== '' ? (int)$columns[3] : null;

        // Parse punch_time from Asia/Riyadh timezone and convert to UTC
        // Format: "2026-01-18 15:31:30"
        $punchTime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $punchTimeStr,
            'Asia/Riyadh'
        )->utc();

        // Validate device_pin
        if (empty($devicePin)) {
            throw new \InvalidArgumentException('Device PIN is empty');
        }

        // Get date from punch_time for daily attendance
        $attDate = $punchTime->copy()->setTimezone('Asia/Riyadh')->format('Y-m-d');

        // Use database transaction for atomicity
        DB::transaction(function () use ($device, $devicePin, $punchTime, $verifyMode, $line, $receivedAt, $attDate) {
            // Try to create raw attendance record (will skip if duplicate due to unique constraint)
            try {
                ZkAttendanceRaw::firstOrCreate(
                    [
                        'device_id' => $device->id,
                        'device_pin' => $devicePin,
                        'punch_time' => $punchTime,
                    ],
                    [
                        'verify_mode' => $verifyMode,
                        'raw_line' => $line,
                        'received_at' => Carbon::instance($receivedAt),
                    ]
                );
            } catch (\Illuminate\Database\QueryException $e) {
                // If duplicate (unique constraint violation), skip silently
                // This is expected behavior to prevent duplicate records
                if ($e->getCode() === '23000') {
                    return;
                }
                throw $e;
            }

            // Update or create daily attendance record
            $dailyAttendance = ZkDailyAttendance::firstOrNew([
                'device_id' => $device->id,
                'device_pin' => $devicePin,
                'att_date' => $attDate,
            ]);

            if ($dailyAttendance->exists) {
                // Update existing record
                // Update first_punch if this is earlier
                if ($punchTime->lt($dailyAttendance->first_punch)) {
                    $dailyAttendance->first_punch = $punchTime;
                    $dailyAttendance->first_verify_mode = $verifyMode;
                }

                // Update last_punch if this is later
                if ($punchTime->gt($dailyAttendance->last_punch)) {
                    $dailyAttendance->last_punch = $punchTime;
                    $dailyAttendance->last_verify_mode = $verifyMode;
                }

                // Increment punch count
                $dailyAttendance->punch_count = ($dailyAttendance->punch_count ?? 0) + 1;
            } else {
                // Create new record
                $dailyAttendance->first_punch = $punchTime;
                $dailyAttendance->last_punch = $punchTime;
                $dailyAttendance->first_verify_mode = $verifyMode;
                $dailyAttendance->last_verify_mode = $verifyMode;
                $dailyAttendance->punch_count = 1;
            }

            $dailyAttendance->save();

            // If employee has a punch, delete any absence penalty (employee came to work)
            if ($dailyAttendance->first_punch) {
                $employee = Employee::where('fingerprint_device_id', $devicePin)->first();
                if ($employee) {
                    AttendancePenalty::where('employee_id', $employee->id)
                        ->where('attendance_date', $attDate)
                        ->where('violation_type', 'absent_without_excuse')
                        ->delete();
                }
            }

            // Calculate penalty if attendance was created or first_punch was updated
            if ($dailyAttendance->wasRecentlyCreated || 
                ($dailyAttendance->exists && $dailyAttendance->wasChanged('first_punch'))) {
                try {
                    $this->calculateAttendancePenalty($dailyAttendance, $attDate);
                } catch (\Exception $e) {
                    // Log error but don't stop the attendance processing
                    Log::channel('daily')->warning('[ZKTeco] Error calculating attendance penalty', [
                        'device_id' => $device->id,
                        'device_pin' => $devicePin,
                        'attendance_date' => $attDate,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * Calculate attendance penalty for a daily attendance record
     *
     * @param ZkDailyAttendance $attendance
     * @param string $attDate Date in Y-m-d format
     * @return void
     */
    private function calculateAttendancePenalty(ZkDailyAttendance $attendance, string $attDate): void
    {
        // Get employee from device_pin
        $employee = Employee::with('shift.workdays')
            ->where('fingerprint_device_id', $attendance->device_pin)
            ->first();

        if (!$employee || !$employee->shift) {
            // No employee or no shift assigned, skip penalty calculation
            return;
        }

        // Get weekday of attendance date (0=Sunday, 6=Saturday)
        $attDateCarbon = Carbon::parse($attDate, 'Asia/Riyadh');
        $weekday = $attDateCarbon->dayOfWeek;
        
        // Check if it's a workday
        $workdays = $employee->shift->workdays()
            ->where('is_workday', true)
            ->pluck('weekday')
            ->toArray();

        if (!in_array($weekday, $workdays)) {
            // Not a workday, skip penalty calculation
            return;
        }

        // No first punch (absent), skip penalty calculation
        if (!$attendance->first_punch) {
            return;
        }

        // Calculate late minutes (same logic as AttendanceController; per-weekday start when set)
        $expectedStart = Carbon::parse(
            $attDate . ' ' . $employee->shift->effectiveStartTimeStringForWeekday($weekday),
            'Asia/Riyadh'
        );
        $firstPunch = Carbon::parse($attendance->first_punch)->setTimezone('Asia/Riyadh');
        
        // Calculate signed difference in minutes
        $actualLateMinutes = (int) round(($firstPunch->timestamp - $expectedStart->timestamp) / 60);
        
        // Apply grace period
        $lateMinutes = max(0, $actualLateMinutes - $employee->shift->grace_minutes);

        // Only calculate penalty if late
        if ($lateMinutes > 0) {
            $penaltyService = new \App\Services\AttendancePenaltyService();
            $penaltyService->calculatePenalty($employee->id, $attDate, $lateMinutes);
        }
    }
}
