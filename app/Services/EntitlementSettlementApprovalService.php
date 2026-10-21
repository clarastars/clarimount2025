<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeEntitlementSettlement;
use App\Models\EntitlementSettlementApprovalDefaultStep;
use App\Models\EntitlementSettlementApprovalRejection;
use App\Models\EntitlementSettlementApprovalStep;
use App\Models\EntitlementSettlementStepApproval;
use App\Models\EntitlementSettlementTransferDefault;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class EntitlementSettlementApprovalService
{
    /**
     * Cached step assignees keyed by "{companyId}:{teamId}:{departmentId|company}".
     *
     * @var array<string, list<array{id: int, name: string, email: string|null}>>
     */
    private array $assigneesCache = [];

    /** @var list<string> */
    public const DEFAULT_STEP_TITLES = [
        'مراجعة الموارد البشرية',
        'اعتماد المدير المباشر',
        'اعتماد الإدارة المالية',
    ];

    /**
     * @return Collection<int, EntitlementSettlementApprovalStep>
     */
    public function activeStepsForCompany(int|Company $company): Collection
    {
        $companyId = $company instanceof Company ? (int) $company->id : $company;

        return EntitlementSettlementApprovalStep::query()
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

    /**
     * @return Collection<int, EntitlementSettlementApprovalDefaultStep>
     */
    public function activeDefaultSteps(): Collection
    {
        return EntitlementSettlementApprovalDefaultStep::query()
            ->with('team')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function seedDefaultStepsForCompany(Company $company): void
    {
        if (EntitlementSettlementApprovalStep::query()->where('company_id', $company->id)->exists()) {
            $this->applyTransferTeamDefaultToCompanyIfEmpty($company);

            return;
        }

        $configuredDefaults = $this->activeDefaultSteps();

        if ($configuredDefaults->isNotEmpty()) {
            foreach ($configuredDefaults as $index => $step) {
                EntitlementSettlementApprovalStep::query()->create([
                    'company_id' => $company->id,
                    'title' => $step->title,
                    'sort_order' => $index + 1,
                    'team_id' => $step->team_id,
                    'is_active' => true,
                ]);
            }

            $this->applyTransferTeamDefaultToCompany($company);

            return;
        }

        $defaults = [
            ['title' => 'مراجعة الموارد البشرية', 'sort_order' => 1, 'team_name' => 'الموارد البشرية'],
            ['title' => 'اعتماد المدير المباشر', 'sort_order' => 2, 'team_name' => null],
            ['title' => 'اعتماد الإدارة المالية', 'sort_order' => 3, 'team_name' => null],
        ];

        foreach ($defaults as $step) {
            $teamId = null;

            if ($step['team_name']) {
                $teamId = Team::query()->where('name', $step['team_name'])->value('id');
            }

            EntitlementSettlementApprovalStep::query()->create([
                'company_id' => $company->id,
                'title' => $step['title'],
                'sort_order' => $step['sort_order'],
                'team_id' => $teamId,
                'is_active' => true,
            ]);
        }

        $this->applyTransferTeamDefaultToCompany($company);
    }

    public function defaultTransferTeamId(): ?int
    {
        $teamId = EntitlementSettlementTransferDefault::current()->team_id;

        return $teamId !== null ? (int) $teamId : null;
    }

    public function updateDefaultTransferTeam(?int $teamId): void
    {
        EntitlementSettlementTransferDefault::current()->update(['team_id' => $teamId]);
    }

    public function updateCompanyTransferTeam(Company $company, ?int $teamId): void
    {
        $company->update(['entitlement_settlement_transfer_team_id' => $teamId]);
    }

    public function applyTransferTeamDefaultToCompany(Company $company): void
    {
        $company->update([
            'entitlement_settlement_transfer_team_id' => $this->defaultTransferTeamId(),
        ]);
    }

    public function applyTransferTeamDefaultToCompanyIfEmpty(Company $company): void
    {
        if ($company->entitlement_settlement_transfer_team_id !== null) {
            return;
        }

        $this->applyTransferTeamDefaultToCompany($company);
    }

    /**
     * @return array{applied: bool, reason: string|null}
     */
    public function applyDefaultsToCompany(Company $company): array
    {
        $defaults = $this->activeDefaultSteps();

        if ($defaults->isEmpty()) {
            return [
                'applied' => false,
                'reason' => 'empty_defaults',
            ];
        }

        $existingSteps = EntitlementSettlementApprovalStep::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($existingSteps as $step) {
            if ($step->hasBlockingWorkflowUsage()) {
                return [
                    'applied' => false,
                    'reason' => 'blocking_usage',
                ];
            }
        }

        DB::transaction(function () use ($company, $existingSteps, $defaults): void {
            foreach ($existingSteps as $step) {
                $step->delete();
            }

            foreach ($defaults as $index => $defaultStep) {
                EntitlementSettlementApprovalStep::query()->create([
                    'company_id' => $company->id,
                    'title' => $defaultStep->title,
                    'sort_order' => $index + 1,
                    'team_id' => $defaultStep->team_id,
                    'is_active' => true,
                ]);
            }

            $this->applyTransferTeamDefaultToCompany($company);
        });

        return [
            'applied' => true,
            'reason' => null,
        ];
    }

    /**
     * @return array{applied: int, skipped: int}
     */
    public function applyDefaultsToAllCompanies(): array
    {
        $applied = 0;
        $skipped = 0;

        foreach (Company::query()->orderBy('id')->get(['id']) as $company) {
            $result = $this->applyDefaultsToCompany($company);

            if ($result['applied']) {
                $applied++;
                continue;
            }

            $skipped++;

            if (($result['reason'] ?? null) === 'empty_defaults') {
                break;
            }
        }

        return [
            'applied' => $applied,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderDefaultSteps(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $stepId) {
                EntitlementSettlementApprovalDefaultStep::query()
                    ->whereKey($stepId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildApprovalPayload(
        EmployeeEntitlementSettlement $settlement,
        User $user,
        Company $company,
    ): array {
        $settlement->loadMissing('employee');
        $steps = $this->activeStepsForCompany($company);
        $approvedByStepId = EntitlementSettlementStepApproval::query()
            ->where('settlement_id', $settlement->id)
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
                && $settlement->isPending()
                && $this->canUserApproveStep($user, $company, $settlement, $step);

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
                    : ($previousStepsApproved && $settlement->isPending()
                        ? 'current'
                        : 'waiting'),
                'can_approve' => $canApprove,
                'can_reject' => $canApprove,
                'waiting_previous' => ! $previousStepsApproved && ! $isApproved,
                'assignees' => $this->assigneesForStep($step, $company, $settlement->employee),
            ];

            if (! $isApproved) {
                $previousStepsApproved = false;
            }
        }

        return $payload;
    }

    public function allStepsApproved(EmployeeEntitlementSettlement $settlement): bool
    {
        $companyId = (int) $settlement->employee()->value('company_id');
        $stepCount = $this->activeStepsForCompany($companyId)->count();

        if ($stepCount === 0) {
            return false;
        }

        return $settlement->stepApprovals()->count() === $stepCount;
    }

    public function getNextPendingStep(EmployeeEntitlementSettlement $settlement): ?EntitlementSettlementApprovalStep
    {
        $companyId = (int) $settlement->employee()->value('company_id');
        $approvedStepIds = $settlement->stepApprovals()->pluck('approval_step_id');

        return $this->activeStepsForCompany($companyId)->first(
            fn (EntitlementSettlementApprovalStep $step) => ! $approvedStepIds->contains($step->id)
        );
    }

    public function remainingStepsCount(EmployeeEntitlementSettlement $settlement): int
    {
        $companyId = (int) $settlement->employee()->value('company_id');
        $total = $this->activeStepsForCompany($companyId)->count();
        $approved = $settlement->stepApprovals()->count();

        return max(0, $total - $approved);
    }

    public function canUserApproveStep(
        User $user,
        Company $company,
        EmployeeEntitlementSettlement $settlement,
        EntitlementSettlementApprovalStep $step,
    ): bool {
        if ((int) $step->company_id !== (int) $company->id) {
            return false;
        }

        if (! $settlement->isPending()) {
            return false;
        }

        if (! $step->is_active) {
            return false;
        }

        if ($settlement->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
            return false;
        }

        if (! $this->previousStepsAreApproved($settlement, $step)) {
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
        $settlement->loadMissing('employee');
        $departmentId = $roleService->departmentIdForEmployeeScope($settlement->employee);

        if (! $roleService->userBelongsToTeamInCompanyScoped($user, $stepTeamId, (int) $company->id, $departmentId)) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($stepTeamId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user->can('employees.entitlements.approve');
    }

    public function previousStepsAreApproved(
        EmployeeEntitlementSettlement $settlement,
        EntitlementSettlementApprovalStep $step,
    ): bool {
        $previousStepIds = EntitlementSettlementApprovalStep::query()
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

        $approvedCount = EntitlementSettlementStepApproval::query()
            ->where('settlement_id', $settlement->id)
            ->whereIn('approval_step_id', $previousStepIds)
            ->count();

        return $approvedCount === $previousStepIds->count();
    }

    public function approveStep(
        User $user,
        EmployeeEntitlementSettlement $settlement,
        EntitlementSettlementApprovalStep $step,
    ): EntitlementSettlementStepApproval {
        return DB::transaction(function () use ($user, $settlement, $step) {
            if ((int) $step->company_id !== (int) $settlement->employee()->value('company_id')) {
                throw new \RuntimeException(__('messages.entitlement_settlement.approval_step_company_mismatch'));
            }

            if ($settlement->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
                throw new \RuntimeException(__('messages.entitlement_settlement.already_approved'));
            }

            if (! $this->previousStepsAreApproved($settlement, $step)) {
                throw new \RuntimeException(__('messages.entitlement_settlement.approval_previous_required'));
            }

            return EntitlementSettlementStepApproval::query()->create([
                'settlement_id' => $settlement->id,
                'approval_step_id' => $step->id,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);
        });
    }

    public function rejectStep(
        User $user,
        EmployeeEntitlementSettlement $settlement,
        EntitlementSettlementApprovalStep $step,
        string $reason,
    ): EntitlementSettlementApprovalRejection {
        return DB::transaction(function () use ($user, $settlement, $step, $reason) {
            if ((int) $step->company_id !== (int) $settlement->employee()->value('company_id')) {
                throw new \RuntimeException(__('messages.entitlement_settlement.approval_step_company_mismatch'));
            }

            if ($settlement->stepApprovals()->where('approval_step_id', $step->id)->exists()) {
                throw new \RuntimeException(__('messages.entitlement_settlement.already_approved'));
            }

            if (! $this->previousStepsAreApproved($settlement, $step)) {
                throw new \RuntimeException(__('messages.entitlement_settlement.approval_previous_required'));
            }

            $clearedCount = $settlement->stepApprovals()->count();
            $settlement->stepApprovals()->delete();

            $settlement->update([
                'status' => EmployeeEntitlementSettlement::STATUS_REJECTED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ]);

            return EntitlementSettlementApprovalRejection::query()->create([
                'settlement_id' => $settlement->id,
                'approval_step_id' => $step->id,
                'rejected_at' => now(),
                'rejected_by' => $user->id,
                'reason' => $reason,
                'cleared_approvals_count' => $clearedCount,
            ]);
        });
    }

    public function markApproved(EmployeeEntitlementSettlement $settlement, User $user, ?string $notes = null): void
    {
        $settlement->update([
            'status' => EmployeeEntitlementSettlement::STATUS_APPROVED,
            'payment_status' => EmployeeEntitlementSettlement::PAYMENT_UNPAID,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    public function markPaid(
        EmployeeEntitlementSettlement $settlement,
        User $actor,
        string $receiptPath,
        ?string $paymentNotes = null,
    ): EmployeeEntitlementSettlement {
        if (! $settlement->awaitsTransfer()) {
            throw ValidationException::withMessages([
                'payment_status' => [__('messages.entitlement_settlement.payment_already_processed')],
            ]);
        }

        $settlement->update([
            'payment_status' => EmployeeEntitlementSettlement::PAYMENT_PAID,
            'paid_at' => now(),
            'paid_by' => $actor->id,
            'payment_receipt_path' => $receiptPath,
            'payment_notes' => $paymentNotes,
        ]);

        return $settlement->fresh() ?? $settlement;
    }

    /**
     * Users assigned to the step's team for this employee (department first, else company-wide).
     *
     * @return list<array{id: int, name: string, email: string|null}>
     */
    public function assigneesForStep(
        EntitlementSettlementApprovalStep $step,
        Company $company,
        ?Employee $employee = null,
    ): array {
        if ($step->team_id === null) {
            return [];
        }

        $roleService = app(EmployeeUserRoleService::class);
        $departmentId = $roleService->departmentIdForEmployeeScope($employee);
        $cacheKey = (int) $company->id.':'.(int) $step->team_id.':'.($departmentId ?? 'company');

        if (isset($this->assigneesCache[$cacheKey])) {
            return $this->assigneesCache[$cacheKey];
        }

        $userIds = $roleService->userIdsForTeamInCompanyScoped(
            (int) $step->team_id,
            (int) $company->id,
            $departmentId,
        );

        if ($userIds === []) {
            return $this->assigneesCache[$cacheKey] = [];
        }

        $users = User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $teamId = (int) $step->team_id;
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $assignees = [];

        try {
            $registrar->setPermissionsTeamId($teamId);

            foreach ($users as $user) {
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');

                if (! $user->can('employees.entitlements.approve')) {
                    continue;
                }

                $assignees[] = [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'email' => $user->email,
                ];
            }
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }

        if ($assignees === []) {
            $assignees = $users
                ->map(static fn (User $user): array => [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'email' => $user->email,
                ])
                ->values()
                ->all();
        }

        return $this->assigneesCache[$cacheKey] = $assignees;
    }

    public function reorderStepsForCompany(int $companyId, array $orderedIds): void
    {
        DB::transaction(function () use ($companyId, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $stepId) {
                EntitlementSettlementApprovalStep::query()
                    ->where('company_id', $companyId)
                    ->whereKey($stepId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
