<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\ManagesOffboardingSettings;
use App\Http\Controllers\Controller;
use App\Models\OffboardingDefaultChecklistItem;
use App\Models\OffboardingDefaultClearanceApprovalStep;
use App\Models\OffboardingDefaultItemApprovalStep;
use App\Services\OffboardingDefaultsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OffboardingSettingsController extends Controller
{
    use ManagesOffboardingSettings;

    public function index(): Response
    {
        $this->abortUnlessCanManageOffboardingDefaults();

        $companies = $this->manageableCompaniesForOffboardingSettings()
            ->map(fn ($company) => [
                'id' => $company->id,
                'name_en' => $company->name_en,
                'name_ar' => $company->name_ar,
            ])
            ->values();

        return Inertia::render('settings/OffboardingCompanyPicker', [
            'companies' => $companies,
            'defaultChecklist' => $this->mapOffboardingDefaultChecklistForUi(),
            'defaultClearanceSteps' => $this->mapOffboardingDefaultClearanceStepsForUi(),
            'teams' => $this->accessibleTeamsForOffboardingSettings(),
            'status' => session('status'),
        ]);
    }

    public function storeChecklistItem(Request $request): RedirectResponse
    {
        $this->abortUnlessCanManageOffboardingDefaults();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'attachment_mode' => ['required', 'in:none,optional,required'],
        ]);

        $maxOrder = (int) OffboardingDefaultChecklistItem::query()->max('sort_order');

        OffboardingDefaultChecklistItem::query()->create([
            'title' => $validated['title'],
            'attachment_mode' => $validated['attachment_mode'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.offboarding_defaults_checklist_saved'));
    }

    public function updateChecklistItem(
        Request $request,
        OffboardingDefaultChecklistItem $offboardingDefaultChecklistItem,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'attachment_mode' => ['required', 'in:none,optional,required'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $offboardingDefaultChecklistItem->update([
            'title' => $validated['title'],
            'attachment_mode' => $validated['attachment_mode'],
            'is_active' => $validated['is_active'] ?? $offboardingDefaultChecklistItem->is_active,
        ]);

        return back()->with('status', __('messages.settings.offboarding_defaults_checklist_saved'));
    }

    public function destroyChecklistItem(
        OffboardingDefaultChecklistItem $offboardingDefaultChecklistItem,
        OffboardingDefaultsService $defaultsService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();

        $offboardingDefaultChecklistItem->delete();

        $remaining = OffboardingDefaultChecklistItem::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            $defaultsService->reorderDefaultChecklistItems($remaining);
        }

        return back()->with('status', __('messages.settings.offboarding_defaults_checklist_deleted'));
    }

    public function reorderChecklistItems(
        Request $request,
        OffboardingDefaultsService $defaultsService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:offboarding_default_checklist_items,id'],
        ]);

        $existingIds = OffboardingDefaultChecklistItem::query()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['ordered_ids'] as $id) {
            if (! in_array((int) $id, $existingIds, true)) {
                abort(403);
            }
        }

        $defaultsService->reorderDefaultChecklistItems($validated['ordered_ids']);

        return back()->with('status', __('messages.settings.offboarding_defaults_checklist_reordered'));
    }

    public function itemApprovalsIndex(OffboardingDefaultChecklistItem $offboardingDefaultChecklistItem): Response
    {
        $this->abortUnlessCanManageOffboardingDefaults();

        return Inertia::render('settings/OffboardingDefaultItemApprovals', [
            'item' => [
                'id' => $offboardingDefaultChecklistItem->id,
                'title' => $offboardingDefaultChecklistItem->title,
            ],
            'steps' => $this->mapOffboardingDefaultItemStepsForUi($offboardingDefaultChecklistItem),
            'teams' => $this->accessibleTeamsForOffboardingSettings(),
            'status' => session('status'),
        ]);
    }

    public function storeItemStep(
        Request $request,
        OffboardingDefaultChecklistItem $offboardingDefaultChecklistItem,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForOffboarding((int) $validated['team_id']), 403);

        $maxOrder = (int) OffboardingDefaultItemApprovalStep::query()
            ->where('default_checklist_item_id', $offboardingDefaultChecklistItem->id)
            ->max('sort_order');

        OffboardingDefaultItemApprovalStep::query()->create([
            'default_checklist_item_id' => $offboardingDefaultChecklistItem->id,
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.offboarding_defaults_item_steps_saved'));
    }

    public function updateItemStep(
        Request $request,
        OffboardingDefaultChecklistItem $offboardingDefaultChecklistItem,
        OffboardingDefaultItemApprovalStep $offboardingDefaultItemStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();
        abort_unless(
            (int) $offboardingDefaultItemStep->default_checklist_item_id === (int) $offboardingDefaultChecklistItem->id,
            404
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForOffboarding((int) $validated['team_id']), 403);

        $offboardingDefaultItemStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $offboardingDefaultItemStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.offboarding_defaults_item_steps_saved'));
    }

    public function destroyItemStep(
        OffboardingDefaultChecklistItem $offboardingDefaultChecklistItem,
        OffboardingDefaultItemApprovalStep $offboardingDefaultItemStep,
        OffboardingDefaultsService $defaultsService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();
        abort_unless(
            (int) $offboardingDefaultItemStep->default_checklist_item_id === (int) $offboardingDefaultChecklistItem->id,
            404
        );

        $offboardingDefaultItemStep->delete();

        $remaining = OffboardingDefaultItemApprovalStep::query()
            ->where('default_checklist_item_id', $offboardingDefaultChecklistItem->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            $defaultsService->reorderDefaultItemSteps((int) $offboardingDefaultChecklistItem->id, $remaining);
        }

        return back()->with('status', __('messages.settings.offboarding_defaults_item_steps_deleted'));
    }

    public function reorderItemSteps(
        Request $request,
        OffboardingDefaultChecklistItem $offboardingDefaultChecklistItem,
        OffboardingDefaultsService $defaultsService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:offboarding_default_item_approval_steps,id'],
        ]);

        $itemStepIds = OffboardingDefaultItemApprovalStep::query()
            ->where('default_checklist_item_id', $offboardingDefaultChecklistItem->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['ordered_ids'] as $stepId) {
            if (! in_array((int) $stepId, $itemStepIds, true)) {
                abort(403);
            }
        }

        $defaultsService->reorderDefaultItemSteps(
            (int) $offboardingDefaultChecklistItem->id,
            $validated['ordered_ids'],
        );

        return back()->with('status', __('messages.settings.offboarding_defaults_item_steps_reordered'));
    }

    public function storeClearanceStep(Request $request): RedirectResponse
    {
        $this->abortUnlessCanManageOffboardingDefaults();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForOffboarding((int) $validated['team_id']), 403);

        $maxOrder = (int) OffboardingDefaultClearanceApprovalStep::query()->max('sort_order');

        OffboardingDefaultClearanceApprovalStep::query()->create([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.offboarding_defaults_clearance_saved'));
    }

    public function updateClearanceStep(
        Request $request,
        OffboardingDefaultClearanceApprovalStep $offboardingDefaultClearanceStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForOffboarding((int) $validated['team_id']), 403);

        $offboardingDefaultClearanceStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $offboardingDefaultClearanceStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.offboarding_defaults_clearance_saved'));
    }

    public function destroyClearanceStep(
        OffboardingDefaultClearanceApprovalStep $offboardingDefaultClearanceStep,
        OffboardingDefaultsService $defaultsService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();

        $offboardingDefaultClearanceStep->delete();

        $remaining = OffboardingDefaultClearanceApprovalStep::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            $defaultsService->reorderDefaultClearanceSteps($remaining);
        }

        return back()->with('status', __('messages.settings.offboarding_defaults_clearance_deleted'));
    }

    public function reorderClearanceSteps(
        Request $request,
        OffboardingDefaultsService $defaultsService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingDefaults();

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:offboarding_default_clearance_approval_steps,id'],
        ]);

        $existingIds = OffboardingDefaultClearanceApprovalStep::query()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['ordered_ids'] as $stepId) {
            if (! in_array((int) $stepId, $existingIds, true)) {
                abort(403);
            }
        }

        $defaultsService->reorderDefaultClearanceSteps($validated['ordered_ids']);

        return back()->with('status', __('messages.settings.offboarding_defaults_clearance_reordered'));
    }

    public function applyDefaultsToAll(OffboardingDefaultsService $defaultsService): RedirectResponse
    {
        $this->abortUnlessCanManageOffboardingDefaults();

        if (! $defaultsService->hasAnyDefaultsConfigured()) {
            return back()->withErrors([
                'defaults' => __('messages.settings.offboarding_defaults_empty_apply'),
            ]);
        }

        $result = $defaultsService->applyDefaultsToAllCompanies();

        return back()->with(
            'status',
            __('messages.settings.offboarding_defaults_applied', [
                'applied' => $result['applied'],
                'skipped' => $result['skipped'],
            ])
        );
    }
}
