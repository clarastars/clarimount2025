<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\EmployeeOffboardingCase;
use App\Models\EmployeeOffboardingClearanceApprovalRejection;
use App\Models\EmployeeOffboardingClearanceStepApproval;
use App\Models\OffboardingClearanceApprovalStep;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class OffboardingClearanceApprovalService
{
    /**
     * @return Collection<int, OffboardingClearanceApprovalStep>
     */
    public function activeStepsForCompany(int|Company $company): Collection
    {
        $companyId = $company instanceof Company ? (int) $company->id : $company;

        return OffboardingClearanceApprovalStep::query()
            ->with('team')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function hasActiveStepsForCompany(int|Company $company): bool
    {
        return $this->activeStepsForCompany($company)->isNotEmpty();
    }

    public function seedDefaultStepsForCompany(Company $company): void
    {
        if (OffboardingClearanceApprovalStep::query()->where('company_id', $company->id)->exists()) {
            return;
        }

        $hrTeamId = Team::query()->where('name', 'الموارد البشرية')->value('id');

        OffboardingClearanceApprovalStep::query()->create([
            'company_id' => $company->id,
            'title' => 'اعتماد إنهاء الخدمات النهائي',
            'sort_order' => 1,
            'team_id' => $hrTeamId,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildApprovalPayload(
        EmployeeOffboardingCase $case,
        User $user,
        Company $company,
    ): array {
        $steps = $this->activeStepsForCompany($company);
        $approvedByStepId = EmployeeOffboardingClearanceStepApproval::query()
            ->where('offboarding_case_id', $case->id)
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
                && $case->isPendingClearance()
                && $this->canUserApproveStep($user, $company, $case, $step);

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
                    : ($previousStepsApproved && $case->isPendingClearance()
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

    public function allStepsApproved(EmployeeOffboardingCase $case): bool
    {
        $stepCount = $this->activeStepsForCompany((int) $case->company_id)->count();

        if ($stepCount === 0) {
            return true;
        }

        return $case->clearanceStepApprovals()->count() === $stepCount;
    }

    public function getNextPendingStep(EmployeeOffboardingCase $case): ?OffboardingClearanceApprovalStep
    {
        $approvedStepIds = $case->clearanceStepApprovals()->pluck('approval_step_id');

        return $this->activeStepsForCompany((int) $case->company_id)->first(
            fn (OffboardingClearanceApprovalStep $step) => ! $approvedStepIds->contains($step->id)
        );
    }

    public function remainingStepsCount(EmployeeOffboardingCase $case): int
    {
        $total = $this->activeStepsForCompany((int) $case->company_id)->count();
        $approved = $case->clearanceStepApprovals()->count();

        return max(0, $total - $approved);
    }

    public function canUserApproveStep(
        User $user,
        Company $company,
        EmployeeOffboardingCase $case,
        OffboardingClearanceApprovalStep $step,
    ): bool {
        if ((int) $step->company_id !== (int) $company->id) {
            return false;
        }

        if (! $case->isPendingClearance()) {
            return false;
        }

        if (! $step->is_active) {
            return false;
        }

        if ($case->clearanceStepApprovals()->where('approval_step_id', $step->id)->exists()) {
            return false;
        }

        if (! $this->previousStepsAreApproved($case, $step)) {
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
        $case->loadMissing('employee');
        $departmentId = $roleService->departmentIdForEmployeeScope($case->employee);

        if (! $roleService->userBelongsToTeamInCompanyScoped($user, $stepTeamId, (int) $company->id, $departmentId)) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($stepTeamId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user->can('employees.offboarding.clearance-approve');
    }

    public function previousStepsAreApproved(
        EmployeeOffboardingCase $case,
        OffboardingClearanceApprovalStep $step,
    ): bool {
        $previousStepIds = OffboardingClearanceApprovalStep::query()
            ->where('company_id', $step->company_id)
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

        $approvedCount = EmployeeOffboardingClearanceStepApproval::query()
            ->where('offboarding_case_id', $case->id)
            ->whereIn('approval_step_id', $previousStepIds)
            ->count();

        return $approvedCount === $previousStepIds->count();
    }

    public function approveStep(
        User $user,
        EmployeeOffboardingCase $case,
        OffboardingClearanceApprovalStep $step,
    ): EmployeeOffboardingClearanceStepApproval {
        return DB::transaction(function () use ($user, $case, $step) {
            if ((int) $step->company_id !== (int) $case->company_id) {
                throw new \RuntimeException(__('messages.offboarding.approval_step_company_mismatch'));
            }

            if ($case->clearanceStepApprovals()->where('approval_step_id', $step->id)->exists()) {
                throw new \RuntimeException(__('messages.offboarding.already_approved'));
            }

            if (! $this->previousStepsAreApproved($case, $step)) {
                throw new \RuntimeException(__('messages.offboarding.approval_previous_required'));
            }

            return EmployeeOffboardingClearanceStepApproval::query()->create([
                'offboarding_case_id' => $case->id,
                'approval_step_id' => $step->id,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);
        });
    }

    public function rejectStep(
        User $user,
        EmployeeOffboardingCase $case,
        OffboardingClearanceApprovalStep $step,
        string $reason,
    ): EmployeeOffboardingClearanceApprovalRejection {
        return DB::transaction(function () use ($user, $case, $step, $reason) {
            if ((int) $step->company_id !== (int) $case->company_id) {
                throw new \RuntimeException(__('messages.offboarding.approval_step_company_mismatch'));
            }

            if ($case->clearanceStepApprovals()->where('approval_step_id', $step->id)->exists()) {
                throw new \RuntimeException(__('messages.offboarding.already_approved'));
            }

            if (! $this->previousStepsAreApproved($case, $step)) {
                throw new \RuntimeException(__('messages.offboarding.approval_previous_required'));
            }

            $clearedCount = $case->clearanceStepApprovals()->count();
            $case->clearanceStepApprovals()->delete();

            // Restart clearance chain while keeping case in pending_clearance.
            $case->update([
                'status' => EmployeeOffboardingCase::STATUS_PENDING_CLEARANCE,
                'review_notes' => $reason,
            ]);

            return EmployeeOffboardingClearanceApprovalRejection::query()->create([
                'offboarding_case_id' => $case->id,
                'approval_step_id' => $step->id,
                'rejected_at' => now(),
                'rejected_by' => $user->id,
                'reason' => $reason,
                'cleared_approvals_count' => $clearedCount,
            ]);
        });
    }

    public function reorderStepsForCompany(int $companyId, array $orderedIds): void
    {
        DB::transaction(function () use ($companyId, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $stepId) {
                OffboardingClearanceApprovalStep::query()
                    ->where('company_id', $companyId)
                    ->whereKey($stepId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
