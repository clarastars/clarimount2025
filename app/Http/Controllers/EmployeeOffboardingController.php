<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Employee;
use App\Models\EmployeeOffboardingCase;
use App\Models\EmployeeOffboardingItem;
use App\Models\OffboardingClearanceApprovalStep;
use App\Models\OffboardingItemApprovalStep;
use App\Services\OffboardingApprovalNotificationService;
use App\Services\OffboardingAttachmentService;
use App\Services\OffboardingCaseService;
use App\Services\OffboardingClearanceApprovalService;
use App\Services\OffboardingItemApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeOffboardingController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private OffboardingCaseService $caseService,
        private OffboardingItemApprovalService $itemApprovalService,
        private OffboardingClearanceApprovalService $clearanceApprovalService,
        private OffboardingApprovalNotificationService $notificationService,
        private OffboardingAttachmentService $attachmentService,
    ) {}

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $this->abortUnlessCanStartEmployeeOffboarding($user, $employee);

        $validated = $request->validate([
            'termination_date' => ['nullable', 'date'],
        ]);

        $case = $this->caseService->start(
            $employee,
            $user,
            $validated['termination_date'] ?? null,
        );

        return redirect()
            ->route('employees.offboarding.show', [$employee, $case])
            ->with('status', __('messages.offboarding.started'));
    }

    public function show(Employee $employee, EmployeeOffboardingCase $offboardingCase): Response
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless((int) $offboardingCase->employee_id === (int) $employee->id, 404);

        $this->abortUnlessCanViewOffboardingCase($user, $employee, $offboardingCase);

        $employee->loadMissing('company');
        $company = $employee->company;
        abort_unless($company !== null, 404);

        $offboardingCase->load([
            'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'starter:id,name',
            'reviewer:id,name',
        ]);

        $canSeeAll = $this->canSeeFullOffboardingCase($user, $employee);
        $visibleItems = $offboardingCase->items->filter(function (EmployeeOffboardingItem $item) use ($user, $company, $canSeeAll) {
            if ($canSeeAll) {
                return true;
            }

            return $this->userCanSeeOffboardingItem($user, $company, $item);
        })->values();

        $itemsPayload = $visibleItems->map(function (EmployeeOffboardingItem $item) use ($user, $company) {
            $nextStep = $this->itemApprovalService->getNextPendingStep($item);
            $canAct = $nextStep !== null
                && $this->itemApprovalService->canUserApproveStep($user, $company, $item, $nextStep);

            return [
                'id' => $item->id,
                'title' => $item->title,
                'attachment_mode' => $item->attachment_mode,
                'status' => $item->status,
                'sort_order' => $item->sort_order,
                'attachment_path' => $item->attachment_path,
                'attachment_url' => $this->attachmentService->temporaryUrl($item->attachment_path),
                'completed_at' => $item->completed_at?->toIso8601String(),
                'approval_steps' => $this->itemApprovalService->buildApprovalPayload($item, $user, $company),
                'can_act' => $canAct,
                'requires_attachment_on_approve' => $item->requiresAttachment() && blank($item->attachment_path),
                'allows_attachment' => $item->allowsAttachment(),
            ];
        })->all();

        $hasClearanceWorkflow = $this->clearanceApprovalService->hasActiveStepsForCompany($company);

        return Inertia::render('Employees/Offboarding/Show', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
            ],
            'offboarding_case' => [
                'id' => $offboardingCase->id,
                'status' => $offboardingCase->status,
                'started_at' => $offboardingCase->started_at?->toIso8601String(),
                'cleared_at' => $offboardingCase->cleared_at?->toIso8601String(),
                'termination_date' => $offboardingCase->termination_date?->format('Y-m-d'),
                'starter_name' => $offboardingCase->starter?->name,
                'reviewer_name' => $offboardingCase->reviewer?->name,
                'review_notes' => $offboardingCase->review_notes,
            ],
            'items' => $itemsPayload,
            'can_see_all_items' => $canSeeAll,
            'has_clearance_workflow' => $hasClearanceWorkflow,
            'clearance_steps' => $canSeeAll && ($offboardingCase->isPendingClearance() || $offboardingCase->isCleared() || $hasClearanceWorkflow)
                ? $this->clearanceApprovalService->buildApprovalPayload($offboardingCase, $user, $company)
                : [],
            'status' => session('status'),
        ]);
    }

    public function approveItemStep(
        Request $request,
        Employee $employee,
        EmployeeOffboardingCase $offboardingCase,
        EmployeeOffboardingItem $offboardingItem,
        OffboardingItemApprovalStep $offboardingItemStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless((int) $offboardingCase->employee_id === (int) $employee->id, 404);
        abort_unless((int) $offboardingItem->offboarding_case_id === (int) $offboardingCase->id, 404);

        $employee->loadMissing('company');
        $company = $employee->company;
        abort_unless($company !== null, 404);

        abort_unless(
            $this->itemApprovalService->canUserApproveStep($user, $company, $offboardingItem, $offboardingItemStep),
            403
        );

        $needsAttachment = $offboardingItem->requiresAttachment() && blank($offboardingItem->attachment_path);
        $validated = $request->validate([
            ...$this->attachmentService->validationRules($needsAttachment),
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($request->hasFile('attachment') && $offboardingItem->allowsAttachment()) {
            if ($offboardingItem->attachment_path) {
                $this->attachmentService->delete($offboardingItem->attachment_path);
            }

            $path = $this->attachmentService->store(
                $request->file('attachment'),
                (int) $employee->id,
                (int) $offboardingCase->id,
            );
            $offboardingItem->update(['attachment_path' => $path]);
        }

        if ($offboardingItem->requiresAttachment() && blank($offboardingItem->fresh()->attachment_path)) {
            return back()->withErrors([
                'attachment' => __('messages.offboarding.attachment_required'),
            ]);
        }

        $this->itemApprovalService->approveStep($user, $offboardingItem, $offboardingItemStep);
        $offboardingItem->refresh();

        if ($this->itemApprovalService->allStepsApproved($offboardingItem)) {
            $this->itemApprovalService->markItemApproved($offboardingItem, $user);
            $this->caseService->afterItemApproved($offboardingItem->fresh(), $company, $user);
        } else {
            $this->notificationService->notifyItemStepApproved(
                $offboardingItem,
                $company,
                $offboardingItemStep,
                $user,
            );
        }

        return back()->with('status', __('messages.offboarding.item_step_approved'));
    }

    public function rejectItemStep(
        Request $request,
        Employee $employee,
        EmployeeOffboardingCase $offboardingCase,
        EmployeeOffboardingItem $offboardingItem,
        OffboardingItemApprovalStep $offboardingItemStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless((int) $offboardingCase->employee_id === (int) $employee->id, 404);
        abort_unless((int) $offboardingItem->offboarding_case_id === (int) $offboardingCase->id, 404);

        $employee->loadMissing('company');
        $company = $employee->company;
        abort_unless($company !== null, 404);

        abort_unless(
            $this->itemApprovalService->canUserApproveStep($user, $company, $offboardingItem, $offboardingItemStep),
            403
        );

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        if ($offboardingItem->attachment_path) {
            $this->attachmentService->delete($offboardingItem->attachment_path);
        }

        $this->itemApprovalService->rejectStep($user, $offboardingItem, $offboardingItemStep, $validated['reason']);
        $this->notificationService->notifyItemRejected(
            $offboardingItem->fresh(),
            $company,
            $offboardingItemStep,
            $user,
            $validated['reason'],
        );

        return back()->with('status', __('messages.offboarding.item_step_rejected'));
    }

    public function approveClearanceStep(
        Request $request,
        Employee $employee,
        EmployeeOffboardingCase $offboardingCase,
        OffboardingClearanceApprovalStep $offboardingClearanceStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless((int) $offboardingCase->employee_id === (int) $employee->id, 404);

        $employee->loadMissing('company');
        $company = $employee->company;
        abort_unless($company !== null, 404);

        abort_unless(
            $this->clearanceApprovalService->canUserApproveStep($user, $company, $offboardingCase, $offboardingClearanceStep),
            403
        );

        $this->clearanceApprovalService->approveStep($user, $offboardingCase, $offboardingClearanceStep);
        $offboardingCase->refresh();

        if ($this->clearanceApprovalService->allStepsApproved($offboardingCase)) {
            $this->caseService->finalize($offboardingCase, $company, $user);
        } else {
            $this->notificationService->notifyClearanceStepApproved(
                $offboardingCase,
                $company,
                $offboardingClearanceStep,
                $user,
            );
        }

        return back()->with('status', __('messages.offboarding.clearance_step_approved'));
    }

    public function rejectClearanceStep(
        Request $request,
        Employee $employee,
        EmployeeOffboardingCase $offboardingCase,
        OffboardingClearanceApprovalStep $offboardingClearanceStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless((int) $offboardingCase->employee_id === (int) $employee->id, 404);

        $employee->loadMissing('company');
        $company = $employee->company;
        abort_unless($company !== null, 404);

        abort_unless(
            $this->clearanceApprovalService->canUserApproveStep($user, $company, $offboardingCase, $offboardingClearanceStep),
            403
        );

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->clearanceApprovalService->rejectStep(
            $user,
            $offboardingCase,
            $offboardingClearanceStep,
            $validated['reason'],
        );

        $this->notificationService->notifyClearanceRejected(
            $offboardingCase->fresh(),
            $company,
            $offboardingClearanceStep,
            $user,
            $validated['reason'],
        );

        return back()->with('status', __('messages.offboarding.clearance_step_rejected'));
    }

    private function userCanSeeOffboardingItem($user, $company, EmployeeOffboardingItem $item): bool
    {
        if ($item->stepApprovals()->where('approved_by', $user->id)->exists()) {
            return true;
        }

        foreach ($this->itemApprovalService->activeStepsForItem($item) as $step) {
            if ($this->itemApprovalService->canUserApproveStep($user, $company, $item, $step)) {
                return true;
            }

            // Also show items where user belongs to any step team for this item (even if not current).
            if ($step->team_id !== null) {
                $roleService = app(\App\Services\EmployeeUserRoleService::class);
                $item->loadMissing('offboardingCase.employee');
                if ($roleService->userBelongsToTeamInCompanyScoped(
                    $user,
                    (int) $step->team_id,
                    (int) $company->id,
                    $roleService->departmentIdForEmployeeScope($item->offboardingCase?->employee),
                )) {
                    return true;
                }
            }
        }

        return false;
    }
}
