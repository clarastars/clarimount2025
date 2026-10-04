<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesOffboardingSettings;
use App\Models\Company;
use App\Models\OffboardingClearanceApprovalStep;
use App\Services\OffboardingClearanceApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyOffboardingClearanceApprovalStepsController extends Controller
{
    use ManagesOffboardingSettings;

    public function index(Company $company): Response
    {
        $this->abortUnlessCanManageOffboardingSettings($company);

        return Inertia::render('Companies/OffboardingClearanceApprovals', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'steps' => $this->mapOffboardingClearanceStepsForUi($company),
            'teams' => $this->accessibleTeamsForOffboardingSettings(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->abortUnlessCanManageOffboardingSettings($company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForOffboarding((int) $validated['team_id']), 403);

        $maxOrder = (int) OffboardingClearanceApprovalStep::query()
            ->where('company_id', $company->id)
            ->max('sort_order');

        OffboardingClearanceApprovalStep::query()->create([
            'company_id' => $company->id,
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.offboarding_clearance_approvals_saved'));
    }

    public function update(
        Request $request,
        Company $company,
        OffboardingClearanceApprovalStep $offboardingClearanceStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingSettings($company);
        abort_unless((int) $offboardingClearanceStep->company_id === (int) $company->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForOffboarding((int) $validated['team_id']), 403);

        $offboardingClearanceStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $offboardingClearanceStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.offboarding_clearance_approvals_saved'));
    }

    public function destroy(
        Company $company,
        OffboardingClearanceApprovalStep $offboardingClearanceStep,
        OffboardingClearanceApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingSettings($company);
        abort_unless((int) $offboardingClearanceStep->company_id === (int) $company->id, 404);

        if ($offboardingClearanceStep->hasBlockingWorkflowUsage()) {
            return back()->withErrors([
                'step' => __('messages.settings.offboarding_clearance_approvals_cannot_delete'),
            ]);
        }

        $offboardingClearanceStep->delete();

        $remaining = OffboardingClearanceApprovalStep::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            $approvalService->reorderStepsForCompany($company->id, $remaining);
        }

        return back()->with('status', __('messages.settings.offboarding_clearance_approvals_deleted'));
    }

    public function reorder(
        Request $request,
        Company $company,
        OffboardingClearanceApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingSettings($company);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:offboarding_clearance_approval_steps,id'],
        ]);

        $companyStepIds = OffboardingClearanceApprovalStep::query()
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

        return back()->with('status', __('messages.settings.offboarding_clearance_approvals_reordered'));
    }
}
