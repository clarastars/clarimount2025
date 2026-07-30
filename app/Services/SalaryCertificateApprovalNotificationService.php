<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\SalaryCertificateApprovalWorkflowMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveApprovalStep;
use App\Models\SalaryCertificateRequest;
use App\Models\User;
use App\Notifications\SalaryCertificateApprovalWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SalaryCertificateApprovalNotificationService
{
    public function __construct(
        private SalaryCertificateApprovalService $approvalService,
        private LeaveApprovalService $leaveApprovalService,
    ) {}

    public function notifyWorkflowStarted(
        SalaryCertificateRequest $certificateRequest,
        Company $company,
        User $actor,
    ): void {
        $firstStep = $this->approvalService->getNextPendingStep($certificateRequest);

        if ($firstStep === null) {
            return;
        }

        $payload = [
            ...$this->buildBasePayload($certificateRequest, $company, $actor),
            'step_id' => $firstStep->id,
            'step_title' => $firstStep->title,
        ];

        foreach ($this->getWorkflowStakeholders($company, $certificateRequest->employee) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($this->userIsAssignedToApprovalStep($user, $firstStep)) {
                $this->send($user, 'your_turn', $payload);
            }
        }
    }

    public function notifyStepApproved(
        SalaryCertificateRequest $certificateRequest,
        Company $company,
        LeaveApprovalStep $approvedStep,
        User $actor,
    ): void {
        $certificateRequest->refresh();
        $basePayload = $this->buildBasePayload($certificateRequest, $company, $actor);
        $basePayload['step_id'] = $approvedStep->id;
        $basePayload['step_title'] = $approvedStep->title;
        $basePayload['remaining_steps'] = $this->approvalService->remainingStepsCount($certificateRequest);

        $nextStep = $this->approvalService->getNextPendingStep($certificateRequest);
        $stakeholders = $this->getWorkflowStakeholders($company, $certificateRequest->employee);

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

        $this->notifyEmployeeProgress($certificateRequest, $basePayload, $nextStep !== null);
    }

    public function notifyWorkflowFinalized(
        SalaryCertificateRequest $certificateRequest,
        Company $company,
        User $actor,
    ): void {
        $payload = $this->buildBasePayload($certificateRequest, $company, $actor);

        foreach ($this->getWorkflowStakeholders($company, $certificateRequest->employee) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'finalized', $payload);
        }

        $this->notifyEmployeeFinalized($certificateRequest, $payload);
    }

    public function notifyStepRejected(
        SalaryCertificateRequest $certificateRequest,
        Company $company,
        LeaveApprovalStep $rejectedStep,
        User $actor,
        string $reason,
    ): void {
        $certificateRequest->refresh();
        $payload = [
            ...$this->buildBasePayload($certificateRequest, $company, $actor),
            'step_id' => $rejectedStep->id,
            'step_title' => $rejectedStep->title,
            'reason' => $reason,
        ];

        $firstStep = $this->approvalService->getNextPendingStep($certificateRequest);

        foreach ($this->getWorkflowStakeholders($company, $certificateRequest->employee) as $user) {
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

        $this->notifyEmployeeWorkflowRejected($certificateRequest, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEmployeeProgress(
        SalaryCertificateRequest $certificateRequest,
        array $payload,
        bool $hasMoreSteps,
    ): void {
        $portalUser = $this->resolveEmployeePortalUser($certificateRequest->employee);

        if ($portalUser === null) {
            return;
        }

        $eventType = $hasMoreSteps ? 'step_progress' : 'completed';
        $this->sendToEmployee($portalUser, $eventType, [
            ...$payload,
            'url' => route('employee.salary-certificates.index'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEmployeeFinalized(
        SalaryCertificateRequest $certificateRequest,
        array $payload,
    ): void {
        $portalUser = $this->resolveEmployeePortalUser($certificateRequest->employee);

        if ($portalUser === null) {
            return;
        }

        $this->sendToEmployee($portalUser, 'completed', [
            ...$payload,
            'url' => route('employee.salary-certificates.index'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyEmployeeWorkflowRejected(
        SalaryCertificateRequest $certificateRequest,
        array $payload,
    ): void {
        $portalUser = $this->resolveEmployeePortalUser($certificateRequest->employee);

        if ($portalUser === null) {
            return;
        }

        $this->sendToEmployee($portalUser, 'workflow_rejected', [
            ...$payload,
            'url' => route('employee.salary-certificates.index'),
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
        $teamIds = $this->leaveApprovalService->activeStepsForCompany($company)
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
            ->filter(fn (User $user) => $this->userCanReceiveWorkflowNotifications($user, $company, $employee))
            ->values();
    }

    private function userIsAssignedToApprovalStep(User $user, LeaveApprovalStep $step): bool
    {
        if ($step->team_id === null) {
            return false;
        }

        return app(EmployeeUserRoleService::class)->userBelongsToTeam($user, (int) $step->team_id);
    }

    private function userCanReceiveWorkflowNotifications(User $user, Company $company, ?Employee $employee = null): bool
    {
        $roleService = app(EmployeeUserRoleService::class);

        return $roleService->canAnyAccessEmployeeInCompanyDepartment(
            $user,
            ['leaves.approve', 'leaves.company.view', 'leaves.create'],
            (int) $company->id,
            $roleService->departmentIdForEmployeeScope($employee)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBasePayload(
        SalaryCertificateRequest $certificateRequest,
        Company $company,
        User $actor,
    ): array {
        $employee = $certificateRequest->employee;

        return [
            'salary_certificate_request_id' => $certificateRequest->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'company_id' => $company->id,
            'company_name' => $company->name_ar ?: $company->name_en,
            'purpose' => $certificateRequest->purpose,
            'addressed_to' => $certificateRequest->addressed_to,
            'language' => $certificateRequest->language,
            'actor_name' => $actor->name,
            'url' => route('companies.salary-certificates.index', $company),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(User $user, string $eventType, array $payload): void
    {
        $user->notify(new SalaryCertificateApprovalWorkflowNotification($eventType, $payload));
        $this->sendWorkflowEmail($user, $eventType, $payload);
        $this->broadcastToSuperAdmins($eventType, $payload, [$user->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendToEmployee(User $user, string $eventType, array $payload): void
    {
        $user->notify(new SalaryCertificateApprovalWorkflowNotification($eventType, $payload));
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
            Mail::to($workEmail)->send(new SalaryCertificateApprovalWorkflowMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send salary certificate approval workflow email.', [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'salary_certificate_request_id' => $payload['salary_certificate_request_id'] ?? null,
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
            Mail::to($email)->send(new SalaryCertificateApprovalWorkflowMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send salary certificate workflow email to employee.', [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'salary_certificate_request_id' => $payload['salary_certificate_request_id'] ?? null,
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
                $admin->notify(new SalaryCertificateApprovalWorkflowNotification($eventType, $payload));
                $this->sendWorkflowEmail($admin, $eventType, $payload);
            });
    }
}
