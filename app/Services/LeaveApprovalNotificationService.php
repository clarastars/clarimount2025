<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\LeaveApprovalWorkflowMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveApprovalStep;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveApprovalWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

class LeaveApprovalNotificationService
{
    public function __construct(
        private LeaveApprovalService $approvalService,
    ) {}

    public function notifyWorkflowStarted(LeaveRequest $leaveRequest, Company $company, User $actor): void
    {
        $firstStep = $this->approvalService->getNextPendingStep($leaveRequest);

        if ($firstStep === null) {
            return;
        }

        $payload = [
            ...$this->buildBasePayload($leaveRequest, $company, $actor),
            'step_id' => $firstStep->id,
            'step_title' => $firstStep->title,
        ];

        foreach ($this->getWorkflowStakeholders($leaveRequest, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($this->userIsAssignedToApprovalStep($user, $firstStep)) {
                $this->send($user, 'your_turn', $payload);
            }
        }
    }

    public function notifyStepApproved(
        LeaveRequest $leaveRequest,
        Company $company,
        LeaveApprovalStep $approvedStep,
        User $actor,
    ): void {
        $leaveRequest->refresh();
        $basePayload = $this->buildBasePayload($leaveRequest, $company, $actor);
        $basePayload['step_id'] = $approvedStep->id;
        $basePayload['step_title'] = $approvedStep->title;
        $basePayload['remaining_steps'] = $this->approvalService->remainingStepsCount($leaveRequest);

        $nextStep = $this->approvalService->getNextPendingStep($leaveRequest);
        $stakeholders = $this->getWorkflowStakeholders($leaveRequest, $company);

        foreach ($stakeholders as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($nextStep !== null && $this->userIsAssignedToApprovalStep($user, $nextStep)) {
                $this->send($user, 'your_turn', [
                    ...$basePayload,
                    'step_id' => $nextStep->id,
                    'step_title' => $nextStep->title,
                ]);

                continue;
            }

            $this->send($user, 'step_approved', $basePayload);
        }

        $this->notifyEmployeeProgress($leaveRequest, $basePayload, $nextStep !== null);
    }

    public function notifyWorkflowFinalized(LeaveRequest $leaveRequest, Company $company, User $actor): void
    {
        $payload = $this->buildBasePayload($leaveRequest, $company, $actor);

        foreach ($this->getWorkflowStakeholders($leaveRequest, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'finalized', $payload);
        }

        $this->notifyEmployeeFinalized($leaveRequest, $payload);
    }

    public function notifyStepRejected(
        LeaveRequest $leaveRequest,
        Company $company,
        LeaveApprovalStep $rejectedStep,
        User $actor,
        string $reason,
    ): void {
        $leaveRequest->refresh();
        $payload = [
            ...$this->buildBasePayload($leaveRequest, $company, $actor),
            'step_id' => $rejectedStep->id,
            'step_title' => $rejectedStep->title,
            'reason' => $reason,
        ];

        $firstStep = $this->approvalService->getNextPendingStep($leaveRequest);

        foreach ($this->getWorkflowStakeholders($leaveRequest, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($firstStep !== null && $this->userIsAssignedToApprovalStep($user, $firstStep)) {
                $this->send($user, 'your_turn', [
                    ...$payload,
                    'step_id' => $firstStep->id,
                    'step_title' => $firstStep->title,
                    'after_rejection' => true,
                ]);

                continue;
            }

            $this->send($user, 'rejected', $payload);
        }

        $this->notifyEmployeeWorkflowRejected($leaveRequest, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEmployeeProgress(
        LeaveRequest $leaveRequest,
        array $payload,
        bool $hasMoreSteps,
    ): void {
        $portalUser = $this->resolveEmployeePortalUser($leaveRequest->employee);

        if ($portalUser === null) {
            return;
        }

        $eventType = $hasMoreSteps ? 'step_progress' : 'approved';
        $employeePayload = [
            ...$payload,
            'url' => route('employee.leaves.index'),
        ];

        $this->sendToEmployee($portalUser, $eventType, $employeePayload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEmployeeFinalized(LeaveRequest $leaveRequest, array $payload): void
    {
        $portalUser = $this->resolveEmployeePortalUser($leaveRequest->employee);

        if ($portalUser === null) {
            return;
        }

        $this->sendToEmployee($portalUser, 'approved', [
            ...$payload,
            'url' => route('employee.leaves.index'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEmployeeWorkflowRejected(LeaveRequest $leaveRequest, array $payload): void
    {
        $portalUser = $this->resolveEmployeePortalUser($leaveRequest->employee);

        if ($portalUser === null) {
            return;
        }

        $this->sendToEmployee($portalUser, 'workflow_rejected', [
            ...$payload,
            'url' => route('employee.leaves.index'),
        ]);
    }

    private function resolveEmployeePortalUser(Employee $employee): ?User
    {
        $employee->loadMissing('user');

        return $employee->user;
    }

    /**
     * @return Collection<int, User>
     */
    public function getWorkflowStakeholders(LeaveRequest $leaveRequest, Company $company): Collection
    {
        $teamIds = $this->approvalService->activeStepsForCompany($company)
            ->pluck('team_id')
            ->filter()
            ->unique()
            ->values();

        $userIds = collect([$company->owner_id])->filter();
        $roleService = app(EmployeeUserRoleService::class);

        foreach ($teamIds as $teamId) {
            $teamMemberIds = $roleService->userIdsForTeam((int) $teamId);

            if ($teamMemberIds === []) {
                continue;
            }

            $teamUserIds = User::query()
                ->whereIn('id', $teamMemberIds)
                ->where(function ($query) use ($company) {
                    $query->whereHas('accessibleCompanies', function ($companyQuery) use ($company) {
                        $companyQuery->where('companies.id', $company->id);
                    })->orWhereHas('ownedCompanies', function ($companyQuery) use ($company) {
                        $companyQuery->where('id', $company->id);
                    });
                })
                ->pluck('id');

            $userIds = $userIds->merge($teamUserIds);
        }

        return User::query()
            ->whereIn('id', $userIds->unique()->values())
            ->get()
            ->filter(fn (User $user) => $this->userCanReceiveLeaveWorkflowNotifications($user, $company))
            ->values();
    }

    private function userIsAssignedToApprovalStep(User $user, LeaveApprovalStep $step): bool
    {
        if ($step->team_id === null) {
            return false;
        }

        return app(EmployeeUserRoleService::class)->userBelongsToTeam($user, (int) $step->team_id);
    }

    private function userCanReceiveLeaveWorkflowNotifications(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->where('id', $company->id)->exists()) {
            return true;
        }

        $teamIds = app(EmployeeUserRoleService::class)->assignedTeamIdsFor($user);

        if ($teamIds === []) {
            return false;
        }

        foreach ($teamIds as $teamId) {
            $this->refreshUserPermissionContext($user, $teamId);

            if (
                $user->can('leaves.approve')
                || $user->can('leaves.company.view')
                || $user->can('leaves.create')
            ) {
                return true;
            }
        }

        return false;
    }

    private function refreshUserPermissionContext(User $user, ?int $teamId = null): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId ?? $user->team_id);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBasePayload(LeaveRequest $leaveRequest, Company $company, User $actor): array
    {
        $employee = $leaveRequest->employee;

        return [
            'leave_request_id' => $leaveRequest->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'company_id' => $company->id,
            'company_name' => $company->name_ar ?: $company->name_en,
            'leave_type' => $leaveRequest->leave_type,
            'start_date' => $leaveRequest->start_date->format('Y-m-d'),
            'end_date' => $leaveRequest->end_date->format('Y-m-d'),
            'days' => $leaveRequest->days,
            'actor_name' => $actor->name,
            'url' => route('companies.leaves.index', $company),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(User $user, string $eventType, array $payload): void
    {
        $user->notify(new LeaveApprovalWorkflowNotification($eventType, $payload));
        $this->sendWorkflowEmail($user, $eventType, $payload);
        $this->broadcastToSuperAdmins($eventType, $payload, [$user->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendToEmployee(User $user, string $eventType, array $payload): void
    {
        $user->notify(new LeaveApprovalWorkflowNotification($eventType, $payload));
        $this->sendEmployeeWorkflowEmail($user, $eventType, $payload);
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
            Mail::to($workEmail)->send(new LeaveApprovalWorkflowMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send leave approval workflow email.', [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'leave_request_id' => $payload['leave_request_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendEmployeeWorkflowEmail(User $user, string $eventType, array $payload): void
    {
        $email = trim((string) ($user->email ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($email)->send(new LeaveApprovalWorkflowMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send leave approval workflow email to employee.', [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'leave_request_id' => $payload['leave_request_id'] ?? null,
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
                $admin->notify(new LeaveApprovalWorkflowNotification($eventType, $payload));
                $this->sendWorkflowEmail($admin, $eventType, $payload);
            });
    }
}
