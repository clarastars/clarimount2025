<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\AttendancePenaltyService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestAttendancePenalties extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:test-penalties 
                            {--employee-id= : Employee ID to test}
                            {--date= : Date to test (Y-m-d format)}
                            {--late-minutes= : Late minutes to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test attendance penalty calculation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $employeeId = $this->option('employee-id');
        $date = $this->option('date') ?: Carbon::today()->format('Y-m-d');
        $lateMinutes = (int) ($this->option('late-minutes') ?: 10);

        // Get employee
        $employee = $employeeId
            ? Employee::with('shift')->find($employeeId)
            : Employee::with('shift')->first();

        if (! $employee) {
            $this->error('❌ No employee found');

            return 1;
        }

        if (! $employee->shift) {
            $this->error("❌ Employee {$employee->id} has no shift assigned");

            return 1;
        }

        $this->info("✅ Testing with Employee: {$employee->first_name} {$employee->last_name}");
        $this->info("   Employee ID: {$employee->id}");
        $this->info("   Fingerprint ID: {$employee->fingerprint_device_id}");
        $this->info("   Shift Start: {$employee->shift->start_time->format('H:i')}");
        $this->info("   Date: {$date}");
        $this->info("   Late Minutes: {$lateMinutes}\n");

        // Calculate penalty
        $service = app(AttendancePenaltyService::class);
        $penalty = $service->calculatePenalty($employee->id, $date, $lateMinutes);

        if ($penalty) {
            $this->info('✅ Penalty Created:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Action Text', $penalty->action_text],
                    ['Violation Type', $penalty->violation_type],
                    ['Repeat Number', $penalty->repeat_number],
                    ['Action Type', $penalty->action_type],
                    ['Action Value', $penalty->action_value ?? 'N/A'],
                    ['Late Minutes', $penalty->late_minutes],
                ]
            );
            $this->info("\n📝 Reason: {$penalty->reason_text}");
        } else {
            $this->warn('⚠️ No penalty created (late_minutes <= 0 or no rule found)');
        }

        // Show all penalties for this employee
        $allPenalties = \App\Models\AttendancePenalty::where('employee_id', $employee->id)
            ->orderBy('attendance_date', 'desc')
            ->get();

        if ($allPenalties->isNotEmpty()) {
            $this->info("\n📋 All Penalties for this Employee:");
            $this->table(
                ['Date', 'Late Min', 'Violation', 'Repeat', 'Action'],
                $allPenalties->map(function ($p) {
                    return [
                        $p->attendance_date->format('Y-m-d'),
                        $p->late_minutes,
                        $p->violation_type,
                        $p->repeat_number,
                        $p->action_text,
                    ];
                })->toArray()
            );
        } else {
            $this->info("\n📋 No penalties found for this employee.");
        }

        return 0;
    }
}
