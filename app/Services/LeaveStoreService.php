<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\Leave;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveStoreService
{
    public function __construct(
        private LeaveTypeService $leaveTypeService,
        private LeaveBalanceService $leaveBalanceService,
        private LeaveAttachmentService $leaveAttachmentService,
    ) {}

    public function validateAndCreate(Request $request, Employee $employee): Leave
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

        if ($this->leaveTypeService->findActiveByKey((string) $validated['leave_type']) === null) {
            throw ValidationException::withMessages([
                'leave_type' => [__('messages.leaves.invalid_leave_type')],
            ]);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $days = (int) ($startDate->diffInDays($endDate) + 1);

        if ($validated['deduct_from_balance']) {
            $this->leaveBalanceService->assertSufficientBalance($employee, $startDate, $days);
        }

        $attachmentPayload = $this->leaveAttachmentService->persistPayload(
            $this->leaveAttachmentService->storeFromRequest($request),
        );

        return Leave::create([
            'employee_id' => $employee->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days' => $days,
            'deduct_from_balance' => $validated['deduct_from_balance'],
            'is_paid' => $validated['is_paid'],
            'notes' => $validated['notes'] ?? null,
            'attachment_path' => $attachmentPayload['attachment_path'],
            'attachment_paths' => $attachmentPayload['attachment_paths'],
        ]);
    }
}
