<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\AttendancePenaltyApprovedMail;
use App\Models\AttendancePenalty;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AttendancePenaltyApprovalNotifier
{
    public function notifyEmployeeOfApproval(AttendancePenalty $penalty): void
    {
        $penalty->loadMissing('employee');

        $employeeEmail = $penalty->employee?->work_email ?: $penalty->employee?->personal_email;
        if (empty($employeeEmail) || ! filter_var($employeeEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($employeeEmail)->send(new AttendancePenaltyApprovedMail($penalty));
        } catch (\Throwable $exception) {
            Log::error('Failed to send approved attendance penalty email.', [
                'penalty_id' => $penalty->id,
                'employee_id' => $penalty->employee_id,
                'employee_email' => $employeeEmail,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
