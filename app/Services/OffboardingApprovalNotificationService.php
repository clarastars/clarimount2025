<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\OffboardingApprovalWorkflowMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeOffboardingCase;
use App\Models\EmployeeOffboardingItem;
use App\Models\OffboardingClearanceApprovalStep;
use App\Models\OffboardingItemApprovalStep;
use App\Models\User;
use App\Notifications\OffboardingApprovalWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OffboardingApprovalNotificationService
{
    public function __construct(
        private OffboardingItemApprovalService $itemApprovalService,
        private OffboardingClearanceApprovalService $clearanceApprovalService,
    ) {}

    public function notifyCaseStarted(
        EmployeeOffboardingCase $case,
        Company $company,
        User $actor,
    ): void {
        $case->loadMissing(['items', 'employee']);

        $payload = $this->buildBasePayload($case, $company, $actor);

        foreach ($this->getFullViewStakeholders($case, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'case_started', $payload);
        }

        foreach ($case->items as $item) {
            $this->notifyItemYourTurn($item, $company, $actor);
        }
    }

    public function notifyItemStepApproved(
        EmployeeOffboardingItem $item,
        Company $company,
        OffboardingItemApprovalStep $approvedStep,
        User $actor,
    ): void {
        $item->refresh();
        $item->loadMissing(['offboardingCase.employee']);

        $basePayload = [
            ...$this->buildBasePayload($item->offboardingCase, $company, $actor),
            'item_id' => $item->id,
            'item_title' => $item->title,
            'step_id' => $approvedStep->id,
            'step_title' => $approvedStep->title,
            'remaining_steps' => $this->itemApprovalService->remainingStepsCount($item),
        ];

        $nextStep = $this->itemApprovalService->getNextPendingStep($item);
        $stakeholders = $this->getItemStakeholders($item, $company);

        foreach ($stakeholders as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($nextStep !== null && $this->itemApprovalService->canUserApproveStep($user, $company, $item, $nextStep)) {
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

    public function notifyItemFinalized(
        EmployeeOffboardingItem $item,
        Company $company,
        User $actor,
    ): void {
        $item->loadMissing('offboardingCase.employee');
        $payload = [
            ...$this->buildBasePayload($item->offboardingCase, $company, $actor),
            'item_id' => $item->id,
            'item_title' => $item->title,
        ];

        foreach ($this->getItemStakeholders($item, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'item_finalized', $payload);
        }
    }

    public function notifyItemRejected(
        EmployeeOffboardingItem $item,
        Company $company,
        OffboardingItemApprovalStep $rejectedStep,
        User $actor,
        string $reason,
    ): void {
        $item->refresh();
        $item->loadMissing('offboardingCase.employee');

        $payload = [
            ...$this->buildBasePayload($item->offboardingCase, $company, $actor),
            'item_id' => $item->id,
            'item_title' => $item->title,
            'step_id' => $rejectedStep->id,
            'step_title' => $rejectedStep->title,
            'reason' => $reason,
            'after_rejection' => true,
        ];

        $firstStep = $this->itemApprovalService->getNextPendingStep($item);

        foreach ($this->getItemStakeholders($item, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($firstStep !== null && $this->itemApprovalService->canUserApproveStep($user, $company, $item, $firstStep)) {
                $this->send($user, 'your_turn', [
                    ...$payload,
                    'step_id' => $firstStep->id,
                    'step_title' => $firstStep->title,
                ]);

                continue;
            }

            $this->send($user, 'rejected', $payload);
        }
    }

    public function notifyClearanceStarted(
        EmployeeOffboardingCase $case,
        Company $company,
        User $actor,
    ): void {
        $case->loadMissing('employee');
        $firstStep = $this->clearanceApprovalService->getNextPendingStep($case);

        if ($firstStep === null) {
            return;
        }

        $payload = [
            ...$this->buildBasePayload($case, $company, $actor),
            'step_id' => $firstStep->id,
            'step_title' => $firstStep->title,
        ];

        foreach ($this->getClearanceStakeholders($case, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($this->clearanceApprovalService->canUserApproveStep($user, $company, $case, $firstStep)) {
                $this->send($user, 'your_turn', $payload);
            } else {
                $this->send($user, 'clearance_started', $payload);
            }
        }
    }

    public function notifyClearanceStepApproved(
        EmployeeOffboardingCase $case,
        Company $company,
        OffboardingClearanceApprovalStep $approvedStep,
        User $actor,
    ): void {
        $case->refresh();
        $case->loadMissing('employee');

        $basePayload = [
            ...$this->buildBasePayload($case, $company, $actor),
            'step_id' => $approvedStep->id,
            'step_title' => $approvedStep->title,
            'remaining_steps' => $this->clearanceApprovalService->remainingStepsCount($case),
        ];

        $nextStep = $this->clearanceApprovalService->getNextPendingStep($case);
        $stakeholders = $this->getClearanceStakeholders($case, $company);

        foreach ($stakeholders as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($nextStep !== null && $this->clearanceApprovalService->canUserApproveStep($user, $company, $case, $nextStep)) {
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

    public function notifyClearanceRejected(
        EmployeeOffboardingCase $case,
        Company $company,
        OffboardingClearanceApprovalStep $rejectedStep,
        User $actor,
        string $reason,
    ): void {
        $case->refresh();
        $case->loadMissing('employee');

        $payload = [
            ...$this->buildBasePayload($case, $company, $actor),
            'step_id' => $rejectedStep->id,
            'step_title' => $rejectedStep->title,
            'reason' => $reason,
            'after_rejection' => true,
        ];

        $firstStep = $this->clearanceApprovalService->getNextPendingStep($case);

        foreach ($this->getClearanceStakeholders($case, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($firstStep !== null && $this->clearanceApprovalService->canUserApproveStep($user, $company, $case, $firstStep)) {
                $this->send($user, 'your_turn', [
                    ...$payload,
                    'step_id' => $firstStep->id,
                    'step_title' => $firstStep->title,
                ]);

                continue;
            }

            $this->send($user, 'rejected', $payload);
        }
    }

    public function notifyCaseFinalized(
        EmployeeOffboardingCase $case,
        Company $company,
        User $actor,
    ): void {
        $case->loadMissing('employee');
        $payload = $this->buildBasePayload($case, $company, $actor);

        $stakeholders = $this->getClearanceStakeholders($case, $company)
            ->merge($this->getFullViewStakeholders($case, $company))
            ->unique('id');

        foreach ($stakeholders as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            $this->send($user, 'finalized', $payload);
        }
    }

    private function notifyItemYourTurn(
        EmployeeOffboardingItem $item,
        Company $company,
        User $actor,
    ): void {
        $item->loadMissing('offboardingCase.employee');
        $firstStep = $this->itemApprovalService->getNextPendingStep($item);

        if ($firstStep === null) {
            return;
        }

        $payload = [
            ...$this->buildBasePayload($item->offboardingCase, $company, $actor),
            'item_id' => $item->id,
            'item_title' => $item->title,
            'step_id' => $firstStep->id,
            'step_title' => $firstStep->title,
        ];

        foreach ($this->getItemStakeholders($item, $company) as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if ($this->itemApprovalService->canUserApproveStep($user, $company, $item, $firstStep)) {
                $this->send($user, 'your_turn', $payload);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function getItemStakeholders(EmployeeOffboardingItem $item, Company $company): Collection
    {
        $item->loadMissing('offboardingCase.employee');
        $case = $item->offboardingCase;
        $employee = $case?->employee;

        if ($case === null || $employee === null) {
            return collect();
        }

        $teamIds = $this->itemApprovalService->activeStepsForItem($item)
            ->pluck('team_id')
            ->filter()
            ->unique()
            ->values();

        return $this->usersForTeams($teamIds, $company, $employee, [
            'employees.offboarding.item-approve',
            'employees.offboarding.start',
            'employees.offboarding.view',
        ])->merge($this->getFullViewStakeholders($case, $company))->unique('id')->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function getClearanceStakeholders(EmployeeOffboardingCase $case, Company $company): Collection
    {
        $case->loadMissing('employee');
        $employee = $case->employee;

        if ($employee === null) {
            return collect();
        }

        $teamIds = $this->clearanceApprovalService->activeStepsForCompany($company)
            ->pluck('team_id')
            ->filter()
            ->unique()
            ->values();

        return $this->usersForTeams($teamIds, $company, $employee, [
            'employees.offboarding.clearance-approve',
            'employees.offboarding.start',
            'employees.offboarding.view',
        ])->merge($this->getFullViewStakeholders($case, $company))->unique('id')->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function getFullViewStakeholders(EmployeeOffboardingCase $case, Company $company): Collection
    {
        $case->loadMissing('employee');
        $employee = $case->employee;

        if ($employee === null) {
            return collect();
        }

        $userIds = collect([$company->owner_id, $case->started_by])->filter();
        $roleService = app(EmployeeUserRoleService::class);
        $departmentId = $roleService->departmentIdForEmployeeScope($employee);

        $candidates = User::query()
            ->where(function ($query) use ($company) {
                $query->whereHas('accessibleCompanies', fn ($q) => $q->where('companies.id', $company->id))
                    ->orWhereHas('ownedCompanies', fn ($q) => $q->where('id', $company->id));
            })
            ->pluck('id');

        $userIds = $userIds->merge($candidates);

        return User::query()
            ->whereIn('id', $userIds->unique()->values())
            ->get()
            ->filter(function (User $user) use ($company, $employee, $roleService, $departmentId) {
                if ($user->hasRole('super-admin')) {
                    return true;
                }

                if ($user->ownedCompanies()->whereKey($company->id)->exists()) {
                    return true;
                }

                return $roleService->canAnyAccessEmployeeInCompanyDepartment(
                    $user,
                    [
                        'employees.offboarding.start',
                        'employees.offboarding.view',
                        'employees.offboarding.clearance-approve',
                    ],
                    (int) $company->id,
                    $departmentId,
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, mixed>  $teamIds
     * @param  list<string>  $permissions
     * @return Collection<int, User>
     */
    private function usersForTeams(
        Collection $teamIds,
        Company $company,
        Employee $employee,
        array $permissions,
    ): Collection {
        $userIds = collect([$company->owner_id])->filter();
        $roleService = app(EmployeeUserRoleService::class);
        $departmentId = $roleService->departmentIdForEmployeeScope($employee);

        foreach ($teamIds as $teamId) {
            $memberIds = $roleService->userIdsForTeamInCompanyScoped(
                (int) $teamId,
                (int) $company->id,
                $departmentId,
            );

            if ($memberIds !== []) {
                $userIds = $userIds->merge($memberIds);
            }
        }

        return User::query()
            ->whereIn('id', $userIds->unique()->values())
            ->get()
            ->filter(function (User $user) use ($company, $employee, $roleService, $departmentId, $permissions) {
                if ($user->hasRole('super-admin') || $user->ownedCompanies()->whereKey($company->id)->exists()) {
                    return true;
                }

                return $roleService->canAnyAccessEmployeeInCompanyDepartment(
                    $user,
                    $permissions,
                    (int) $company->id,
                    $departmentId,
                );
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBasePayload(
        ?EmployeeOffboardingCase $case,
        Company $company,
        User $actor,
    ): array {
        $employee = $case?->employee;

        return [
            'offboarding_case_id' => $case?->id,
            'employee_id' => $employee?->id,
            'employee_name' => $employee?->full_name,
            'company_id' => $company->id,
            'company_name' => $company->name_ar ?: $company->name_en,
            'actor_name' => $actor->name,
            'url' => $case !== null && $employee !== null
                ? route('employees.offboarding.show', [$employee, $case], absolute: false)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(User $user, string $eventType, array $payload): void
    {
        $user->notify(new OffboardingApprovalWorkflowNotification($eventType, $payload));
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
            Mail::to($workEmail)->send(new OffboardingApprovalWorkflowMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send offboarding approval workflow email.', [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'offboarding_case_id' => $payload['offboarding_case_id'] ?? null,
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
                $admin->notify(new OffboardingApprovalWorkflowNotification($eventType, $payload));
                $this->sendWorkflowEmail($admin, $eventType, $payload);
            });
    }
}
