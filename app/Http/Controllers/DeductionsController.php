<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttendancePenalty;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Services\ManualDeductionAmountService;
use App\Services\OperationalMonthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeductionsController extends Controller
{
    public function __construct(
        private readonly ManualDeductionAmountService $manualDeductionAmountService,
        private readonly OperationalMonthService $operationalMonthService,
    ) {}

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

        $monthInput = $request->query('month');
        if ($monthInput === null || $monthInput === '') {
            $currentPayroll = $this->operationalMonthService->resolvePayrollMonthForDate(\Carbon\Carbon::now('Asia/Riyadh'));
            $canonical = [
                'year' => $currentPayroll['year'],
                'month' => $currentPayroll['month'],
                'ym' => sprintf('%04d-%02d', $currentPayroll['year'], $currentPayroll['month']),
            ];
        } else {
            $canonical = $this->operationalMonthService->parseCanonicalPayrollYm((string) $monthInput);
        }
        $month = $canonical['ym'];
        $employeeId = $request->query('employee_id');
        $range = $this->operationalMonthService->resolveRangeForPayrollMonth($canonical['year'], $canonical['month']);
        $start = $range['start'];
        $end = $range['end'];

        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_id', 'company_id', 'basic_salary', 'allowances']);

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
            'monthPeriodStart' => $start->format('Y-m-d'),
            'monthPeriodEnd' => $end->format('Y-m-d'),
            'employeeId' => $employeeId,
            'approvedPenalties' => $approvedPenalties->map(fn (AttendancePenalty $p) => [
                'id' => $p->id,
                'type' => 'penalty',
                'employee_id' => $p->employee_id,
                'employee_name' => $p->employee ? $p->employee->full_name : '-',
                'employee_code' => $p->employee?->employee_id,
                'date' => \Carbon\Carbon::parse((string) $p->attendance_date)->format('Y-m-d'),
                'action_text' => $p->action_text,
                'reason_text' => $p->reason_text,
                'late_minutes_deduction_amount' => $p->late_minutes_deduction_amount !== null ? (float) $p->late_minutes_deduction_amount : null,
                'approved_at' => $p->approved_at?->toIso8601String(),
                'approver_name' => $p->approver?->name,
            ]),
            'manualDeductions' => $manualDeductions->map(fn (EmployeeDeduction $d) => [
                'id' => $d->id,
                'type' => 'manual',
                'employee_id' => $d->employee_id,
                'employee_name' => $d->employee ? $d->employee->full_name : '-',
                'employee_code' => $d->employee?->employee_id,
                'date' => \Carbon\Carbon::parse((string) $d->deduction_date)->format('Y-m-d'),
                'amount' => (float) $d->amount,
                'amount_input_mode' => $d->amount_input_mode ?? ManualDeductionAmountService::INPUT_MANUAL,
                'amount_input_days' => $d->amount_input_days !== null ? (float) $d->amount_input_days : null,
                'amount_input_percent' => $d->amount_input_percent !== null ? (float) $d->amount_input_percent : null,
                'deduction_type' => $d->deduction_type,
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
            'amount_input_mode' => ['required', Rule::in(ManualDeductionAmountService::INPUT_MODES)],
            'amount' => [
                Rule::requiredIf(fn () => $request->input('amount_input_mode') === ManualDeductionAmountService::INPUT_MANUAL),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'amount_input_days' => [
                Rule::requiredIf(fn () => in_array(
                    (string) $request->input('amount_input_mode'),
                    [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAYS],
                    true
                )),
                'nullable',
                'numeric',
                'min:0.01',
                'max:365',
            ],
            'amount_input_percent' => [
                Rule::requiredIf(fn () => in_array(
                    (string) $request->input('amount_input_mode'),
                    [
                        ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT,
                        ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT,
                    ],
                    true
                )),
                'nullable',
                'numeric',
                'min:0.01',
                'max:100',
            ],
            'deduction_date' => ['required', 'date'],
            'deduction_type' => ['required', Rule::in(EmployeeDeduction::TYPES)],
            'reason' => ['nullable', 'string', 'max:65535'],
        ]);

        $mode = $validated['amount_input_mode'];
        $employee = Employee::query()->whereKey($validated['employee_id'])->firstOrFail();

        if (in_array($mode, [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT], true)
            && ! $this->manualDeductionAmountService->hasValidBasicSalary($employee)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['amount' => __('messages.attendance.deduction_basic_salary_required')]);
        }

        if (in_array($mode, [ManualDeductionAmountService::INPUT_GROSS_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT], true)
            && ! $this->manualDeductionAmountService->hasValidGrossSalary($employee)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['amount' => __('messages.attendance.deduction_gross_salary_required')]);
        }

        $manualAmount = isset($validated['amount']) && $validated['amount'] !== null
            ? (float) $validated['amount'] : null;
        $days = isset($validated['amount_input_days']) && $validated['amount_input_days'] !== null
            ? (float) $validated['amount_input_days'] : null;
        $percent = isset($validated['amount_input_percent']) && $validated['amount_input_percent'] !== null
            ? (float) $validated['amount_input_percent'] : null;

        $resolved = $this->manualDeductionAmountService->resolveAmount(
            $employee,
            $mode,
            $manualAmount,
            $days,
            $percent
        );

        if ($resolved === null || $resolved < 0.01) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['amount' => __('messages.attendance.deduction_amount_invalid')]);
        }

        EmployeeDeduction::create([
            'employee_id' => $validated['employee_id'],
            'amount' => $resolved,
            'amount_input_mode' => $mode,
            'amount_input_days' => in_array(
                $mode,
                [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAYS],
                true
            ) ? $days : null,
            'amount_input_percent' => in_array(
                $mode,
                [ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT],
                true
            ) ? $percent : null,
            'deduction_date' => $validated['deduction_date'],
            'deduction_type' => $validated['deduction_type'],
            'reason' => (string) ($validated['reason'] ?? ''),
            'created_by' => $user->id,
        ]);

        $company = Company::find($validated['company_id']);

        return redirect()
            ->route('attendance.deductions', ['company' => $company->id])
            ->with('success', __('messages.attendance.deduction_created'));
    }

    /**
     * Update a manual deduction.
     */
    public function update(Request $request, EmployeeDeduction $deduction): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->ownedCompanies()->where('id', $deduction->employee->company_id)->exists()) {
            abort(403, 'You do not have access to this deduction.');
        }

        $validated = $request->validate([
            'amount_input_mode' => ['required', Rule::in(ManualDeductionAmountService::INPUT_MODES)],
            'amount' => [
                Rule::requiredIf(fn () => $request->input('amount_input_mode') === ManualDeductionAmountService::INPUT_MANUAL),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'amount_input_days' => [
                Rule::requiredIf(fn () => in_array(
                    (string) $request->input('amount_input_mode'),
                    [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAYS],
                    true
                )),
                'nullable',
                'numeric',
                'min:0.01',
                'max:365',
            ],
            'amount_input_percent' => [
                Rule::requiredIf(fn () => in_array(
                    (string) $request->input('amount_input_mode'),
                    [
                        ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT,
                        ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT,
                    ],
                    true
                )),
                'nullable',
                'numeric',
                'min:0.01',
                'max:100',
            ],
            'deduction_date' => ['required', 'date'],
            'deduction_type' => ['required', Rule::in(EmployeeDeduction::TYPES)],
            'reason' => ['nullable', 'string', 'max:65535'],
        ]);

        $mode = $validated['amount_input_mode'];
        $employee = $deduction->employee;
        if ($employee === null) {
            abort(500);
        }

        if (in_array($mode, [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT], true)
            && ! $this->manualDeductionAmountService->hasValidBasicSalary($employee)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['amount' => __('messages.attendance.deduction_basic_salary_required')]);
        }

        if (in_array($mode, [ManualDeductionAmountService::INPUT_GROSS_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT], true)
            && ! $this->manualDeductionAmountService->hasValidGrossSalary($employee)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['amount' => __('messages.attendance.deduction_gross_salary_required')]);
        }

        $manualAmount = isset($validated['amount']) && $validated['amount'] !== null
            ? (float) $validated['amount'] : null;
        $days = isset($validated['amount_input_days']) && $validated['amount_input_days'] !== null
            ? (float) $validated['amount_input_days'] : null;
        $percent = isset($validated['amount_input_percent']) && $validated['amount_input_percent'] !== null
            ? (float) $validated['amount_input_percent'] : null;

        $resolved = $this->manualDeductionAmountService->resolveAmount(
            $employee,
            $mode,
            $manualAmount,
            $days,
            $percent
        );

        if ($resolved === null || $resolved < 0.01) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['amount' => __('messages.attendance.deduction_amount_invalid')]);
        }

        $deduction->update([
            'amount' => $resolved,
            'amount_input_mode' => $mode,
            'amount_input_days' => in_array(
                $mode,
                [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAYS],
                true
            ) ? $days : null,
            'amount_input_percent' => in_array(
                $mode,
                [ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT],
                true
            ) ? $percent : null,
            'deduction_date' => $validated['deduction_date'],
            'deduction_type' => $validated['deduction_type'],
            'reason' => (string) ($validated['reason'] ?? ''),
        ]);

        return redirect()
            ->route('attendance.deductions', ['company' => $deduction->employee->company_id])
            ->with('success', __('messages.attendance.deduction_updated'));
    }

    /**
     * Delete a manual deduction.
     */
    public function destroy(EmployeeDeduction $deduction): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->ownedCompanies()->where('id', $deduction->employee->company_id)->exists()) {
            abort(403, 'You do not have access to this deduction.');
        }

        $companyId = $deduction->employee->company_id;
        $deduction->delete();

        return redirect()
            ->route('attendance.deductions', ['company' => $companyId])
            ->with('success', __('messages.attendance.deduction_deleted'));
    }
}
