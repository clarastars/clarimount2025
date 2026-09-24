<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\AdvanceRequestDecisionMail;
use App\Mail\AdvanceRequestSubmittedMail;
use App\Models\AdvanceRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AdvanceRequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdvanceRequestNotificationService
{
    public function notifySubmitted(AdvanceRequest $advanceRequest): void
    {
        $advanceRequest->loadMissing(['employee.company']);
        $employee = $advanceRequest->employee;
        $company = $employee->company;

        if ($company === null) {
            return;
        }

        $payload = $this->buildPayload($advanceRequest, $company);

        foreach ($this->getRecipientsForCompany($company, $employee) as $user) {
            $this->send($user, 'submitted', $payload);
        }
    }

    public function notifyEmployeeApproved(AdvanceRequest $advanceRequest): void
    {
        $this->notifyEmployeeOfDecision($advanceRequest, 'approved');
    }

    public function notifyEmployeeRejected(AdvanceRequest $advanceRequest): void
    {
        $this->notifyEmployeeOfDecision($advanceRequest, 'rejected');
    }

    private function notifyEmployeeOfDecision(AdvanceRequest $advanceRequest, string $eventType): void
    {
        $advanceRequest->loadMissing(['employee.company', 'employee.user']);
        $employee = $advanceRequest->employee;
        $company = $employee->company;

        if ($company === null) {
            return;
        }

        $payload = [
            ...$this->buildPayload($advanceRequest, $company),
            'review_notes' => $advanceRequest->review_notes,
            'url' => route('employee.advances.index'),
        ];

        $portalUser = $employee->user;
        if ($portalUser !== null) {
            $portalUser->notify(new AdvanceRequestNotification($eventType, $payload));
        }

        $email = $this->resolveEmployeePortalEmail($employee);
        if ($email === null) {
            return;
        }

        try {
            Mail::to($email)->send(new AdvanceRequestDecisionMail($employee, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send advance decision email to employee.', [
                'employee_id' => $employee->id,
                'advance_request_id' => $advanceRequest->id,
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
    public function getRecipientsForCompany(Company $company, ?Employee $employee = null): Collection
    {
        $roleService = app(EmployeeUserRoleService::class);
        $departmentId = $roleService->departmentIdForEmployeeScope($employee);

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
            ->merge($roleService->userIdsAssignedToDepartment($departmentId))
            ->filter()
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->filter(fn (User $user) => $this->userCanReceiveNotifications($user, $company, $employee))
            ->values();
    }

    private function userCanReceiveNotifications(User $user, Company $company, ?Employee $employee = null): bool
    {
        $roleService = app(EmployeeUserRoleService::class);

        return $roleService->canAnyAccessEmployeeInCompanyDepartment(
            $user,
            ['advances.create', 'advances.approve', 'advances.company.view'],
            (int) $company->id,
            $roleService->departmentIdForEmployeeScope($employee)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(AdvanceRequest $advanceRequest, Company $company): array
    {
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
            'url' => route('companies.advances.index', $company),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(User $user, string $eventType, array $payload): void
    {
        $user->notify(new AdvanceRequestNotification($eventType, $payload));
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
            Mail::to($email)->send(new AdvanceRequestSubmittedMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send advance request email.', [
                'user_id' => $user->id,
                'advance_request_id' => $payload['advance_request_id'] ?? null,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
