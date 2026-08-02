<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSalaryCertificateApprovalSteps;
use App\Models\Company;
use App\Models\SalaryCertificateApprovalStep;
use App\Services\SalaryCertificateApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySalaryCertificateApprovalStepsController extends Controller
{
    use ManagesSalaryCertificateApprovalSteps;

    public function index(Company $company): Response
    {
        $this->abortUnlessCanManageSalaryCertificateApprovalSteps($company);

        return Inertia::render('Companies/SalaryCertificateApprovals', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'steps' => $this->mapSalaryCertificateApprovalStepsForUi($company),
            'teams' => $this->accessibleTeamsForSalaryCertificateApprovalSteps(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->abortUnlessCanManageSalaryCertificateApprovalSteps($company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForSalaryCertificateApprovalStep((int) $validated['team_id']), 403);

        $maxOrder = (int) SalaryCertificateApprovalStep::query()
            ->where('company_id', $company->id)
            ->max('sort_order');

        SalaryCertificateApprovalStep::query()->create([
            'company_id' => $company->id,
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.salary_certificate_approvals_saved'));
    }

    public function update(
        Request $request,
        Company $company,
        SalaryCertificateApprovalStep $salaryCertificateApprovalStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageSalaryCertificateApprovalSteps($company);
        $this->abortUnlessSalaryCertificateStepBelongsToCompany($salaryCertificateApprovalStep, $company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForSalaryCertificateApprovalStep((int) $validated['team_id']), 403);

        $salaryCertificateApprovalStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $salaryCertificateApprovalStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.salary_certificate_approvals_saved'));
    }

    public function destroy(
        Company $company,
        SalaryCertificateApprovalStep $salaryCertificateApprovalStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageSalaryCertificateApprovalSteps($company);
        $this->abortUnlessSalaryCertificateStepBelongsToCompany($salaryCertificateApprovalStep, $company);

        if ($salaryCertificateApprovalStep->hasBlockingWorkflowUsage()) {
            return back()->withErrors([
                'step' => __('messages.settings.salary_certificate_approvals_cannot_delete'),
            ]);
        }

        $salaryCertificateApprovalStep->delete();

        $remaining = SalaryCertificateApprovalStep::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            app(SalaryCertificateApprovalService::class)->reorderStepsForCompany($company->id, $remaining);
        }

        return back()->with('status', __('messages.settings.salary_certificate_approvals_deleted'));
    }

    public function reorder(
        Request $request,
        Company $company,
        SalaryCertificateApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageSalaryCertificateApprovalSteps($company);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:salary_certificate_approval_steps,id'],
        ]);

        $companyStepIds = SalaryCertificateApprovalStep::query()
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

        return back()->with('status', __('messages.settings.salary_certificate_approvals_reordered'));
    }
}
