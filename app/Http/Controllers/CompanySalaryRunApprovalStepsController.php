<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSalaryRunApprovalSteps;
use App\Models\Company;
use App\Models\SalaryRunApprovalStep;
use App\Services\SalaryRunApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CompanySalaryRunApprovalStepsController extends Controller
{
    use ManagesSalaryRunApprovalSteps;

    public function index(Company $company): Response
    {
        $this->abortUnlessCanManageSalaryRunApprovalSteps($company);

        return Inertia::render('Companies/SalaryRunApprovals', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'steps' => $this->mapApprovalStepsForUi($company),
            'teams' => $this->accessibleTeamsForApprovalSteps(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->abortUnlessCanManageSalaryRunApprovalSteps($company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForApprovalStep((int) $validated['team_id']), 403);

        $maxOrder = (int) SalaryRunApprovalStep::query()
            ->where('company_id', $company->id)
            ->max('sort_order');

        SalaryRunApprovalStep::query()->create([
            'company_id' => $company->id,
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.salary_run_approvals_saved'));
    }

    public function update(Request $request, Company $company, SalaryRunApprovalStep $salaryRunApprovalStep): RedirectResponse
    {
        $this->abortUnlessCanManageSalaryRunApprovalSteps($company);
        $this->abortUnlessStepBelongsToCompany($salaryRunApprovalStep, $company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForApprovalStep((int) $validated['team_id']), 403);

        $salaryRunApprovalStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $salaryRunApprovalStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.salary_run_approvals_saved'));
    }

    public function destroy(Company $company, SalaryRunApprovalStep $salaryRunApprovalStep): RedirectResponse
    {
        $this->abortUnlessCanManageSalaryRunApprovalSteps($company);
        $this->abortUnlessStepBelongsToCompany($salaryRunApprovalStep, $company);

        if ($salaryRunApprovalStep->stepApprovals()->exists()) {
            return back()->withErrors([
                'step' => __('messages.settings.salary_run_approvals_cannot_delete'),
            ]);
        }

        $salaryRunApprovalStep->delete();

        $remaining = SalaryRunApprovalStep::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            app(SalaryRunApprovalService::class)->reorderStepsForCompany($company->id, $remaining);
        }

        return back()->with('status', __('messages.settings.salary_run_approvals_deleted'));
    }

    public function reorder(Request $request, Company $company, SalaryRunApprovalService $approvalService): RedirectResponse
    {
        $this->abortUnlessCanManageSalaryRunApprovalSteps($company);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:salary_run_approval_steps,id'],
        ]);

        $companyStepIds = SalaryRunApprovalStep::query()
            ->where('company_id', $company->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['ordered_ids'] as $stepId) {
            if (! in_array((int) $stepId, $companyStepIds, true)) {
                abort(403);
            }
        }

        $approvalService->reorderStepsForCompany($company->id, $validated['ordered_ids']);

        return back()->with('status', __('messages.settings.salary_run_approvals_reordered'));
    }
}
