<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\ManagesEntitlementSettlementApprovalSteps;
use App\Http\Controllers\Controller;
use App\Models\EntitlementSettlementApprovalDefaultStep;
use App\Models\Team;
use App\Services\EntitlementSettlementApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntitlementSettlementApprovalStepsController extends Controller
{
    use ManagesEntitlementSettlementApprovalSteps;

    public function index(EntitlementSettlementApprovalService $approvalService): Response
    {
        $this->abortUnlessCanManageEntitlementSettlementApprovalDefaults();

        $companies = $this->manageableCompaniesForEntitlementSettlementApprovalSteps()
            ->map(fn ($company) => [
                'id' => $company->id,
                'name_en' => $company->name_en,
                'name_ar' => $company->name_ar,
            ])
            ->values();

        $transferTeamId = $approvalService->defaultTransferTeamId();
        $transferTeam = $transferTeamId !== null
            ? Team::query()->find($transferTeamId)
            : null;

        return Inertia::render('settings/EntitlementSettlementApprovalCompanyPicker', [
            'companies' => $companies,
            'defaultSteps' => $this->mapEntitlementSettlementApprovalDefaultStepsForUi(),
            'teams' => $this->accessibleTeamsForEntitlementSettlementApprovalSteps(),
            'transferTeam' => [
                'team_id' => $transferTeamId,
                'team_name' => $transferTeam?->name,
            ],
            'status' => session('status'),
        ]);
    }

    public function storeDefault(Request $request): RedirectResponse
    {
        $this->abortUnlessCanManageEntitlementSettlementApprovalDefaults();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForEntitlementSettlementApprovalStep((int) $validated['team_id']), 403);

        $maxOrder = (int) EntitlementSettlementApprovalDefaultStep::query()->max('sort_order');

        EntitlementSettlementApprovalDefaultStep::query()->create([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.entitlement_settlement_approvals_defaults_saved'));
    }

    public function updateDefault(
        Request $request,
        EntitlementSettlementApprovalDefaultStep $entitlementSettlementApprovalDefaultStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageEntitlementSettlementApprovalDefaults();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForEntitlementSettlementApprovalStep((int) $validated['team_id']), 403);

        $entitlementSettlementApprovalDefaultStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $entitlementSettlementApprovalDefaultStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.entitlement_settlement_approvals_defaults_saved'));
    }

    public function destroyDefault(
        EntitlementSettlementApprovalDefaultStep $entitlementSettlementApprovalDefaultStep,
        EntitlementSettlementApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageEntitlementSettlementApprovalDefaults();

        $entitlementSettlementApprovalDefaultStep->delete();

        $remaining = EntitlementSettlementApprovalDefaultStep::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            $approvalService->reorderDefaultSteps($remaining);
        }

        return back()->with('status', __('messages.settings.entitlement_settlement_approvals_defaults_deleted'));
    }

    public function reorderDefaults(
        Request $request,
        EntitlementSettlementApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageEntitlementSettlementApprovalDefaults();

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:entitlement_settlement_approval_default_steps,id'],
        ]);

        $existingIds = EntitlementSettlementApprovalDefaultStep::query()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['ordered_ids'] as $stepId) {
            if (! in_array((int) $stepId, $existingIds, true)) {
                abort(403);
            }
        }

        $approvalService->reorderDefaultSteps($validated['ordered_ids']);

        return back()->with('status', __('messages.settings.entitlement_settlement_approvals_defaults_reordered'));
    }

    public function applyDefaultsToAll(EntitlementSettlementApprovalService $approvalService): RedirectResponse
    {
        $this->abortUnlessCanManageEntitlementSettlementApprovalDefaults();

        if ($approvalService->activeDefaultSteps()->isEmpty()) {
            return back()->withErrors([
                'defaults' => __('messages.settings.entitlement_settlement_approvals_defaults_empty_apply'),
            ]);
        }

        $result = $approvalService->applyDefaultsToAllCompanies();

        return back()->with(
            'status',
            __('messages.settings.entitlement_settlement_approvals_defaults_applied', [
                'applied' => $result['applied'],
                'skipped' => $result['skipped'],
            ])
        );
    }

    public function updateDefaultTransferTeam(
        Request $request,
        EntitlementSettlementApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageEntitlementSettlementApprovalDefaults();

        $validated = $request->validate([
            'team_id' => ['nullable', 'exists:teams,id'],
        ]);

        $teamId = isset($validated['team_id']) && $validated['team_id'] !== ''
            ? (int) $validated['team_id']
            : null;

        if ($teamId !== null) {
            abort_unless($this->userCanUseTeamForEntitlementSettlementApprovalStep($teamId), 403);
        }

        $approvalService->updateDefaultTransferTeam($teamId);

        return back()->with('status', __('messages.settings.entitlement_settlement_transfer_team_saved'));
    }
}
