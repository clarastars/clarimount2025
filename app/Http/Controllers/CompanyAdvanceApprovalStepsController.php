<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesAdvanceApprovalSteps;
use App\Models\AdvanceApprovalStep;
use App\Models\Company;
use App\Services\AdvanceApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyAdvanceApprovalStepsController extends Controller
{
    use ManagesAdvanceApprovalSteps;

    public function index(Company $company): Response
    {
        $this->abortUnlessCanManageAdvanceApprovalSteps($company);

        return Inertia::render('Companies/AdvanceApprovals', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'steps' => $this->mapAdvanceApprovalStepsForUi($company),
            'teams' => $this->accessibleTeamsForAdvanceApprovalSteps(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->abortUnlessCanManageAdvanceApprovalSteps($company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForAdvanceApprovalStep((int) $validated['team_id']), 403);

        $maxOrder = (int) AdvanceApprovalStep::query()
            ->where('company_id', $company->id)
            ->max('sort_order');

        AdvanceApprovalStep::query()->create([
            'company_id' => $company->id,
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.advance_approvals_saved'));
    }

    public function update(
        Request $request,
        Company $company,
        AdvanceApprovalStep $advanceApprovalStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageAdvanceApprovalSteps($company);
        $this->abortUnlessAdvanceStepBelongsToCompany($advanceApprovalStep, $company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForAdvanceApprovalStep((int) $validated['team_id']), 403);

        $advanceApprovalStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $advanceApprovalStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.advance_approvals_saved'));
    }

    public function destroy(
        Company $company,
        AdvanceApprovalStep $advanceApprovalStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageAdvanceApprovalSteps($company);
        $this->abortUnlessAdvanceStepBelongsToCompany($advanceApprovalStep, $company);

        if ($advanceApprovalStep->hasBlockingWorkflowUsage()) {
            return back()->withErrors([
                'step' => __('messages.settings.advance_approvals_cannot_delete'),
            ]);
        }

        $advanceApprovalStep->delete();

        $remaining = AdvanceApprovalStep::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            app(AdvanceApprovalService::class)->reorderStepsForCompany($company->id, $remaining);
        }

        return back()->with('status', __('messages.settings.advance_approvals_deleted'));
    }

    public function reorder(
        Request $request,
        Company $company,
        AdvanceApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageAdvanceApprovalSteps($company);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:advance_approval_steps,id'],
        ]);

        $companyStepIds = AdvanceApprovalStep::query()
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

        return back()->with('status', __('messages.settings.advance_approvals_reordered'));
    }
}
