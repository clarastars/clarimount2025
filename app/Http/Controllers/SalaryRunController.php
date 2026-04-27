<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\SalaryRunExcelExport;
use App\Models\Company;
use App\Models\EmployeeDebt;
use App\Models\Employee;
use App\Models\SalaryRun;
use App\Models\SalaryRunItem;
use App\Services\SalaryRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalaryRunController extends Controller
{
    public function __construct(
        private SalaryRunService $salaryRunService
    ) {}

    private function canAccessCompanyWithReadOnlyPermission($user, Company $company, string $permission): bool
    {
        if ($user->ownedCompanies()->where('id', $company->id)->exists()) {
            return true;
        }

        if ($user->can('companies-salary-runs.global-read-approve')) {
            return true;
        }

        if (! $user->can($permission)) {
            return false;
        }

        $employeeCompanyId = Employee::query()
            ->where('user_id', $user->id)
            ->value('company_id');

        return (int) $employeeCompanyId === (int) $company->id;
    }

    private function canManageCompanySalaryRuns($user, Company $company): bool
    {
        return $user->ownedCompanies()->where('id', $company->id)->exists();
    }

    /**
     * Display a listing of salary runs for a company
     */
    public function index(Request $request, Company $company): Response
    {
        $user = Auth::user();

        if (! $this->canAccessCompanyWithReadOnlyPermission($user, $company, 'salary-runs.readonly')) {
            abort(403, 'You do not have access to this company.');
        }

        $salaryRuns = SalaryRun::where('company_id', $company->id)
            ->withCount('items')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(12);

        return Inertia::render('SalaryRuns/Index', [
            'company' => $company,
            'salaryRuns' => $salaryRuns,
            'canManageSalaryRuns' => $this->canManageCompanySalaryRuns($user, $company),
        ]);
    }

    /**
     * Display a specific salary run
     */
    public function show(Company $company, int $year, int $month): Response
    {
        $user = Auth::user();

        if (! $this->canAccessCompanyWithReadOnlyPermission($user, $company, 'salary-runs.readonly')) {
            abort(403, 'You do not have access to this company.');
        }

        $salaryRun = SalaryRun::where('company_id', $company->id)
            ->where('year', $year)
            ->where('month', $month)
            ->with(['items.employee.debts', 'creator', 'approverHr', 'approverDirector', 'approverAccountant', 'approverCeo'])
            ->firstOrFail();

        $approvals = [
            'hr' => [
                'approved_at' => $salaryRun->hr_approved_at?->toIso8601String(),
                'approver_name' => $salaryRun->approverHr?->name,
                'can_approve' => $user->can('approve_salary_run_hr')
                    || $user->can('salary-runs.readonly')
                    || $user->can('companies-salary-runs.global-read-approve'),
            ],
            'director' => [
                'approved_at' => $salaryRun->director_approved_at?->toIso8601String(),
                'approver_name' => $salaryRun->approverDirector?->name,
                'can_approve' => $user->can('approve_salary_run_director')
                    || $user->can('salary-runs.readonly')
                    || $user->can('companies-salary-runs.global-read-approve'),
            ],
            'accountant' => [
                'approved_at' => $salaryRun->accountant_approved_at?->toIso8601String(),
                'approver_name' => $salaryRun->approverAccountant?->name,
                'can_approve' => $user->can('approve_salary_run_accountant')
                    || $user->can('salary-runs.readonly')
                    || $user->can('companies-salary-runs.global-read-approve'),
            ],
            'ceo' => [
                'approved_at' => $salaryRun->ceo_approved_at?->toIso8601String(),
                'approver_name' => $salaryRun->approverCeo?->name,
                'can_approve' => $user->can('approve_salary_run_ceo')
                    || $user->can('salary-runs.readonly')
                    || $user->can('companies-salary-runs.global-read-approve'),
            ],
        ];

        return Inertia::render('SalaryRuns/Show', [
            'company' => $company,
            'salaryRun' => $salaryRun,
            'approvals' => $approvals,
            'canManageSalaryRun' => $this->canManageCompanySalaryRuns($user, $company),
        ]);
    }

    /**
     * Export salary run to Excel
     */
    public function exportExcel(Company $company, SalaryRun $salaryRun): BinaryFileResponse
    {
        $user = Auth::user();
        if (! $this->canAccessCompanyWithReadOnlyPermission($user, $company, 'salary-runs.readonly')) {
            abort(403, 'You do not have access to this company.');
        }
        if ($salaryRun->company_id !== $company->id) {
            abort(403, 'Salary run does not belong to this company.');
        }

        $filename = sprintf(
            'salary-run-%s-%s-%s.xlsx',
            $company->id,
            $salaryRun->year,
            str_pad((string) $salaryRun->month, 2, '0', STR_PAD_LEFT)
        );

        return Excel::download(new SalaryRunExcelExport($salaryRun), $filename, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Create or update a salary run
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $user = Auth::user();

        // Verify user owns this company
        if (! $user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $salaryRun = $this->salaryRunService->createOrUpdateSalaryRun(
            $company->id,
            $validated['year'],
            $validated['month']
        );

        return redirect()
            ->route('salary-runs.show', [$company, $validated['year'], $validated['month']])
            ->with('success', __('Salary run created successfully.'));
    }

    /**
     * Delete a salary run.
     */
    public function destroy(Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        if ($salaryRun->company_id !== $company->id) {
            abort(403, 'Salary run does not belong to this company.');
        }

        $salaryRun->delete();

        return redirect()
            ->route('salary-runs.index', $company->id)
            ->with('success', __('messages.salary_runs.deleted_successfully'));
    }

    /**
     * Finalize a salary run
     */
    public function finalize(Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        $user = Auth::user();

        // Verify user owns this company
        if (! $user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        // Verify salary run belongs to company
        if ($salaryRun->company_id !== $company->id) {
            abort(403, 'Salary run does not belong to this company.');
        }

        DB::transaction(function () use ($salaryRun) {
            // Apply debt deductions before finalizing
            $this->salaryRunService->applyDebtDeductions($salaryRun);

            $salaryRun->update([
                'status' => 'finalized',
            ]);
        });

        return back()->with('success', __('Salary run finalized successfully.'));
    }

    private function approveStep(Company $company, SalaryRun $salaryRun, string $permission, string $approvedAtColumn, string $approvedByColumn): RedirectResponse
    {
        $user = Auth::user();
        if (! $this->canAccessCompanyWithReadOnlyPermission($user, $company, 'salary-runs.readonly')) {
            abort(403, 'You do not have access to this company.');
        }
        if ($salaryRun->company_id !== $company->id) {
            abort(403, 'Salary run does not belong to this company.');
        }
        if (! $user->can($permission)
            && ! $user->can('salary-runs.readonly')
            && ! $user->can('companies-salary-runs.global-read-approve')) {
            abort(403, 'You do not have permission to perform this approval.');
        }
        if ($salaryRun->$approvedAtColumn !== null) {
            return back()->with('info', __('messages.salary_runs.already_approved'));
        }

        $salaryRun->update([
            $approvedAtColumn => now(),
            $approvedByColumn => $user->id,
        ]);

        return back()->with('success', __('messages.salary_runs.approval_saved'));
    }

    public function approveHr(Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        return $this->approveStep($company, $salaryRun, 'approve_salary_run_hr', 'hr_approved_at', 'hr_approved_by');
    }

    public function approveDirector(Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        return $this->approveStep($company, $salaryRun, 'approve_salary_run_director', 'director_approved_at', 'director_approved_by');
    }

    public function approveAccountant(Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        return $this->approveStep($company, $salaryRun, 'approve_salary_run_accountant', 'accountant_approved_at', 'accountant_approved_by');
    }

    public function approveCeo(Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        return $this->approveStep($company, $salaryRun, 'approve_salary_run_ceo', 'ceo_approved_at', 'ceo_approved_by');
    }

    /**
     * Update debt deductions for a salary run item
     */
    public function updateDebtDeductions(Request $request, Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        $user = Auth::user();

        // Verify user owns this company
        if (! $user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        // Verify salary run belongs to company
        if ($salaryRun->company_id !== $company->id) {
            abort(403, 'Salary run does not belong to this company.');
        }

        // Verify salary run is not finalized
        if ($salaryRun->status === 'finalized') {
            return back()->with('error', __('messages.salary_runs.cannot_update_finalized'));
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'debt_deductions' => 'required|array',
            'debt_deductions.*.debt_id' => 'required|exists:employee_debts,id',
            'debt_deductions.*.amount' => 'required|numeric|min:0.01',
        ]);

        $item = SalaryRunItem::where('salary_run_id', $salaryRun->id)
            ->where('employee_id', $validated['employee_id'])
            ->firstOrFail();

        // Verify employee belongs to company
        if ($item->employee->company_id !== $company->id) {
            abort(403, 'Employee does not belong to this company.');
        }

        // Validate debt amounts don't exceed remaining debt amounts
        $debtDeductions = [];
        $totalDeduction = 0;

        foreach ($validated['debt_deductions'] as $deduction) {
            $debt = EmployeeDebt::find($deduction['debt_id']);

            if (! $debt || $debt->employee_id !== $validated['employee_id']) {
                continue;
            }

            $deductionAmount = (float) $deduction['amount'];

            if ($deductionAmount > $debt->amount) {
                return back()->withErrors([
                    'debt_deductions' => __('messages.debts.deduction_exceeds_debt', [
                        'debt_type' => $debt->debt_type ?? __('messages.debts.debt'),
                        'remaining' => $debt->amount,
                    ]),
                ]);
            }

            $debtDeductions[] = [
                'debt_id' => $debt->id,
                'debt_type' => $debt->debt_type,
                'amount' => $deductionAmount,
                'original_amount' => $debt->amount,
            ];

            $totalDeduction += $deductionAmount;
        }

        // Recalculate net salary
        $grossSalary = $item->gross_salary;
        $penaltiesTotal = $item->penalties_total;
        $unpaidLeaveTotal = (float) $item->unpaid_leave_total;
        $socialInsuranceDeductionTotal = (float) $item->social_insurance_deduction_total;
        $additionsTotal = (float) $item->additions_total;
        $netSalary = $grossSalary + $additionsTotal - $penaltiesTotal - $unpaidLeaveTotal - $socialInsuranceDeductionTotal - $totalDeduction;

        $item->update([
            'debt_deductions' => $debtDeductions,
            'net_salary' => $netSalary,
        ]);

        return back()->with('success', __('messages.debts.debt_deduction_updated_successfully'));
    }

    /**
     * Remove one breakdown line from a salary run item (draft only) and exclude it from future recalculations.
     */
    public function removeBreakdownLine(Request $request, Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        if ($salaryRun->company_id !== $company->id) {
            abort(403, 'Salary run does not belong to this company.');
        }

        if ($salaryRun->status === 'finalized') {
            return back()->with('error', __('messages.salary_runs.cannot_update_finalized'));
        }

        $validated = $request->validate([
            'salary_run_item_id' => 'required|exists:salary_run_items,id',
            'line_type' => 'required|in:attendance_penalty,employee_deduction,employee_addition,unpaid_leave',
            'line_id' => 'required|integer|min:1',
        ]);

        $item = SalaryRunItem::query()
            ->where('id', $validated['salary_run_item_id'])
            ->where('salary_run_id', $salaryRun->id)
            ->with('employee')
            ->firstOrFail();

        if ($item->employee->company_id !== $company->id) {
            abort(403, 'Employee does not belong to this company.');
        }

        $breakdown = $item->breakdown ?? [];
        $found = false;
        $removedAmount = 0.0;
        $newBreakdown = [];

        foreach ($breakdown as $row) {
            $match = match ($validated['line_type']) {
                'attendance_penalty' => (int) ($row['attendance_penalty_id'] ?? 0) === $validated['line_id'],
                'employee_deduction' => (int) ($row['employee_deduction_id'] ?? 0) === $validated['line_id'],
                'employee_addition' => (int) ($row['employee_addition_id'] ?? 0) === $validated['line_id'],
                'unpaid_leave' => (int) ($row['leave_id'] ?? 0) === $validated['line_id'],
            };

            if ($match) {
                $found = true;
                $removedAmount = (float) ($row['amount'] ?? 0);

                continue;
            }

            $newBreakdown[] = $row;
        }

        if (! $found) {
            return back()->with('error', __('messages.salary_runs.breakdown_line_not_found'));
        }

        $exclusions = $item->breakdown_exclusions ?? [];
        $exclusions[] = [
            'type' => $validated['line_type'],
            'id' => $validated['line_id'],
        ];
        $exclusions = collect($exclusions)
            ->unique(fn (array $e): string => ($e['type'] ?? '').':'.(string) ($e['id'] ?? ''))
            ->values()
            ->all();

        $penaltiesTotal = (float) $item->penalties_total;
        $additionsTotal = (float) $item->additions_total;
        $unpaidLeaveTotal = (float) $item->unpaid_leave_total;

        if ($validated['line_type'] === 'unpaid_leave') {
            $unpaidLeaveTotal = round(max(0, $unpaidLeaveTotal - $removedAmount), 2);
        } elseif ($validated['line_type'] === 'employee_addition') {
            $additionsTotal = round(max(0, $additionsTotal - $removedAmount), 2);
        } else {
            $penaltiesTotal = round(max(0, $penaltiesTotal - $removedAmount), 2);
        }

        $debtDeductions = $item->debt_deductions ?? [];
        $debtTotal = 0.0;
        if (is_array($debtDeductions)) {
            foreach ($debtDeductions as $deduction) {
                $debtTotal += (float) ($deduction['amount'] ?? 0);
            }
        }

        $netSalary = round(
            (float) $item->gross_salary + $additionsTotal - $penaltiesTotal - (float) $item->social_insurance_deduction_total - $unpaidLeaveTotal - $debtTotal,
            2
        );

        $item->update([
            'breakdown' => $newBreakdown,
            'breakdown_exclusions' => $exclusions,
            'penalties_total' => $penaltiesTotal,
            'additions_total' => $additionsTotal,
            'unpaid_leave_total' => $unpaidLeaveTotal,
            'net_salary' => $netSalary,
        ]);

        return back()->with('success', __('messages.salary_runs.breakdown_line_removed'));
    }
}
