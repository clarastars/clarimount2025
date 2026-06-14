<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesLeaveApprovalSteps;
use App\Models\Company;
use App\Models\LeaveApprovalStep;
use App\Services\LeaveApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyLeaveApprovalStepsController extends Controller
{
    use ManagesLeaveApprovalSteps;

    public function index(Company $company): Response
    {
        $this->abortUnlessCanManageLeaveApprovalSteps($company);

        return Inertia::render('Companies/LeaveApprovals', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'steps' => $this->mapLeaveApprovalStepsForUi($company),
            'teams' => $this->accessibleTeamsForLeaveApprovalSteps(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->abortUnlessCanManageLeaveApprovalSteps($company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForLeaveApprovalStep((int) $validated['team_id']), 403);

        $maxOrder = (int) LeaveApprovalStep::query()
            ->where('company_id', $company->id)
            ->max('sort_order');

        LeaveApprovalStep::query()->create([
            'company_id' => $company->id,
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.leave_approvals_saved'));
    }

    public function update(Request $request, Company $company, LeaveApprovalStep $leaveApprovalStep): RedirectResponse
    {
        $this->abortUnlessCanManageLeaveApprovalSteps($company);
        $this->abortUnlessLeaveStepBelongsToCompany($leaveApprovalStep, $company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForLeaveApprovalStep((int) $validated['team_id']), 403);

        $leaveApprovalStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $leaveApprovalStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.leave_approvals_saved'));
    }

    public function destroy(Company $company, LeaveApprovalStep $leaveApprovalStep): RedirectResponse
    {
        $this->abortUnlessCanManageLeaveApprovalSteps($company);
        $this->abortUnlessLeaveStepBelongsToCompany($leaveApprovalStep, $company);

        if ($leaveApprovalStep->hasBlockingWorkflowUsage()) {
            return back()->withErrors([
                'step' => __('messages.settings.leave_approvals_cannot_delete'),
            ]);
        }

        $leaveApprovalStep->delete();

        $remaining = LeaveApprovalStep::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            app(LeaveApprovalService::class)->reorderStepsForCompany($company->id, $remaining);
        }

        return back()->with('status', __('messages.settings.leave_approvals_deleted'));
    }

    public function reorder(Request $request, Company $company, LeaveApprovalService $approvalService): RedirectResponse
    {
        $this->abortUnlessCanManageLeaveApprovalSteps($company);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:leave_approval_steps,id'],
        ]);

        $companyStepIds = LeaveApprovalStep::query()
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

        return back()->with('status', __('messages.settings.leave_approvals_reordered'));
    }
}
