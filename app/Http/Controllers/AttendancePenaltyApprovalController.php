<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RejectPenaltyRequest;
use App\Models\AttendancePenalty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendancePenaltyApprovalController extends Controller
{
    /**
     * Approve a penalty
     */
    public function approve(AttendancePenalty $penalty): RedirectResponse
    {
        // Verify user has access to this penalty's employee company
        $user = Auth::user();
        if (!$user->ownedCompanies()->where('id', $penalty->employee->company_id)->exists()) {
            abort(403, 'You do not have access to this penalty.');
        }

        $penalty->update([
            'approval_status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', __('Penalty approved successfully.'));
    }

    /**
     * Reject a penalty
     */
    public function reject(RejectPenaltyRequest $request, AttendancePenalty $penalty): RedirectResponse
    {
        // Verify user has access to this penalty's employee company
        $user = Auth::user();
        if (!$user->ownedCompanies()->where('id', $penalty->employee->company_id)->exists()) {
            abort(403, 'You do not have access to this penalty.');
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
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('penalty_rejections', $filename, 'public');
            $data['rejection_attachment_path'] = $path;
        }

        $penalty->update($data);

        return back()->with('success', __('Penalty rejected successfully.'));
    }
}
