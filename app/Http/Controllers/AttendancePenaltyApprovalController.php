<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesAttendanceAccess;
use App\Http\Requests\RejectPenaltyRequest;
use App\Models\Company;
use App\Models\AttendancePenalty;
use App\Services\AttendancePenaltyApprovalNotifier;
use App\Services\AttendancePenaltyService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AttendancePenaltyApprovalController extends Controller
{
    use AuthorizesAttendanceAccess;

    public function __construct(
        private AttendancePenaltyService $attendancePenaltyService,
        private AttendancePenaltyApprovalNotifier $approvalNotifier,
    ) {}

    /**
     * Approve a penalty
     */
    public function approve(AttendancePenalty $penalty): RedirectResponse
    {
        $penalty->loadMissing(['employee.company']);

        $user = Auth::user();
        $company = Company::findOrFail($penalty->employee->company_id);
        $this->abortUnlessCanManageAttendanceAdjustments($user, $company);

        $penalty->update([
            'approval_status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this->approvalNotifier->notifyEmployeeOfApproval($penalty->fresh());

        return back()->with('success', __('Penalty approved successfully.'));
    }

    /**
     * Reject a penalty
     */
    public function reject(RejectPenaltyRequest $request, AttendancePenalty $penalty): RedirectResponse
    {
        $penalty->loadMissing(['employee.company']);

        $user = Auth::user();
        $company = Company::findOrFail($penalty->employee->company_id);
        $this->abortUnlessCanManageAttendanceAdjustments($user, $company);

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
