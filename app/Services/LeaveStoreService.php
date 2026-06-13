<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\Leave;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeaveStoreService
{
    public function validateAndCreate(Request $request, Employee $employee): Leave
    {
        $validated = $request->validate([
            'leave_type' => ['required', 'string', 'in:'.implode(',', Leave::TYPES)],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'deduct_from_balance' => 'required|boolean',
            'is_paid' => 'required|boolean',
            'notes' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $days = (int) ($startDate->diffInDays($endDate) + 1);

        if ($validated['deduct_from_balance']) {
            $remaining = (float) $employee->remaining_annual_leave_balance;
            if ($remaining < $days) {
                throw ValidationException::withMessages([
                    'start_date' => [__('messages.leaves.insufficient_balance', [
                        'remaining' => $remaining,
                        'requested' => $days,
                    ])],
                ]);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave-attachments', 'public');
        }

        return Leave::create([
            'employee_id' => $employee->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days' => $days,
            'deduct_from_balance' => $validated['deduct_from_balance'],
            'is_paid' => $validated['is_paid'],
            'notes' => $validated['notes'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);
    }
}
