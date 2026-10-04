<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\EmployeeOffboardingCase;
use App\Models\EmployeeOffboardingItem;
use App\Models\EmployeeOffboardingItemApprovalRejection;
use App\Models\EmployeeOffboardingItemStepApproval;
use App\Models\OffboardingItemApprovalStep;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class OffboardingItemApprovalService
{
    /**
     * @return Collection<int, OffboardingItemApprovalStep>
     */
    public function activeStepsForTemplate(int $templateId): Collection
    {
        return OffboardingItemApprovalStep::query()
            ->with('team')
            ->where('template_id', $templateId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function hasActiveStepsForTemplate(int $templateId): bool
    {
        return $this->activeStepsForTemplate($templateId)->isNotEmpty();
    }

    /**
     * Steps that apply to an instance item (via its template snapshot).
     *
     * @return Collection<int, OffboardingItemApprovalStep>
     */
    public function activeStepsForItem(EmployeeOffboardingItem $item): Collection
    {
        if ($item->template_id === null) {
            return collect();
        }

        return $this->activeStepsForTemplate((int) $item->template_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildApprovalPayload(
        EmployeeOffboardingItem $item,
        User $user,
        Company $company,
    ): array {
        $steps = $this->activeStepsForItem($item);
        $approvedByStepId = EmployeeOffboardingItemStepApproval::query()
            ->where('offboarding_item_id', $item->id)
            ->with('approver')
            ->get()
            ->keyBy('approval_step_id');

        $payload = [];
        $previousStepsApproved = true;

        foreach ($steps as $step) {
            $record = $approvedByStepId->get($step->id);
            $isApproved = $record !== null;

            $canApprove = $previousStepsApproved
                && ! $isApproved
                && $item->isPending()
                && $this->canUserApproveStep($user, $company, $item, $step);

            $payload[] = [
                'id' => $step->id,
                'title' => $step->title,
                'sort_order' => $step->sort_order,
                'team_id' => $step->team_id,
                'team_name' => $step->team?->name,
                'approved_at' => $record?->approved_at?->toIso8601String(),
                'approver_name' => $record?->approver?->name,
                'status' => $isApproved
                    ? 'approved'
                    : ($previousStepsApproved && $item->isPending()
                        ? 'current'
                        : 'waiting'),
                'can_approve' => $canApprove,
                'can_reject' => $canApprove,
                'waiting_previous' => ! $previousStepsApproved && ! $isApproved,
            ];

            if (! $isApproved) {
                $previousStepsApproved = false;
            }
        }

        return $payload;
    }

    public function allStepsApproved(EmployeeOffboardingItem $item): bool
    {
        $steps = $this->activeStepsForItem($item);

        if ($steps->isEmpty()) {
            return true;
        }

        return $item->stepApprovals()->count() === $steps->count();
    }

    public function getNextPendingStep(EmployeeOffboardingItem $item): ?OffboardingItemApprovalStep
    {
        $approvedStepIds = $item->stepApprovals()->pluck('approval_step_id');

        return $this->activeStepsForItem($item)->first(
            fn (OffboardingItemApprovalStep $step) => ! $approvedStepIds->contains($step->id)
        );
    }

    public function remainingStepsCount(EmployeeOffboardingItem $item): int
    {
        $total = $this->activeStepsForItem($item)->count();
        $approved = $item->stepApprovals()->count();

        return max(0, $total - $approved);
    }

    public function canUserApproveStep(
        User $user,
        Company $company,
        EmployeeOffboardingItem $item,
        OffboardingItemApprovalStep $step,
    ): bool {
        $item->loadMissing('offboardingCase.employee');

        $case = $item->offboardingCase;
        if ($case === null || ! $case->isInProgress()) {
            return false;
        }

        if ((int) $step->company_id !== (int) $company->id) {
            return false;
        }

        if ($item->template_id !== null && (int) $step->template_id !== (int) $item->template_id) {
            return false;
        }

        if (! $item->isPending()) {
            return false;
        }

        if (! $step->is_active) {
            return false;
        }

        if ($item->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
            return false;
        }

        if (! $this->previousStepsAreApproved($item, $step)) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->where('id', $company->id)->exists()) {
            return true;
        }

        if ($step->team_id === null) {
            return false;
        }

        $stepTeamId = (int) $step->team_id;
        $roleService = app(EmployeeUserRoleService::class);
        $departmentId = $roleService->departmentIdForEmployeeScope($case->employee);

        if (! $roleService->userBelongsToTeamInCompanyScoped($user, $stepTeamId, (int) $company->id, $departmentId)) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($stepTeamId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user->can('employees.offboarding.item-approve');
    }

    public function previousStepsAreApproved(
        EmployeeOffboardingItem $item,
        OffboardingItemApprovalStep $step,
    ): bool {
        if ($item->template_id === null) {
            return true;
        }

        $previousStepIds = OffboardingItemApprovalStep::query()
            ->where('template_id', $item->template_id)
            ->where('is_active', true)
            ->where(function ($query) use ($step) {
                $query->where('sort_order', '<', $step->sort_order)
                    ->orWhere(function ($inner) use ($step) {
                        $inner->where('sort_order', $step->sort_order)
                            ->where('id', '<', $step->id);
                    });
            })
            ->pluck('id');

        if ($previousStepIds->isEmpty()) {
            return true;
        }

        $approvedCount = EmployeeOffboardingItemStepApproval::query()
            ->where('offboarding_item_id', $item->id)
            ->whereIn('approval_step_id', $previousStepIds)
            ->count();

        return $approvedCount === $previousStepIds->count();
    }

    public function approveStep(
        User $user,
        EmployeeOffboardingItem $item,
        OffboardingItemApprovalStep $step,
    ): EmployeeOffboardingItemStepApproval {
        return DB::transaction(function () use ($user, $item, $step) {
            $item->loadMissing('offboardingCase');

            if ((int) $step->company_id !== (int) $item->offboardingCase?->company_id) {
                throw new \RuntimeException(__('messages.offboarding.approval_step_company_mismatch'));
            }

            if ($item->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
                throw new \RuntimeException(__('messages.offboarding.already_approved'));
            }

            if (! $this->previousStepsAreApproved($item, $step)) {
                throw new \RuntimeException(__('messages.offboarding.approval_previous_required'));
            }

            return EmployeeOffboardingItemStepApproval::query()->create([
                'offboarding_item_id' => $item->id,
                'approval_step_id' => $step->id,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);
        });
    }

    public function rejectStep(
        User $user,
        EmployeeOffboardingItem $item,
        OffboardingItemApprovalStep $step,
        string $reason,
    ): EmployeeOffboardingItemApprovalRejection {
        return DB::transaction(function () use ($user, $item, $step, $reason) {
            $item->loadMissing('offboardingCase');

            if ((int) $step->company_id !== (int) $item->offboardingCase?->company_id) {
                throw new \RuntimeException(__('messages.offboarding.approval_step_company_mismatch'));
            }

            if ($item->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
                throw new \RuntimeException(__('messages.offboarding.already_approved'));
            }

            if (! $this->previousStepsAreApproved($item, $step)) {
                throw new \RuntimeException(__('messages.offboarding.approval_previous_required'));
            }

            $clearedCount = $item->stepApprovals()->count();
            $item->stepApprovals()->delete();

            // Restart item chain: clear approvals, keep pending, notify first step again.
            $item->update([
                'status' => EmployeeOffboardingItem::STATUS_PENDING,
                'attachment_path' => null,
                'completed_by' => null,
                'completed_at' => null,
            ]);

            return EmployeeOffboardingItemApprovalRejection::query()->create([
                'offboarding_item_id' => $item->id,
                'approval_step_id' => $step->id,
                'rejected_at' => now(),
                'rejected_by' => $user->id,
                'reason' => $reason,
                'cleared_approvals_count' => $clearedCount,
            ]);
        });
    }

    public function markItemApproved(EmployeeOffboardingItem $item, User $user): void
    {
        $item->update([
            'status' => EmployeeOffboardingItem::STATUS_APPROVED,
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);
    }

    public function reorderStepsForTemplate(int $templateId, array $orderedIds): void
    {
        DB::transaction(function () use ($templateId, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $stepId) {
                OffboardingItemApprovalStep::query()
                    ->where('template_id', $templateId)
                    ->whereKey($stepId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function userCanActOnAnyPendingItemStep(
        User $user,
        Company $company,
        EmployeeOffboardingCase $case,
    ): bool {
        if (! $case->isInProgress()) {
            return false;
        }

        $case->loadMissing('items');

        foreach ($case->items as $item) {
            if (! $item->isPending()) {
                continue;
            }

            $next = $this->getNextPendingStep($item);
            if ($next !== null && $this->canUserApproveStep($user, $company, $item, $next)) {
                return true;
            }
        }

        return false;
    }
}
