<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesOffboardingSettings;
use App\Models\Company;
use App\Models\OffboardingChecklistItemTemplate;
use App\Models\OffboardingItemApprovalStep;
use App\Services\OffboardingItemApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyOffboardingChecklistController extends Controller
{
    use ManagesOffboardingSettings;

    public function index(Company $company): Response
    {
        $this->abortUnlessCanManageOffboardingSettings($company);

        return Inertia::render('Companies/OffboardingChecklist', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'templates' => $this->mapOffboardingTemplatesForUi($company),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->abortUnlessCanManageOffboardingSettings($company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'attachment_mode' => ['required', 'in:none,optional,required'],
        ]);

        $maxOrder = (int) OffboardingChecklistItemTemplate::query()
            ->where('company_id', $company->id)
            ->max('sort_order');

        OffboardingChecklistItemTemplate::query()->create([
            'company_id' => $company->id,
            'title' => $validated['title'],
            'attachment_mode' => $validated['attachment_mode'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.offboarding_checklist_saved'));
    }

    public function update(
        Request $request,
        Company $company,
        OffboardingChecklistItemTemplate $offboardingTemplate,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingSettings($company);
        abort_unless((int) $offboardingTemplate->company_id === (int) $company->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'attachment_mode' => ['required', 'in:none,optional,required'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $offboardingTemplate->update([
            'title' => $validated['title'],
            'attachment_mode' => $validated['attachment_mode'],
            'is_active' => $validated['is_active'] ?? $offboardingTemplate->is_active,
        ]);

        return back()->with('status', __('messages.settings.offboarding_checklist_saved'));
    }

    public function destroy(
        Company $company,
        OffboardingChecklistItemTemplate $offboardingTemplate,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingSettings($company);
        abort_unless((int) $offboardingTemplate->company_id === (int) $company->id, 404);

        $inUse = $offboardingTemplate->instanceItems()
            ->whereHas('offboardingCase', function ($q) {
                $q->whereIn('status', ['in_progress', 'pending_clearance']);
            })
            ->exists();

        if ($inUse) {
            return back()->withErrors([
                'template' => __('messages.settings.offboarding_checklist_cannot_delete'),
            ]);
        }

        $offboardingTemplate->delete();

        $remaining = OffboardingChecklistItemTemplate::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach (array_values($remaining) as $index => $id) {
            OffboardingChecklistItemTemplate::query()
                ->whereKey($id)
                ->update(['sort_order' => $index + 1]);
        }

        return back()->with('status', __('messages.settings.offboarding_checklist_deleted'));
    }

    public function reorder(Request $request, Company $company): RedirectResponse
    {
        $this->abortUnlessCanManageOffboardingSettings($company);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:offboarding_checklist_item_templates,id'],
        ]);

        $companyIds = OffboardingChecklistItemTemplate::query()
            ->where('company_id', $company->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['ordered_ids'] as $id) {
            if (! in_array((int) $id, $companyIds, true)) {
                abort(403);
            }
        }

        foreach (array_values($validated['ordered_ids']) as $index => $id) {
            OffboardingChecklistItemTemplate::query()
                ->where('company_id', $company->id)
                ->whereKey($id)
                ->update(['sort_order' => $index + 1]);
        }

        return back()->with('status', __('messages.settings.offboarding_checklist_reordered'));
    }

    public function stepsIndex(
        Company $company,
        OffboardingChecklistItemTemplate $offboardingTemplate,
    ): Response {
        $this->abortUnlessCanManageOffboardingSettings($company);
        abort_unless((int) $offboardingTemplate->company_id === (int) $company->id, 404);

        return Inertia::render('Companies/OffboardingItemApprovals', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'template' => [
                'id' => $offboardingTemplate->id,
                'title' => $offboardingTemplate->title,
            ],
            'steps' => $this->mapOffboardingItemStepsForUi($offboardingTemplate),
            'teams' => $this->accessibleTeamsForOffboardingSettings(),
            'status' => session('status'),
        ]);
    }

    public function stepsStore(
        Request $request,
        Company $company,
        OffboardingChecklistItemTemplate $offboardingTemplate,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingSettings($company);
        abort_unless((int) $offboardingTemplate->company_id === (int) $company->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeamForOffboarding((int) $validated['team_id']), 403);

        $maxOrder = (int) OffboardingItemApprovalStep::query()
            ->where('template_id', $offboardingTemplate->id)
            ->max('sort_order');

        OffboardingItemApprovalStep::query()->create([
            'company_id' => $company->id,
            'template_id' => $offboardingTemplate->id,
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.offboarding_item_approvals_saved'));
    }

    public function stepsUpdate(
        Request $request,
        Company $company,
        OffboardingChecklistItemTemplate $offboardingTemplate,
        OffboardingItemApprovalStep $offboardingItemStep,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingSettings($company);
        abort_unless((int) $offboardingTemplate->company_id === (int) $company->id, 404);
        abort_unless((int) $offboardingItemStep->template_id === (int) $offboardingTemplate->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeamForOffboarding((int) $validated['team_id']), 403);

        $offboardingItemStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $offboardingItemStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.offboarding_item_approvals_saved'));
    }

    public function stepsDestroy(
        Company $company,
        OffboardingChecklistItemTemplate $offboardingTemplate,
        OffboardingItemApprovalStep $offboardingItemStep,
        OffboardingItemApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingSettings($company);
        abort_unless((int) $offboardingTemplate->company_id === (int) $company->id, 404);
        abort_unless((int) $offboardingItemStep->template_id === (int) $offboardingTemplate->id, 404);

        if ($offboardingItemStep->hasBlockingWorkflowUsage()) {
            return back()->withErrors([
                'step' => __('messages.settings.offboarding_item_approvals_cannot_delete'),
            ]);
        }

        $offboardingItemStep->delete();

        $remaining = OffboardingItemApprovalStep::query()
            ->where('template_id', $offboardingTemplate->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            $approvalService->reorderStepsForTemplate((int) $offboardingTemplate->id, $remaining);
        }

        return back()->with('status', __('messages.settings.offboarding_item_approvals_deleted'));
    }

    public function stepsReorder(
        Request $request,
        Company $company,
        OffboardingChecklistItemTemplate $offboardingTemplate,
        OffboardingItemApprovalService $approvalService,
    ): RedirectResponse {
        $this->abortUnlessCanManageOffboardingSettings($company);
        abort_unless((int) $offboardingTemplate->company_id === (int) $company->id, 404);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:offboarding_item_approval_steps,id'],
        ]);

        $templateStepIds = OffboardingItemApprovalStep::query()
            ->where('template_id', $offboardingTemplate->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['ordered_ids'] as $stepId) {
            if (! in_array((int) $stepId, $templateStepIds, true)) {
                abort(403);
            }
        }

        $approvalService->reorderStepsForTemplate((int) $offboardingTemplate->id, $validated['ordered_ids']);

        return back()->with('status', __('messages.settings.offboarding_item_approvals_reordered'));
    }
}
