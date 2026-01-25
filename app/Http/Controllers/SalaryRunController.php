<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\EmployeeDebt;
use App\Models\SalaryRun;
use App\Models\SalaryRunItem;
use App\Services\SalaryRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalaryRunController extends Controller
{
    public function __construct(
        private SalaryRunService $salaryRunService
    ) {}

    /**
     * Display a listing of salary runs for a company
     */
    public function index(Request $request, Company $company): Response
    {
        $user = Auth::user();

        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
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
        ]);
    }

    /**
     * Display a specific salary run
     */
    public function show(Company $company, int $year, int $month): Response
    {
        $user = Auth::user();

        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        $salaryRun = SalaryRun::where('company_id', $company->id)
            ->where('year', $year)
            ->where('month', $month)
            ->with(['items.employee.debts', 'creator'])
            ->firstOrFail();

        return Inertia::render('SalaryRuns/Show', [
            'company' => $company,
            'salaryRun' => $salaryRun,
        ]);
    }

    /**
     * Create or update a salary run
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $user = Auth::user();

        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
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
     * Finalize a salary run
     */
    public function finalize(Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        $user = Auth::user();

        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
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

    /**
     * Update debt deductions for a salary run item
     */
    public function updateDebtDeductions(Request $request, Company $company, SalaryRun $salaryRun): RedirectResponse
    {
        $user = Auth::user();

        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
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

            if (!$debt || $debt->employee_id !== $validated['employee_id']) {
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
        $netSalary = $grossSalary - $penaltiesTotal - $totalDeduction;

        $item->update([
            'debt_deductions' => $debtDeductions,
            'net_salary' => $netSalary,
        ]);

        return back()->with('success', __('messages.debts.debt_deduction_updated_successfully'));
    }
}
