<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesEntitlementSettlementApprovalSteps;
use App\Models\Company;
use App\Models\EntitlementSettlementApprovalStep;
use App\Services\EntitlementSettlementApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyEntitlementSettlementApprovalStepsController extends Controller
{
    use ManagesEntitlementSettlementApprovalSteps;

    public function index(Company $company): Response
    {
        $this->abortUnlessCanManageEntitlementSettlementApprovalSteps($company);

        return Inertia::render('Companies/EntitlementSettlementApprovals', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'steps' => $this->mapEntitlementSettlementApprovalStepsForUi($company),
            'teams' => $this->accessibleTeamsForEntitlementSettlementApprovalSteps(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->abortUnlessCanManageEntitlementSettlementApprovalSteps($company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForEntitlementSettlementApprovalStep((int) $validated['team_id']), 403);

        $maxOrder = (int) EntitlementSettlementApprovalStep::query()
            ->where('company_id', $company->id)
            ->max('sort_order');

        EntitlementSettlementApprovalStep::query()->create([
            'company_id' => $company->id,
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.entitlement_settlement_approvals_saved'));
    }

    public function update(
        Request $request,
        Company $company,
        EntitlementSettlementApprovalStep $settlementApprovalStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageEntitlementSettlementApprovalSteps($company);
        $this->abortUnlessEntitlementSettlementStepBelongsToCompany($settlementApprovalStep, $company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForEntitlementSettlementApprovalStep((int) $validated['team_id']), 403);

        $settlementApprovalStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $settlementApprovalStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.entitlement_settlement_approvals_saved'));
    }

    public function destroy(
        Company $company,
        EntitlementSettlementApprovalStep $settlementApprovalStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageEntitlementSettlementApprovalSteps($company);
        $this->abortUnlessEntitlementSettlementStepBelongsToCompany($settlementApprovalStep, $company);

        if ($settlementApprovalStep->hasBlockingWorkflowUsage()) {
            return back()->withErrors([
                'step' => __('messages.settings.entitlement_settlement_approvals_cannot_delete'),
            ]);
        }

        $settlementApprovalStep->delete();

        $remaining = EntitlementSettlementApprovalStep::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            app(EntitlementSettlementApprovalService::class)->reorderStepsForCompany($company->id, $remaining);
        }

        return back()->with('status', __('messages.settings.entitlement_settlement_approvals_deleted'));
    }

    public function reorder(
        Request $request,
        Company $company,
        EntitlementSettlementApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageEntitlementSettlementApprovalSteps($company);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:entitlement_settlement_approval_steps,id'],
        ]);

        $companyStepIds = EntitlementSettlementApprovalStep::query()
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

        return back()->with('status', __('messages.settings.entitlement_settlement_approvals_reordered'));
    }
}
