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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(
        private LeaveRequestNotificationService $notificationService,
        private LeaveApprovalService $leaveApprovalService,
        private LeaveApprovalNotificationService $leaveApprovalNotificationService,
        private LeaveTypeService $leaveTypeService,
        private LeaveBalanceService $leaveBalanceService,
        private LeaveAttachmentService $leaveAttachmentService,
    ) {}

    public function submitForEmployee(Employee $employee, Request $request, ?User $submittedBy = null): LeaveRequest
    {
        $validated = $request->validate([
            'leave_type' => ['required', 'string', Rule::in($this->leaveTypeService->activeKeys())],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'deduct_from_balance' => 'required|boolean',
            'is_paid' => 'required|boolean',
            'notes' => 'nullable|string|max:2000',
            ...$this->leaveAttachmentService->validationRules(),
        ]);

        $leaveType = $this->leaveTypeService->findActiveByKey((string) $validated['leave_type']);
        if ($leaveType === null) {
            throw ValidationException::withMessages([
                'leave_type' => [__('messages.leaves.invalid_leave_type')],
            ]);
        }

        $this->leaveTypeService->ensureStartDateAllowed($leaveType, (string) $validated['start_date']);
        $this->leaveTypeService->ensureMinimumNoticeDays($leaveType, (string) $validated['start_date']);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $days = (int) ($startDate->diffInDays($endDate) + 1);

        $this->assertNoOverlappingRequests($employee, $startDate, $endDate);
        $this->assertNoOverlappingApprovedLeaves($employee, $startDate, $endDate);

        $forecast = null;
        if ($validated['deduct_from_balance']) {
            $forecast = $this->leaveBalanceService->assertSufficientBalance($employee, $startDate, $days);
        }

        $attachmentPayload = $this->leaveAttachmentService->persistPayload(
            $this->leaveAttachmentService->storeFromRequest($request),
        );

        $leaveRequest = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days' => $days,
            'current_remaining_at_submit' => $forecast['current_remaining'] ?? null,
            'projected_remaining_at_start' => $forecast['projected_remaining'] ?? null,
            'deduct_from_balance' => $validated['deduct_from_balance'],
            'is_paid' => $validated['is_paid'],
            'notes' => $validated['notes'] ?? null,
            'attachment_path' => $attachmentPayload['attachment_path'],
            'attachment_paths' => $attachmentPayload['attachment_paths'],
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $leaveRequest->load(['employee.company', 'employee.user']);
        $company = $leaveRequest->employee->company;
        $actor = $submittedBy
            ?? $leaveRequest->employee->user
            ?? User::make(['name' => $leaveRequest->employee->full_name]);

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

        if ($leaveRequest->deduct_from_balance) {
            $this->leaveBalanceService->assertSufficientBalance(
                $employee,
                $leaveRequest->start_date,
                (float) $leaveRequest->days,
                $leaveRequest->id,
            );
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
                'attachment_paths' => $leaveRequest->attachmentPaths(),
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
