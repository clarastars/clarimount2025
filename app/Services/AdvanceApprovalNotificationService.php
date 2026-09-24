<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\AdvanceApprovalWorkflowMail;
use App\Models\AdvanceApprovalStep;
use App\Models\AdvanceRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AdvanceApprovalWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdvanceApprovalNotificationService
{
    public function __construct(
        private AdvanceApprovalService $approvalService,
    ) {}

    public function notifyWorkflowStarted(
        AdvanceRequest $advanceRequest,
        Company $company,
        User $actor,
    ): void {
        $firstStep = $this->approvalService->getNextPendingStep($advanceRequest);

        if ($firstStep === null) {
            return;
        }

        $payload = [
            ...$this->buildBasePayload($advanceRequest, $company, $actor),
            'step_id' => $firstStep->id,
            'step_title' => $firstStep->title,
        ];

        foreach ($this->getWorkflowStakeholders($company, $advanceRequest->employee) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($this->userIsAssignedToApprovalStep($user, $firstStep, $advanceRequest->employee)) {
                $this->send($user, 'your_turn', $payload);
            }
        }
    }

    public function notifyStepApproved(
        AdvanceRequest $advanceRequest,
        Company $company,
        AdvanceApprovalStep $approvedStep,
        User $actor,
    ): void {
        $advanceRequest->refresh();
        $basePayload = $this->buildBasePayload($advanceRequest, $company, $actor);
        $basePayload['step_id'] = $approvedStep->id;
        $basePayload['step_title'] = $approvedStep->title;
        $basePayload['remaining_steps'] = $this->approvalService->remainingStepsCount($advanceRequest);

        $nextStep = $this->approvalService->getNextPendingStep($advanceRequest);
        $stakeholders = $this->getWorkflowStakeholders($company, $advanceRequest->employee);

        foreach ($stakeholders as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($nextStep !== null && $this->userIsAssignedToApprovalStep($user, $nextStep, $advanceRequest->employee)) {
                $this->send($user, 'your_turn', [
                    ...$basePayload,
                    'step_id' => $nextStep->id,
                    'step_title' => $nextStep->title,
                ]);

                continue;
            }

            $this->send($user, 'step_approved', $basePayload);
        }

        $this->notifyEmployeeProgress($advanceRequest, $basePayload, $nextStep !== null);
    }

    public function notifyWorkflowFinalized(
        AdvanceRequest $advanceRequest,
        Company $company,
        User $actor,
    ): void {
        $payload = $this->buildBasePayload($advanceRequest, $company, $actor);

        foreach ($this->getWorkflowStakeholders($company, $advanceRequest->employee) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'finalized', $payload);
        }

        $this->notifyEmployeeFinalized($advanceRequest, $payload);
    }

    public function notifyStepRejected(
        AdvanceRequest $advanceRequest,
        Company $company,
        AdvanceApprovalStep $rejectedStep,
        User $actor,
        string $reason,
    ): void {
        $advanceRequest->refresh();
        $payload = [
            ...$this->buildBasePayload($advanceRequest, $company, $actor),
            'step_id' => $rejectedStep->id,
            'step_title' => $rejectedStep->title,
            'reason' => $reason,
        ];

        foreach ($this->getWorkflowStakeholders($company, $advanceRequest->employee) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'rejected', $payload);
        }

        $this->notifyEmployeeWorkflowRejected($advanceRequest, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEmployeeProgress(
        AdvanceRequest $advanceRequest,
        array $payload,
        bool $hasMoreSteps,
    ): void {
        $portalUser = $this->resolveEmployeePortalUser($advanceRequest->employee);

        if ($portalUser === null) {
            return;
        }

        $eventType = $hasMoreSteps ? 'step_progress' : 'approved';
        $this->sendToEmployee($portalUser, $eventType, [
            ...$payload,
            'url' => route('employee.advances.index'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEmployeeFinalized(
        AdvanceRequest $advanceRequest,
        array $payload,
    ): void {
        $portalUser = $this->resolveEmployeePortalUser($advanceRequest->employee);

        if ($portalUser === null) {
            return;
        }

        $this->sendToEmployee($portalUser, 'approved', [
            ...$payload,
            'url' => route('employee.advances.index'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEmployeeWorkflowRejected(
        AdvanceRequest $advanceRequest,
        array $payload,
    ): void {
        $portalUser = $this->resolveEmployeePortalUser($advanceRequest->employee);

        if ($portalUser === null) {
            return;
        }

        $this->sendToEmployee($portalUser, 'workflow_rejected', [
            ...$payload,
            'url' => route('employee.advances.index'),
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
    public function getWorkflowStakeholders(Company $company, ?Employee $employee = null): Collection
    {
        $teamIds = $this->approvalService->activeStepsForCompany($company)
            ->pluck('team_id')
            ->filter()
            ->unique()
            ->values();

        $userIds = collect([$company->owner_id])->filter();
        $roleService = app(EmployeeUserRoleService::class);
        $departmentId = $roleService->departmentIdForEmployeeScope($employee);

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
            ->filter(fn (User $user) => $this->userCanReceiveWorkflowNotifications($user, $company, $employee))
            ->values();
    }

    private function userIsAssignedToApprovalStep(User $user, AdvanceApprovalStep $step, ?Employee $employee = null): bool
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

    private function userCanReceiveWorkflowNotifications(User $user, Company $company, ?Employee $employee = null): bool
    {
        $roleService = app(EmployeeUserRoleService::class);

        return $roleService->canAnyAccessEmployeeInCompanyDepartment(
            $user,
            ['advances.approve', 'advances.company.view', 'advances.create'],
            (int) $company->id,
            $roleService->departmentIdForEmployeeScope($employee)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBasePayload(
        AdvanceRequest $advanceRequest,
        Company $company,
        User $actor,
    ): array {
        $employee = $advanceRequest->employee;

        return [
            'advance_request_id' => $advanceRequest->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'company_id' => $company->id,
            'company_name' => $company->name_ar ?: $company->name_en,
            'amount' => number_format((float) $advanceRequest->amount, 2),
            'monthly_deduction' => number_format((float) $advanceRequest->monthly_deduction, 2),
            'months_count' => (int) $advanceRequest->months_count,
            'actor_name' => $actor->name,
            'url' => route('companies.advances.index', $company),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(User $user, string $eventType, array $payload): void
    {
        $user->notify(new AdvanceApprovalWorkflowNotification($eventType, $payload));
        $this->sendWorkflowEmail($user, $eventType, $payload);
        $this->broadcastToSuperAdmins($eventType, $payload, [$user->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendToEmployee(User $user, string $eventType, array $payload): void
    {
        $user->notify(new AdvanceApprovalWorkflowNotification($eventType, $payload));
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
            Mail::to($workEmail)->send(new AdvanceApprovalWorkflowMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send advance approval workflow email.', [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'advance_request_id' => $payload['advance_request_id'] ?? null,
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
            Mail::to($email)->send(new AdvanceApprovalWorkflowMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send advance workflow email to employee.', [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'advance_request_id' => $payload['advance_request_id'] ?? null,
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
                $admin->notify(new AdvanceApprovalWorkflowNotification($eventType, $payload));
                $this->sendWorkflowEmail($admin, $eventType, $payload);
            });
    }
}
