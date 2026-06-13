<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\LeaveRequestDecisionMail;
use App\Mail\LeaveRequestSubmittedMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

class LeaveRequestNotificationService
{
    public function notifySubmitted(LeaveRequest $leaveRequest): void
    {
        $employee = $leaveRequest->employee;
        $company = $employee->company;

        if ($company === null) {
            return;
        }

        $payload = $this->buildPayload($leaveRequest, $company);

        foreach ($this->getRecipientsForCompany($company) as $user) {
            $this->send($user, 'submitted', $payload);
        }
    }

    public function notifyEmployeeApproved(LeaveRequest $leaveRequest): void
    {
        $this->notifyEmployeeOfDecision($leaveRequest, 'approved');
    }

    public function notifyEmployeeRejected(LeaveRequest $leaveRequest): void
    {
        $this->notifyEmployeeOfDecision($leaveRequest, 'rejected');
    }

    private function notifyEmployeeOfDecision(LeaveRequest $leaveRequest, string $eventType): void
    {
        $leaveRequest->loadMissing(['employee.company', 'employee.user']);
        $employee = $leaveRequest->employee;
        $company = $employee->company;

        if ($company === null) {
            return;
        }

        $payload = [
            ...$this->buildPayload($leaveRequest, $company),
            'review_notes' => $leaveRequest->review_notes,
            'url' => route('employee.leaves.index'),
        ];

        $portalUser = $employee->user;
        if ($portalUser !== null) {
            $portalUser->notify(new LeaveRequestNotification($eventType, $payload));
        }

        $email = $this->resolveEmployeePortalEmail($employee);
        if ($email === null) {
            return;
        }

        try {
            Mail::to($email)->send(new LeaveRequestDecisionMail($employee, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send leave request decision email to employee.', [
                'employee_id' => $employee->id,
                'leave_request_id' => $leaveRequest->id,
                'event_type' => $eventType,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveEmployeePortalEmail(Employee $employee): ?string
    {
        $employee->loadMissing('user');

        $email = trim((string) ($employee->user?->email ?? $employee->work_email ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    /**
     * @return Collection<int, User>
     */
    public function getRecipientsForCompany(Company $company): Collection
    {
        $candidateIds = User::query()
            ->where(function ($query) use ($company) {
                $query->whereHas('accessibleCompanies', function ($companyQuery) use ($company) {
                    $companyQuery->where('companies.id', $company->id);
                })->orWhereHas('ownedCompanies', function ($companyQuery) use ($company) {
                    $companyQuery->where('id', $company->id);
                });
            })
            ->pluck('id');

        $userIds = collect([$company->owner_id])
            ->merge($candidateIds)
            ->filter()
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->filter(fn (User $user) => $this->userCanReceiveLeaveRequestNotifications($user, $company))
            ->values();
    }

    private function userCanReceiveLeaveRequestNotifications(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($company->id)->exists()) {
            return true;
        }

        $teamIds = app(EmployeeUserRoleService::class)->assignedTeamIdsFor($user);

        if ($teamIds === []) {
            return false;
        }

        foreach ($teamIds as $teamId) {
            $this->refreshUserPermissionContext($user, $teamId);

            if ($user->can('leaves.requests.receive-email')) {
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
    private function buildPayload(LeaveRequest $leaveRequest, Company $company): array
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
            'url' => route('companies.leaves.index', $company),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(User $user, string $eventType, array $payload): void
    {
        $user->notify(new LeaveRequestNotification($eventType, $payload));
        $this->sendEmail($user, $eventType, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendEmail(User $user, string $eventType, array $payload): void
    {
        $user->loadMissing('employee');

        $email = trim((string) ($user->employee?->work_email ?? $user->email ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($email)->send(new LeaveRequestSubmittedMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send leave request email.', [
                'user_id' => $user->id,
                'leave_request_id' => $payload['leave_request_id'] ?? null,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
