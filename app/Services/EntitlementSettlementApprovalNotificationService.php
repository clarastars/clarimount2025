<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\EntitlementSettlementApprovalWorkflowMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeEntitlementSettlement;
use App\Models\EntitlementSettlementApprovalStep;
use App\Models\User;
use App\Notifications\EntitlementSettlementApprovalWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EntitlementSettlementApprovalNotificationService
{
    public function __construct(
        private EntitlementSettlementApprovalService $approvalService,
    ) {}

    public function notifyWorkflowStarted(
        EmployeeEntitlementSettlement $settlement,
        Company $company,
        User $actor,
    ): void {
        $firstStep = $this->approvalService->getNextPendingStep($settlement);

        if ($firstStep === null) {
            return;
        }

        $payload = [
            ...$this->buildBasePayload($settlement, $company, $actor),
            'step_id' => $firstStep->id,
            'step_title' => $firstStep->title,
        ];

        foreach ($this->getWorkflowStakeholders($settlement, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($this->approvalService->canUserApproveStep($user, $company, $settlement, $firstStep)) {
                $this->send($user, 'your_turn', $payload);
            }
        }
    }

    public function notifyStepApproved(
        EmployeeEntitlementSettlement $settlement,
        Company $company,
        EntitlementSettlementApprovalStep $approvedStep,
        User $actor,
    ): void {
        $settlement->refresh();
        $basePayload = $this->buildBasePayload($settlement, $company, $actor);
        $basePayload['step_id'] = $approvedStep->id;
        $basePayload['step_title'] = $approvedStep->title;
        $basePayload['remaining_steps'] = $this->approvalService->remainingStepsCount($settlement);

        $nextStep = $this->approvalService->getNextPendingStep($settlement);
        $stakeholders = $this->getWorkflowStakeholders($settlement, $company);

        foreach ($stakeholders as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($nextStep !== null && $this->approvalService->canUserApproveStep($user, $company, $settlement, $nextStep)) {
                $this->send($user, 'your_turn', [
                    ...$basePayload,
                    'step_id' => $nextStep->id,
                    'step_title' => $nextStep->title,
                ]);

                continue;
            }

            $this->send($user, 'step_approved', $basePayload);
        }
    }

    public function notifyWorkflowFinalized(
        EmployeeEntitlementSettlement $settlement,
        Company $company,
        User $actor,
    ): void {
        $payload = $this->buildBasePayload($settlement, $company, $actor);

        foreach ($this->getWorkflowStakeholders($settlement, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'finalized', $payload);
        }
    }

    public function notifyStepRejected(
        EmployeeEntitlementSettlement $settlement,
        Company $company,
        EntitlementSettlementApprovalStep $rejectedStep,
        User $actor,
        string $reason,
    ): void {
        $settlement->refresh();
        $payload = [
            ...$this->buildBasePayload($settlement, $company, $actor),
            'step_id' => $rejectedStep->id,
            'step_title' => $rejectedStep->title,
            'reason' => $reason,
        ];

        foreach ($this->getWorkflowStakeholders($settlement, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'rejected', $payload);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function getWorkflowStakeholders(EmployeeEntitlementSettlement $settlement, Company $company): Collection
    {
        $settlement->loadMissing('employee');

        $teamIds = $this->approvalService->activeStepsForCompany($company)
            ->pluck('team_id')
            ->filter()
            ->unique()
            ->values();

        $userIds = collect([$company->owner_id])->filter();
        $roleService = app(EmployeeUserRoleService::class);
        $departmentId = $roleService->departmentIdForEmployeeScope($settlement->employee);

        foreach ($teamIds as $teamId) {
            $teamMemberIds = $roleService->userIdsForTeamInCompanyScoped(
                (int) $teamId,
                (int) $company->id,
                $departmentId,
            );

            if ($teamMemberIds === []) {
                continue;
            }
            $userIds = $userIds->merge($teamMemberIds);
        }

        return User::query()
            ->whereIn('id', $userIds->unique()->values())
            ->get()
            ->filter(function (User $user) use ($company, $settlement) {
                $employee = $settlement->employee;
                if ($employee === null) {
                    return false;
                }

                return $this->userCanReceiveWorkflowNotifications($user, $company, $employee);
            })
            ->values();
    }

    private function userIsAssignedToApprovalStep(User $user, EntitlementSettlementApprovalStep $step, ?Employee $employee = null): bool
    {
        if ($step->team_id === null) {
            return false;
        }

        $roleService = app(EmployeeUserRoleService::class);

        return $roleService->userBelongsToTeamInCompanyScoped(
            $user,
            (int) $step->team_id,
            (int) $step->company_id,
            $roleService->departmentIdForEmployeeScope($employee),
        );
    }

    private function userCanReceiveWorkflowNotifications(User $user, Company $company, Employee $employee): bool
    {
        $roleService = app(EmployeeUserRoleService::class);

        return $roleService->canAnyAccessEmployeeInCompanyDepartment(
            $user,
            ['employees.entitlements.approve', 'employees.entitlements.settle', 'employees.readonly'],
            (int) $company->id,
            $roleService->departmentIdForEmployeeScope($employee)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBasePayload(
        EmployeeEntitlementSettlement $settlement,
        Company $company,
        User $actor,
    ): array {
        $employee = $settlement->employee;

        return [
            'settlement_id' => $settlement->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'company_id' => $company->id,
            'company_name' => $company->name_ar ?: $company->name_en,
            'settlement_date' => $settlement->settlement_date?->format('Y-m-d'),
            'settlement_reason' => $settlement->reason,
            'net_due' => number_format((float) $settlement->net_due, 2),
            'actor_name' => $actor->name,
            'url' => route('employees.entitlement-settlement.show', [$employee, $settlement], absolute: false),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(User $user, string $eventType, array $payload): void
    {
        $user->notify(new EntitlementSettlementApprovalWorkflowNotification($eventType, $payload));
        $this->sendWorkflowEmail($user, $eventType, $payload);
        $this->broadcastToSuperAdmins($eventType, $payload, [$user->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWorkflowEmail(User $user, string $eventType, array $payload): void
    {
        $user->loadMissing('employee');

        $workEmail = trim((string) ($user->employee?->work_email ?? $user->email ?? ''));

        if ($workEmail === '' || ! filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($workEmail)->send(new EntitlementSettlementApprovalWorkflowMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send entitlement settlement approval workflow email.', [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'settlement_id' => $payload['settlement_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $excludeUserIds
     * @param  array<string, mixed>  $payload
     */
    private function broadcastToSuperAdmins(string $eventType, array $payload, array $excludeUserIds = []): void
    {
        $excludeUserIds = array_values(array_unique($excludeUserIds));

        User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'super-admin'))
            ->whereNotIn('id', $excludeUserIds)
            ->get()
            ->each(function (User $admin) use ($eventType, $payload): void {
                $admin->notify(new EntitlementSettlementApprovalWorkflowNotification($eventType, $payload));
                $this->sendWorkflowEmail($admin, $eventType, $payload);
            });
    }
}
