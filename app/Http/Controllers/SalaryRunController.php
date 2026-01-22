<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SalaryRun;
use App\Services\SalaryRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->with(['items.employee', 'creator'])
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

        $salaryRun->update([
            'status' => 'finalized',
        ]);

        return back()->with('success', __('Salary run finalized successfully.'));
    }
}
