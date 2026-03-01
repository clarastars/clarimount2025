<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    /**
     * Show the form for creating a new leave for the employee.
     */
    public function create(Employee $employee): Response|RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        if (!$ownedCompanyIds->contains($employee->company_id)) {
            abort(403);
        }

        $employee->load(['company']);

        return Inertia::render('Employees/LeaveCreate', [
            'employee' => $employee,
            'leaveTypes' => Leave::TYPES,
        ]);
    }

    /**
     * Store a newly created leave.
     */
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        if (!$ownedCompanyIds->contains($employee->company_id)) {
            abort(403);
        }

        $validated = $request->validate([
            'leave_type' => ['required', 'string', 'in:' . implode(',', Leave::TYPES)],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'deduct_from_balance' => 'required|boolean',
            'is_paid' => 'required|boolean',
            'notes' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $days = $startDate->diffInDays($endDate) + 1;

        if ($validated['deduct_from_balance']) {
            $remaining = $employee->remaining_annual_leave_balance;
            if ($remaining < $days) {
                return back()->withErrors([
                    'start_date' => __('leaves.insufficient_balance', ['remaining' => $remaining, 'requested' => $days]),
                ]);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave-attachments', 'public');
        }

        Leave::create([
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

        return redirect()->route('employees.show', $employee)
            ->with('success', __('leaves.created_success'));
    }
}
