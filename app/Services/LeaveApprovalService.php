<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\LeaveApprovalStep;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApprovalRejection;
use App\Models\LeaveRequestStepApproval;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class LeaveApprovalService
{
    /**
     * @return Collection<int, LeaveApprovalStep>
     */
    public function activeStepsForCompany(int|Company $company): Collection
    {
        $companyId = $company instanceof Company ? (int) $company->id : $company;

        return LeaveApprovalStep::query()
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
        if (LeaveApprovalStep::query()->where('company_id', $company->id)->exists()) {
            return;
        }

        $defaults = [
            ['title' => 'مراجعة الموارد البشرية', 'sort_order' => 1, 'team_name' => 'الموارد البشرية'],
            ['title' => 'اعتماد المدير المباشر', 'sort_order' => 2, 'team_name' => null],
            ['title' => 'اعتماد الإدارة', 'sort_order' => 3, 'team_name' => null],
        ];

        foreach ($defaults as $step) {
            $teamId = null;

            if ($step['team_name']) {
                $teamId = Team::query()->where('name', $step['team_name'])->value('id');
            }

            LeaveApprovalStep::query()->create([
                'company_id' => $company->id,
                'title' => $step['title'],
                'sort_order' => $step['sort_order'],
                'team_id' => $teamId,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildApprovalPayload(LeaveRequest $leaveRequest, User $user, Company $company): array
    {
        $steps = $this->activeStepsForCompany($company);
        $approvedByStepId = LeaveRequestStepApproval::query()
            ->where('leave_request_id', $leaveRequest->id)
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
                && $leaveRequest->isPending()
                && $this->canUserApproveStep($user, $company, $leaveRequest, $step);

            $payload[] = [
                'id' => $step->id,
                'title' => $step->title,
                'sort_order' => $step->sort_order,
                'team_id' => $step->team_id,
                'team_name' => $step->team?->name,
                'approved_at' => $record?->approved_at?->toIso8601String(),
                'approver_name' => $record?->approver?->name,
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

    /**
     * @return array<string, mixed>|null
     */
    public function buildEmployeeProgressPayload(LeaveRequest $leaveRequest, Company $company): ?array
    {
        if (! $leaveRequest->isPending() || ! $this->hasActiveStepsForCompany($company)) {
            return null;
        }

        $steps = $this->activeStepsForCompany($company);
        $approvedByStepId = LeaveRequestStepApproval::query()
            ->where('leave_request_id', $leaveRequest->id)
            ->with('approver')
            ->get()
            ->keyBy('approval_step_id');

        $stepPayload = [];
        $previousStepsApproved = true;
        $approvedCount = 0;
        $currentStepTitle = null;

        foreach ($steps as $step) {
            $record = $approvedByStepId->get($step->id);
            $isApproved = $record !== null;

            if ($isApproved) {
                $approvedCount++;
            }

            $status = match (true) {
                $isApproved => 'approved',
                $previousStepsApproved => 'current',
                default => 'waiting',
            };

            if ($status === 'current' && $currentStepTitle === null) {
                $currentStepTitle = $step->title;
            }

            $stepPayload[] = [
                'id' => $step->id,
                'title' => $step->title,
                'sort_order' => $step->sort_order,
                'team_name' => $step->team?->name,
                'status' => $status,
                'approved_at' => $record?->approved_at?->toIso8601String(),
                'approver_name' => $record?->approver?->name,
            ];

            if (! $isApproved) {
                $previousStepsApproved = false;
            }
        }

        $totalSteps = $steps->count();

        return [
            'steps' => $stepPayload,
            'approved_count' => $approvedCount,
            'total_steps' => $totalSteps,
            'remaining_steps' => max(0, $totalSteps - $approvedCount),
            'current_step_title' => $currentStepTitle,
            'latest_rejection' => $this->buildLatestRejectionPayload($leaveRequest),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildLatestRejectionPayload(LeaveRequest $leaveRequest): ?array
    {
        if ($this->allStepsApproved($leaveRequest)) {
            return null;
        }

        $rejection = LeaveRequestApprovalRejection::query()
            ->where('leave_request_id', $leaveRequest->id)
            ->with(['rejector', 'approvalStep'])
            ->latest('rejected_at')
            ->first();

        if ($rejection === null) {
            return null;
        }

        return [
            'id' => $rejection->id,
            'reason' => $rejection->reason,
            'rejected_at' => $rejection->rejected_at->toIso8601String(),
            'rejector_name' => $rejection->rejector?->name,
            'step_title' => $rejection->approvalStep?->title,
            'cleared_approvals_count' => $rejection->cleared_approvals_count,
        ];
    }

    public function allStepsApproved(LeaveRequest $leaveRequest): bool
    {
        $companyId = (int) $leaveRequest->employee()->value('company_id');
        $stepCount = $this->activeStepsForCompany($companyId)->count();

        if ($stepCount === 0) {
            return false;
        }

        $approvedCount = $leaveRequest->stepApprovals()->count();

        return $approvedCount === $stepCount;
    }

    public function getNextPendingStep(LeaveRequest $leaveRequest): ?LeaveApprovalStep
    {
        $companyId = (int) $leaveRequest->employee()->value('company_id');
        $approvedStepIds = $leaveRequest->stepApprovals()->pluck('approval_step_id');

        return $this->activeStepsForCompany($companyId)->first(
            fn (LeaveApprovalStep $step) => ! $approvedStepIds->contains($step->id)
        );
    }

    public function remainingStepsCount(LeaveRequest $leaveRequest): int
    {
        $companyId = (int) $leaveRequest->employee()->value('company_id');
        $total = $this->activeStepsForCompany($companyId)->count();
        $approved = $leaveRequest->stepApprovals()->count();

        return max(0, $total - $approved);
    }

    public function canUserApproveStep(
        User $user,
        Company $company,
        LeaveRequest $leaveRequest,
        LeaveApprovalStep $step,
    ): bool {
        if ((int) $step->company_id !== (int) $company->id) {
            return false;
        }

        if (! $leaveRequest->isPending()) {
            return false;
        }

        if (! $step->is_active) {
            return false;
        }

        if ($leaveRequest->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
            return false;
        }

        if (! $this->previousStepsAreApproved($leaveRequest, $step)) {
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

        if (! app(EmployeeUserRoleService::class)->userBelongsToTeam($user, $stepTeamId)) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($stepTeamId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user->can('leaves.approve');
    }

    public function previousStepsAreApproved(LeaveRequest $leaveRequest, LeaveApprovalStep $step): bool
    {
        $previousStepIds = LeaveApprovalStep::query()
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

        $approvedCount = LeaveRequestStepApproval::query()
            ->where('leave_request_id', $leaveRequest->id)
            ->whereIn('approval_step_id', $previousStepIds)
            ->count();

        return $approvedCount === $previousStepIds->count();
    }

    public function approveStep(
        User $user,
        LeaveRequest $leaveRequest,
        LeaveApprovalStep $step
    ): LeaveRequestStepApproval {
        return DB::transaction(function () use ($user, $leaveRequest, $step) {
            if ((int) $step->company_id !== (int) $leaveRequest->employee()->value('company_id')) {
                throw new \RuntimeException(__('messages.leaves.approval_step_company_mismatch'));
            }

            if ($leaveRequest->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
                throw new \RuntimeException(__('messages.leaves.already_approved'));
            }

            if (! $this->previousStepsAreApproved($leaveRequest, $step)) {
                throw new \RuntimeException(__('messages.leaves.approval_previous_required'));
            }

            return LeaveRequestStepApproval::query()->create([
                'leave_request_id' => $leaveRequest->id,
                'approval_step_id' => $step->id,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);
        });
    }

    public function rejectStep(
        User $user,
        LeaveRequest $leaveRequest,
        LeaveApprovalStep $step,
        string $reason
    ): LeaveRequestApprovalRejection {
        return DB::transaction(function () use ($user, $leaveRequest, $step, $reason) {
            if ((int) $step->company_id !== (int) $leaveRequest->employee()->value('company_id')) {
                throw new \RuntimeException(__('messages.leaves.approval_step_company_mismatch'));
            }

            if ($leaveRequest->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
                throw new \RuntimeException(__('messages.leaves.already_approved'));
            }

            if (! $this->previousStepsAreApproved($leaveRequest, $step)) {
                throw new \RuntimeException(__('messages.leaves.approval_previous_required'));
            }

            $clearedCount = $leaveRequest->stepApprovals()->count();
            $leaveRequest->stepApprovals()->delete();

            return LeaveRequestApprovalRejection::query()->create([
                'leave_request_id' => $leaveRequest->id,
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
                LeaveApprovalStep::query()
                    ->where('company_id', $companyId)
                    ->whereKey($stepId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
