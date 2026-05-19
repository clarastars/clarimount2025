<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\SalaryRun;
use App\Models\SalaryRunApprovalStep;
use App\Mail\SalaryRunWorkflowMail;
use App\Models\User;
use App\Notifications\SalaryRunWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

class SalaryRunNotificationService
{
    public function __construct(
        private SalaryRunApprovalService $approvalService
    ) {}

    public function notifyStepApproved(
        SalaryRun $salaryRun,
        Company $company,
        SalaryRunApprovalStep $approvedStep,
        User $actor
    ): void {
        $salaryRun->refresh();
        $basePayload = $this->buildBasePayload($salaryRun, $company, $actor);
        $basePayload['step_id'] = $approvedStep->id;
        $basePayload['step_title'] = $approvedStep->title;

        $nextStep = $this->approvalService->getNextPendingStep($salaryRun);
        $stakeholders = $this->getWorkflowStakeholders($salaryRun, $company);

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
    }

    public function ensureYourTurnNotifications(SalaryRun $salaryRun, Company $company): void
    {
        $salaryRun->refresh();
        $nextStep = $this->approvalService->getNextPendingStep($salaryRun);

        if ($nextStep === null) {
            return;
        }

        $actor = User::query()->find($company->owner_id) ?? User::make(['name' => '']);
        $payload = [
            ...$this->buildBasePayload($salaryRun, $company, $actor),
            'step_id' => $nextStep->id,
            'step_title' => $nextStep->title,
        ];

        foreach ($this->getWorkflowStakeholders($salaryRun, $company) as $user) {
            if (! $this->userIsAssignedToApprovalStep($user, $nextStep)) {
                continue;
            }

            $alreadyNotified = $user->notifications()
                ->where('data->event_type', 'your_turn')
                ->where('data->salary_run_id', $salaryRun->id)
                ->where('data->step_id', $nextStep->id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $this->send($user, 'your_turn', $payload);
        }
    }

    private function userIsAssignedToApprovalStep(User $user, SalaryRunApprovalStep $step): bool
    {
        if ($step->team_id === null) {
            return false;
        }

        return app(EmployeeUserRoleService::class)->userBelongsToTeam($user, (int) $step->team_id);
    }

    public function notifyStepRejected(
        SalaryRun $salaryRun,
        Company $company,
        SalaryRunApprovalStep $rejectedStep,
        User $actor,
        string $reason
    ): void {
        $salaryRun->refresh();
        $payload = [
            ...$this->buildBasePayload($salaryRun, $company, $actor),
            'step_id' => $rejectedStep->id,
            'step_title' => $rejectedStep->title,
            'reason' => $reason,
        ];

        $firstStep = $this->approvalService->getNextPendingStep($salaryRun);

        foreach ($this->getWorkflowStakeholders($salaryRun, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($firstStep !== null && $this->userIsAssignedToApprovalStep($user, $firstStep)) {
                $this->send($user, 'your_turn', [
                    ...$payload,
                    'step_id' => $firstStep->id,
                    'step_title' => $firstStep->title,
                    'reason' => $reason,
                    'after_rejection' => true,
                ]);

                continue;
            }

            $this->send($user, 'rejected', $payload);
        }
    }

    public function notifyWorkflowFinalized(
        SalaryRun $salaryRun,
        Company $company,
        User $actor
    ): void {
        $payload = $this->buildBasePayload($salaryRun, $company, $actor);

        foreach ($this->getWorkflowStakeholders($salaryRun, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'finalized', $payload);
        }
    }

    public function notifyWorkflowStarted(SalaryRun $salaryRun, Company $company, User $actor): void
    {
        $firstStep = $this->approvalService->getNextPendingStep($salaryRun);
        if ($firstStep === null) {
            return;
        }

        $payload = [
            ...$this->buildBasePayload($salaryRun, $company, $actor),
            'step_id' => $firstStep->id,
            'step_title' => $firstStep->title,
        ];

        foreach ($this->getWorkflowStakeholders($salaryRun, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($this->userIsAssignedToApprovalStep($user, $firstStep)) {
                $this->send($user, 'your_turn', $payload);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function getWorkflowStakeholders(SalaryRun $salaryRun, Company $company): Collection
    {
        $teamIds = $this->approvalService->activeSteps()
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
            ->filter(fn (User $user) => $this->userCanReceiveSalaryRunNotifications($user, $company))
            ->values();
    }

    private function userCanReceiveSalaryRunNotifications(User $user, Company $company): bool
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
                $user->can('salary-runs.readonly')
                || $user->can('salary-runs.approve')
                || $user->can('salary-runs.create')
                || $user->can('salary-runs.delete')
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
    private function buildBasePayload(SalaryRun $salaryRun, Company $company, User $actor): array
    {
        return [
            'salary_run_id' => $salaryRun->id,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'year' => $salaryRun->year,
            'month' => $salaryRun->month,
            'actor_name' => $actor->name,
            'url' => route('salary-runs.show', [$company, $salaryRun->year, $salaryRun->month]),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(User $user, string $eventType, array $payload): void
    {
        $user->notify(new SalaryRunWorkflowNotification($eventType, $payload));
        $this->sendWorkflowEmail($user, $eventType, $payload);

        $this->broadcastToSuperAdmins($eventType, $payload, [$user->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWorkflowEmail(User $user, string $eventType, array $payload): void
    {
        $user->loadMissing('employee');

        $workEmail = trim((string) ($user->employee?->work_email ?? ''));
        if ($workEmail === '' || ! filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($workEmail)->send(new SalaryRunWorkflowMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send salary run workflow email.', [
                'user_id' => $user->id,
                'employee_id' => $user->employee?->id,
                'event_type' => $eventType,
                'salary_run_id' => $payload['salary_run_id'] ?? null,
                'work_email' => $workEmail,
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
                $admin->notify(new SalaryRunWorkflowNotification($eventType, $payload));
                $this->sendWorkflowEmail($admin, $eventType, $payload);
            });
    }
}
