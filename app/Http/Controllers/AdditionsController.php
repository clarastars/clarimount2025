<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAddition;
use App\Services\ManualDeductionAmountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdditionsController extends Controller
{
    public function __construct(
        private readonly ManualDeductionAmountService $manualDeductionAmountService
    ) {}

    public function index(Request $request, Company $company): Response
    {
        $user = Auth::user();
        if (! $user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        $month = $request->query('month', now()->format('Y-m'));
        $employeeId = $request->query('employee_id');
        $start = \Carbon\Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_id', 'company_id', 'basic_salary', 'allowances']);

        $manualAdditionsQuery = EmployeeAddition::query()
            ->with(['employee:id,first_name,last_name,employee_id,company_id', 'creator:id,name'])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $company->id))
            ->whereBetween('addition_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        if ($employeeId) {
            $manualAdditionsQuery->where('employee_id', $employeeId);
        }

        $manualAdditions = $manualAdditionsQuery->orderBy('addition_date', 'desc')->get();
        $companies = $user->ownedCompanies()->orderBy('name_en')->get(['id', 'name_en', 'name_ar']);

        return Inertia::render('Attendance/Additions', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'companies' => $companies,
            'employees' => $employees,
            'month' => $month,
            'employeeId' => $employeeId,
            'manualAdditions' => $manualAdditions->map(fn (EmployeeAddition $a) => [
                'id' => $a->id,
                'employee_id' => $a->employee_id,
                'employee_name' => $a->employee ? $a->employee->full_name : '-',
                'employee_code' => $a->employee?->employee_id,
                'date' => \Carbon\Carbon::parse((string) $a->addition_date)->format('Y-m-d'),
                'amount' => (float) $a->amount,
                'amount_input_mode' => $a->amount_input_mode ?? ManualDeductionAmountService::INPUT_MANUAL,
                'amount_input_days' => $a->amount_input_days !== null ? (float) $a->amount_input_days : null,
                'amount_input_percent' => $a->amount_input_percent !== null ? (float) $a->amount_input_percent : null,
                'addition_type' => $a->addition_type,
                'reason' => $a->reason,
                'created_at' => $a->created_at->toIso8601String(),
                'creator_name' => $a->creator?->name,
            ]),
        ]);
    }

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
                    [ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT],
                    true
                )),
                'nullable',
                'numeric',
                'min:0.01',
                'max:100',
            ],
            'addition_date' => ['required', 'date'],
            'addition_type' => ['required', Rule::in(EmployeeAddition::TYPES)],
            'reason' => ['nullable', 'string', 'max:65535'],
        ]);

        $mode = $validated['amount_input_mode'];
        $employee = Employee::query()->whereKey($validated['employee_id'])->firstOrFail();

        if (in_array($mode, [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT], true)
            && ! $this->manualDeductionAmountService->hasValidBasicSalary($employee)) {
            return redirect()->back()->withInput()->withErrors(['amount' => __('messages.attendance.addition_basic_salary_required')]);
        }
        if (in_array($mode, [ManualDeductionAmountService::INPUT_GROSS_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT], true)
            && ! $this->manualDeductionAmountService->hasValidGrossSalary($employee)) {
            return redirect()->back()->withInput()->withErrors(['amount' => __('messages.attendance.addition_gross_salary_required')]);
        }

        $resolved = $this->manualDeductionAmountService->resolveAmount(
            $employee,
            $mode,
            isset($validated['amount']) && $validated['amount'] !== null ? (float) $validated['amount'] : null,
            isset($validated['amount_input_days']) && $validated['amount_input_days'] !== null ? (float) $validated['amount_input_days'] : null,
            isset($validated['amount_input_percent']) && $validated['amount_input_percent'] !== null ? (float) $validated['amount_input_percent'] : null
        );

        if ($resolved === null || $resolved < 0.01) {
            return redirect()->back()->withInput()->withErrors(['amount' => __('messages.attendance.addition_amount_invalid')]);
        }

        EmployeeAddition::create([
            'employee_id' => $validated['employee_id'],
            'amount' => $resolved,
            'amount_input_mode' => $mode,
            'amount_input_days' => in_array($mode, [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAYS], true)
                ? (float) ($validated['amount_input_days'] ?? 0) : null,
            'amount_input_percent' => in_array($mode, [ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT], true)
                ? (float) ($validated['amount_input_percent'] ?? 0) : null,
            'addition_date' => $validated['addition_date'],
            'addition_type' => $validated['addition_type'],
            'reason' => (string) ($validated['reason'] ?? ''),
            'created_by' => $user->id,
        ]);

        $company = Company::find($validated['company_id']);

        return redirect()
            ->route('attendance.additions', ['company' => $company->id])
            ->with('success', __('messages.attendance.addition_created'));
    }

    public function update(Request $request, EmployeeAddition $addition): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->ownedCompanies()->where('id', $addition->employee->company_id)->exists()) {
            abort(403, 'You do not have access to this addition.');
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
                    [ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT],
                    true
                )),
                'nullable',
                'numeric',
                'min:0.01',
                'max:100',
            ],
            'addition_date' => ['required', 'date'],
            'addition_type' => ['required', Rule::in(EmployeeAddition::TYPES)],
            'reason' => ['nullable', 'string', 'max:65535'],
        ]);

        $mode = $validated['amount_input_mode'];
        $employee = $addition->employee;
        if ($employee === null) {
            abort(500);
        }

        if (in_array($mode, [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT], true)
            && ! $this->manualDeductionAmountService->hasValidBasicSalary($employee)) {
            return redirect()->back()->withInput()->withErrors(['amount' => __('messages.attendance.addition_basic_salary_required')]);
        }
        if (in_array($mode, [ManualDeductionAmountService::INPUT_GROSS_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT], true)
            && ! $this->manualDeductionAmountService->hasValidGrossSalary($employee)) {
            return redirect()->back()->withInput()->withErrors(['amount' => __('messages.attendance.addition_gross_salary_required')]);
        }

        $resolved = $this->manualDeductionAmountService->resolveAmount(
            $employee,
            $mode,
            isset($validated['amount']) && $validated['amount'] !== null ? (float) $validated['amount'] : null,
            isset($validated['amount_input_days']) && $validated['amount_input_days'] !== null ? (float) $validated['amount_input_days'] : null,
            isset($validated['amount_input_percent']) && $validated['amount_input_percent'] !== null ? (float) $validated['amount_input_percent'] : null
        );

        if ($resolved === null || $resolved < 0.01) {
            return redirect()->back()->withInput()->withErrors(['amount' => __('messages.attendance.addition_amount_invalid')]);
        }

        $addition->update([
            'amount' => $resolved,
            'amount_input_mode' => $mode,
            'amount_input_days' => in_array($mode, [ManualDeductionAmountService::INPUT_BASIC_DAYS, ManualDeductionAmountService::INPUT_GROSS_DAYS], true)
                ? (float) ($validated['amount_input_days'] ?? 0) : null,
            'amount_input_percent' => in_array($mode, [ManualDeductionAmountService::INPUT_BASIC_DAILY_PERCENT, ManualDeductionAmountService::INPUT_GROSS_DAILY_PERCENT], true)
                ? (float) ($validated['amount_input_percent'] ?? 0) : null,
            'addition_date' => $validated['addition_date'],
            'addition_type' => $validated['addition_type'],
            'reason' => (string) ($validated['reason'] ?? ''),
        ]);

        return redirect()
            ->route('attendance.additions', ['company' => $addition->employee->company_id])
            ->with('success', __('messages.attendance.addition_updated'));
    }

    public function destroy(EmployeeAddition $addition): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->ownedCompanies()->where('id', $addition->employee->company_id)->exists()) {
            abort(403, 'You do not have access to this addition.');
        }

        $companyId = $addition->employee->company_id;
        $addition->delete();

        return redirect()
            ->route('attendance.additions', ['company' => $companyId])
            ->with('success', __('messages.attendance.addition_deleted'));
    }
}
