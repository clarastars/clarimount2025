<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RejectPenaltyRequest;
use App\Mail\AttendancePenaltyApprovedMail;
use App\Models\AttendancePenalty;
use App\Services\AttendancePenaltyService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AttendancePenaltyApprovalController extends Controller
{
    public function __construct(
        private AttendancePenaltyService $attendancePenaltyService
    ) {}

    /**
     * Approve a penalty
     */
    public function approve(AttendancePenalty $penalty): RedirectResponse
    {
        $penalty->loadMissing(['employee.company']);

        // Verify user has access to this penalty's employee company
        $user = Auth::user();
        if (! $user->ownedCompanies()->where('id', $penalty->employee->company_id)->exists()) {
            abort(403, 'You do not have access to this penalty.');
        }

        $penalty->update([
            'approval_status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $employeeEmail = $penalty->employee->work_email ?: $penalty->employee->personal_email;
        if (! empty($employeeEmail) && filter_var($employeeEmail, FILTER_VALIDATE_EMAIL)) {
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

        return back()->with('success', __('Penalty approved successfully.'));
    }

    /**
     * Reject a penalty
     */
    public function reject(RejectPenaltyRequest $request, AttendancePenalty $penalty): RedirectResponse
    {
        $penalty->loadMissing(['employee.company']);

        // Verify user has access to this penalty's employee company
        $user = Auth::user();
        if (! $user->ownedCompanies()->where('id', $penalty->employee->company_id)->exists()) {
            abort(403, 'You do not have access to this penalty.');
        }

        if ($penalty->approval_status === 'rejected') {
            return back()->with('error', __('messages.attendance.penalty_already_rejected'));
        }

        $data = [
            'approval_status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $request->input('rejection_reason'),
        ];

        // Handle file upload if provided
        if ($request->hasFile('rejection_attachment')) {
            $file = $request->file('rejection_attachment');
            $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('penalty_rejections', $filename, 'public');
            $data['rejection_attachment_path'] = $path;
        }

        $penalty->update($data);

        // After rejecting one penalty, re-sequence remaining penalties for same employee/type/payroll period
        // so later occurrences shift down (e.g. third becomes second).
        $attDateYmd = Carbon::parse($penalty->attendance_date)->format('Y-m-d');
        $this->attendancePenaltyService->resequenceMonthlyPenaltiesAfterRejection(
            (int) $penalty->employee_id,
            (string) $penalty->violation_type,
            $attDateYmd
        );

        return back()->with('success', __('Penalty rejected successfully.'));
    }
}
