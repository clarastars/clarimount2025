<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\SalaryCertificateRequestDecisionMail;
use App\Mail\SalaryCertificateRequestSubmittedMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryCertificateRequest;
use App\Models\User;
use App\Notifications\SalaryCertificateRequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

class SalaryCertificateRequestNotificationService
{
    public function notifySubmitted(SalaryCertificateRequest $certificateRequest): void
    {
        $employee = $certificateRequest->employee;
        $company = $employee->company;

        if ($company === null) {
            return;
        }

        $payload = $this->buildPayload($certificateRequest, $company);

        foreach ($this->getRecipientsForCompany($company) as $user) {
            $this->send($user, 'submitted', $payload);
        }
    }

    public function notifyEmployeeCompleted(SalaryCertificateRequest $certificateRequest): void
    {
        $this->notifyEmployeeOfDecision($certificateRequest, 'completed');
    }

    public function notifyEmployeeRejected(SalaryCertificateRequest $certificateRequest): void
    {
        $this->notifyEmployeeOfDecision($certificateRequest, 'rejected');
    }

    private function notifyEmployeeOfDecision(SalaryCertificateRequest $certificateRequest, string $eventType): void
    {
        $certificateRequest->loadMissing(['employee.company', 'employee.user']);
        $employee = $certificateRequest->employee;
        $company = $employee->company;

        if ($company === null) {
            return;
        }

        $payload = [
            ...$this->buildPayload($certificateRequest, $company),
            'review_notes' => $certificateRequest->review_notes,
            'url' => route('employee.salary-certificates.index'),
        ];

        $portalUser = $employee->user;
        if ($portalUser !== null) {
            $portalUser->notify(new SalaryCertificateRequestNotification($eventType, $payload));
        }

        $email = $this->resolveEmployeePortalEmail($employee);
        if ($email === null) {
            return;
        }

        try {
            Mail::to($email)->send(new SalaryCertificateRequestDecisionMail($employee, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send salary certificate decision email to employee.', [
                'employee_id' => $employee->id,
                'salary_certificate_request_id' => $certificateRequest->id,
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
            ->filter(fn (User $user) => $this->userCanReceiveNotifications($user, $company))
            ->values();
    }

    private function userCanReceiveNotifications(User $user, Company $company): bool
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
    private function buildPayload(SalaryCertificateRequest $certificateRequest, Company $company): array
    {
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
            'url' => route('companies.salary-certificates.index', $company),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(User $user, string $eventType, array $payload): void
    {
        $user->notify(new SalaryCertificateRequestNotification($eventType, $payload));
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
            Mail::to($email)->send(new SalaryCertificateRequestSubmittedMail($user, $eventType, $payload));
        } catch (\Throwable $exception) {
            Log::error('Failed to send salary certificate request email.', [
                'user_id' => $user->id,
                'salary_certificate_request_id' => $payload['salary_certificate_request_id'] ?? null,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
