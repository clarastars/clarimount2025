<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttendancePenalty;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeductionsController extends Controller
{
    /**
     * List approved attendance penalties and manual deductions for the month.
     * Filter by company (from route) and optional employee.
     */
    public function index(Request $request, Company $company): Response
    {
        $user = Auth::user();
        if (! $user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        $month = $request->query('month', now()->format('Y-m'));
        $employeeId = $request->query('employee_id');
        $start = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_id', 'company_id']);

        $approvedPenaltiesQuery = AttendancePenalty::query()
            ->approved()
            ->with(['employee:id,first_name,last_name,employee_id,company_id', 'approver:id,name'])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $company->id))
            ->whereBetween('attendance_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        if ($employeeId) {
            $approvedPenaltiesQuery->where('employee_id', $employeeId);
        }

        $approvedPenalties = $approvedPenaltiesQuery->orderBy('attendance_date', 'desc')->get();

        $manualDeductionsQuery = EmployeeDeduction::query()
            ->with(['employee:id,first_name,last_name,employee_id,company_id', 'creator:id,name'])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $company->id))
            ->whereBetween('deduction_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        if ($employeeId) {
            $manualDeductionsQuery->where('employee_id', $employeeId);
        }

        $manualDeductions = $manualDeductionsQuery->orderBy('deduction_date', 'desc')->get();

        $companies = $user->ownedCompanies()->orderBy('name_en')->get(['id', 'name_en', 'name_ar']);

        return Inertia::render('Attendance/Deductions', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'companies' => $companies,
            'employees' => $employees,
            'month' => $month,
            'employeeId' => $employeeId,
            'approvedPenalties' => $approvedPenalties->map(fn (AttendancePenalty $p) => [
                'id' => $p->id,
                'type' => 'penalty',
                'employee_id' => $p->employee_id,
                'employee_name' => $p->employee ? $p->employee->full_name : '-',
                'employee_code' => $p->employee?->employee_id,
                'date' => $p->attendance_date->format('Y-m-d'),
                'action_text' => $p->action_text,
                'reason_text' => $p->reason_text,
                'approved_at' => $p->approved_at?->toIso8601String(),
                'approver_name' => $p->approver?->name,
            ]),
            'manualDeductions' => $manualDeductions->map(fn (EmployeeDeduction $d) => [
                'id' => $d->id,
                'type' => 'manual',
                'employee_id' => $d->employee_id,
                'employee_name' => $d->employee ? $d->employee->full_name : '-',
                'employee_code' => $d->employee?->employee_id,
                'date' => $d->deduction_date->format('Y-m-d'),
                'amount' => (float) $d->amount,
                'reason' => $d->reason,
                'created_at' => $d->created_at->toIso8601String(),
                'creator_name' => $d->creator?->name,
            ]),
        ]);
    }

    /**
     * Store a new manual deduction.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'company_id' => ['required', Rule::in($user->ownedCompanies()->pluck('id')->toArray())],
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('company_id', $request->input('company_id')),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'deduction_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:65535'],
        ]);

        EmployeeDeduction::create([
            'employee_id' => $validated['employee_id'],
            'amount' => $validated['amount'],
            'deduction_date' => $validated['deduction_date'],
            'reason' => $validated['reason'],
            'created_by' => $user->id,
        ]);

        $company = Company::find($validated['company_id']);

        return redirect()
            ->route('attendance.deductions', ['company' => $company->id])
            ->with('success', __('messages.attendance.deduction_created'));
    }
}
