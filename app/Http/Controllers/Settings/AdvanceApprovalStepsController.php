<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\ManagesAdvanceApprovalSteps;
use App\Http\Controllers\Controller;
use App\Models\AdvanceApprovalDefaultStep;
use App\Services\AdvanceApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvanceApprovalStepsController extends Controller
{
    use ManagesAdvanceApprovalSteps;

    public function index(): Response
    {
        $this->abortUnlessCanManageAdvanceApprovalDefaults();

        $companies = $this->manageableCompaniesForAdvanceApprovalSteps()
            ->map(fn ($company) => [
                'id' => $company->id,
                'name_en' => $company->name_en,
                'name_ar' => $company->name_ar,
            ])
            ->values();

        return Inertia::render('settings/AdvanceApprovalCompanyPicker', [
            'companies' => $companies,
            'defaultSteps' => $this->mapAdvanceApprovalDefaultStepsForUi(),
            'teams' => $this->accessibleTeamsForAdvanceApprovalSteps(),
            'status' => session('status'),
        ]);
    }

    public function storeDefault(Request $request): RedirectResponse
    {
        $this->abortUnlessCanManageAdvanceApprovalDefaults();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForAdvanceApprovalStep((int) $validated['team_id']), 403);

        $maxOrder = (int) AdvanceApprovalDefaultStep::query()->max('sort_order');

        AdvanceApprovalDefaultStep::query()->create([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.advance_approvals_defaults_saved'));
    }

    public function updateDefault(
        Request $request,
        AdvanceApprovalDefaultStep $advanceApprovalDefaultStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageAdvanceApprovalDefaults();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForAdvanceApprovalStep((int) $validated['team_id']), 403);

        $advanceApprovalDefaultStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $advanceApprovalDefaultStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.advance_approvals_defaults_saved'));
    }

    public function destroyDefault(AdvanceApprovalDefaultStep $advanceApprovalDefaultStep): RedirectResponse
    {
        $this->abortUnlessCanManageAdvanceApprovalDefaults();

        $advanceApprovalDefaultStep->delete();

        $remaining = AdvanceApprovalDefaultStep::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            app(AdvanceApprovalService::class)->reorderDefaultSteps($remaining);
        }

        return back()->with('status', __('messages.settings.advance_approvals_defaults_deleted'));
    }

    public function reorderDefaults(
        Request $request,
        AdvanceApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageAdvanceApprovalDefaults();

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:advance_approval_default_steps,id'],
        ]);

        $existingIds = AdvanceApprovalDefaultStep::query()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['ordered_ids'] as $stepId) {
            if (! in_array((int) $stepId, $existingIds, true)) {
                abort(403);
            }
        }

        $approvalService->reorderDefaultSteps($validated['ordered_ids']);

        return back()->with('status', __('messages.settings.advance_approvals_defaults_reordered'));
    }

    public function applyDefaultsToAll(AdvanceApprovalService $approvalService): RedirectResponse
    {
        $this->abortUnlessCanManageAdvanceApprovalDefaults();

        if ($approvalService->activeDefaultSteps()->isEmpty()) {
            return back()->withErrors([
                'defaults' => __('messages.settings.advance_approvals_defaults_empty_apply'),
            ]);
        }

        $result = $approvalService->applyDefaultsToAllCompanies();

        return back()->with(
            'status',
            __('messages.settings.advance_approvals_defaults_applied', [
                'applied' => $result['applied'],
                'skipped' => $result['skipped'],
            ])
        );
    }
}
