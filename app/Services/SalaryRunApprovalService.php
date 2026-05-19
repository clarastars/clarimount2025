<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\SalaryRun;
use App\Models\SalaryRunApprovalStep;
use App\Models\SalaryRunStepApproval;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalaryRunApprovalService
{
    /**
     * @return Collection<int, SalaryRunApprovalStep>
     */
    public function activeSteps(): Collection
    {
        return SalaryRunApprovalStep::query()
            ->with('team')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildApprovalPayload(SalaryRun $salaryRun, User $user, Company $company): array
    {
        $steps = $this->activeSteps();
        $approvedByStepId = SalaryRunStepApproval::query()
            ->where('salary_run_id', $salaryRun->id)
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
                && $this->canUserApproveStep($user, $company, $salaryRun, $step, $previousStepsApproved);

            $payload[] = [
                'id' => $step->id,
                'title' => $step->title,
                'sort_order' => $step->sort_order,
                'team_id' => $step->team_id,
                'team_name' => $step->team?->name,
                'approved_at' => $record?->approved_at?->toIso8601String(),
                'approver_name' => $record?->approver?->name,
                'can_approve' => $canApprove,
                'waiting_previous' => ! $previousStepsApproved && ! $isApproved,
            ];

            if (! $isApproved) {
                $previousStepsApproved = false;
            }
        }

        return $payload;
    }

    public function canUserApproveStep(
        User $user,
        Company $company,
        SalaryRun $salaryRun,
        SalaryRunApprovalStep $step,
        bool $previousStepsApproved = true
    ): bool {
        if (! $step->is_active) {
            return false;
        }

        if ($salaryRun->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
            return false;
        }

        if (! $previousStepsApproved && ! $this->previousStepsAreApproved($salaryRun, $step)) {
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

        if ((int) $user->team_id !== (int) $step->team_id) {
            return false;
        }

        return $user->can('salary-runs.approve');
    }

    public function previousStepsAreApproved(SalaryRun $salaryRun, SalaryRunApprovalStep $step): bool
    {
        $previousStepIds = SalaryRunApprovalStep::query()
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

        $approvedCount = SalaryRunStepApproval::query()
            ->where('salary_run_id', $salaryRun->id)
            ->whereIn('approval_step_id', $previousStepIds)
            ->count();

        return $approvedCount === $previousStepIds->count();
    }

    public function approveStep(User $user, SalaryRun $salaryRun, SalaryRunApprovalStep $step): SalaryRunStepApproval
    {
        return DB::transaction(function () use ($user, $salaryRun, $step) {
            if ($salaryRun->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
                throw new \RuntimeException(__('messages.salary_runs.already_approved'));
            }

            if (! $this->previousStepsAreApproved($salaryRun, $step)) {
                throw new \RuntimeException(__('messages.salary_runs.approval_previous_required'));
            }

            return SalaryRunStepApproval::query()->create([
                'salary_run_id' => $salaryRun->id,
                'approval_step_id' => $step->id,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);
        });
    }

    public function reorderSteps(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach (array_values($orderedIds) as $index => $stepId) {
                SalaryRunApprovalStep::query()
                    ->whereKey($stepId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
