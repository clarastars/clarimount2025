<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(
        private LeaveRequestNotificationService $notificationService,
        private LeaveApprovalService $leaveApprovalService,
        private LeaveApprovalNotificationService $leaveApprovalNotificationService,
    ) {}

    public function submitForEmployee(Employee $employee, Request $request): LeaveRequest
    {
        $validated = $request->validate([
            'leave_type' => ['required', 'string', 'in:'.implode(',', Leave::TYPES)],
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'deduct_from_balance' => 'required|boolean',
            'is_paid' => 'required|boolean',
            'notes' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $days = (int) ($startDate->diffInDays($endDate) + 1);

        $this->assertNoOverlappingRequests($employee, $startDate, $endDate);
        $this->assertNoOverlappingApprovedLeaves($employee, $startDate, $endDate);

        if ($validated['deduct_from_balance']) {
            $pendingDays = $this->pendingDeductibleDaysForEmployee($employee);
            $remaining = (float) $employee->remaining_annual_leave_balance - $pendingDays;

            if ($remaining < $days) {
                throw ValidationException::withMessages([
                    'start_date' => [__('messages.leaves.insufficient_balance', [
                        'remaining' => max($remaining, 0),
                        'requested' => $days,
                    ])],
                ]);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave-attachments', 'public');
        }

        $leaveRequest = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days' => $days,
            'deduct_from_balance' => $validated['deduct_from_balance'],
            'is_paid' => $validated['is_paid'],
            'notes' => $validated['notes'] ?? null,
            'attachment_path' => $attachmentPath,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $leaveRequest->load(['employee.company']);
        $company = $leaveRequest->employee->company;
        $actor = $leaveRequest->employee->user ?? User::make(['name' => $leaveRequest->employee->full_name]);

        if ($company !== null && $this->leaveApprovalService->hasActiveStepsForCompany($company)) {
            $this->leaveApprovalNotificationService->notifyWorkflowStarted($leaveRequest, $company, $actor);
        } else {
            $this->notificationService->notifySubmitted($leaveRequest);
        }

        return $leaveRequest;
    }

    public function approve(
        LeaveRequest $leaveRequest,
        User $reviewer,
        ?string $reviewNotes = null,
        bool $skipEmployeeNotification = false,
    ): Leave {
        if (! $leaveRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('messages.leaves.request_already_processed')],
            ]);
        }

        $employee = $leaveRequest->employee;
        $employee->append('remaining_annual_leave_balance');

        if ($leaveRequest->deduct_from_balance) {
            $pendingDays = $this->pendingDeductibleDaysForEmployee($employee, $leaveRequest->id);
            $remaining = (float) $employee->remaining_annual_leave_balance - $pendingDays;

            if ($remaining < $leaveRequest->days) {
                throw ValidationException::withMessages([
                    'start_date' => [__('messages.leaves.insufficient_balance', [
                        'remaining' => max($remaining, 0),
                        'requested' => $leaveRequest->days,
                    ])],
                ]);
            }
        }

        $leave = DB::transaction(function () use ($leaveRequest, $reviewer, $reviewNotes, $employee): Leave {
            $leave = Leave::query()->create([
                'employee_id' => $employee->id,
                'leave_type' => $leaveRequest->leave_type,
                'start_date' => $leaveRequest->start_date,
                'end_date' => $leaveRequest->end_date,
                'days' => $leaveRequest->days,
                'deduct_from_balance' => $leaveRequest->deduct_from_balance,
                'is_paid' => $leaveRequest->is_paid,
                'notes' => $leaveRequest->notes,
                'attachment_path' => $leaveRequest->attachment_path,
            ]);

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
                'leave_id' => $leave->id,
            ]);

            return $leave;
        });

        if (! $skipEmployeeNotification) {
            $this->notificationService->notifyEmployeeApproved($leaveRequest->fresh());
        }

        return $leave;
    }

    public function reject(LeaveRequest $leaveRequest, User $reviewer, ?string $reviewNotes = null): LeaveRequest
    {
        if (! $leaveRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('messages.leaves.request_already_processed')],
            ]);
        }

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        $freshRequest = $leaveRequest->fresh();
        $this->notificationService->notifyEmployeeRejected($freshRequest);

        return $freshRequest;
    }

    public function cancelByEmployee(LeaveRequest $leaveRequest, Employee $employee): void
    {
        abort_unless((int) $leaveRequest->employee_id === (int) $employee->id, 403);

        if (! $leaveRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('messages.leaves.request_already_processed')],
            ]);
        }

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_CANCELLED,
        ]);

        $leaveRequest->stepApprovals()->delete();
    }

    private function pendingDeductibleDaysForEmployee(Employee $employee, ?int $excludeRequestId = null): int
    {
        return (int) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->where('deduct_from_balance', true)
            ->when($excludeRequestId !== null, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->sum('days');
    }

    private function assertNoOverlappingRequests(Employee $employee, Carbon $startDate, Carbon $endDate): void
    {
        $overlap = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => [__('messages.leaves.request_overlap_pending')],
            ]);
        }
    }

    private function assertNoOverlappingApprovedLeaves(Employee $employee, Carbon $startDate, Carbon $endDate): void
    {
        $overlap = Leave::query()
            ->where('employee_id', $employee->id)
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => [__('messages.leaves.request_overlap_existing')],
            ]);
        }
    }
}
