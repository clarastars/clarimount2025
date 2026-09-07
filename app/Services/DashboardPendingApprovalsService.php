<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\EmployeeEntitlementSettlement;
use App\Models\LeaveRequest;
use App\Models\SalaryCertificateRequest;
use App\Models\SalaryRun;
use App\Models\User;

class DashboardPendingApprovalsService
{
    private const PREVIEW_LIMIT = 5;

    private const CANDIDATE_LIMIT = 80;

    public function __construct(
        private LeaveApprovalService $leaveApprovalService,
        private LeaveTypeService $leaveTypeService,
        private SalaryCertificateApprovalService $salaryCertificateApprovalService,
        private EntitlementSettlementApprovalService $settlementApprovalService,
        private SalaryRunApprovalService $salaryRunApprovalService,
        private EmployeeUserRoleService $roleService,
    ) {}

    /**
     * @return array{
     *     leaves: array<string, mixed>,
     *     salary_certificates: array<string, mixed>,
     *     entitlement_settlements: array<string, mixed>,
     *     salary_runs: array<string, mixed>,
     *     total_count: int
     * }
     */
    public function forUser(User $user): array
    {
        $companyIds = $this->accessibleCompanyIds($user);

        $leaves = $this->pendingLeaves($user, $companyIds);
        $certificates = $this->pendingSalaryCertificates($user, $companyIds);
        $settlements = $this->pendingSettlements($user, $companyIds);
        $salaryRuns = $this->pendingSalaryRuns($user, $companyIds);

        return [
            'leaves' => $leaves,
            'salary_certificates' => $certificates,
            'entitlement_settlements' => $settlements,
            'salary_runs' => $salaryRuns,
            'total_count' => $leaves['count']
                + $certificates['count']
                + $settlements['count']
                + $salaryRuns['count'],
        ];
    }

    /**
     * @return list<int>
     */
    private function accessibleCompanyIds(User $user): array
    {
        if ($user->hasRole('super-admin')) {
            return Company::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $user->ownedCompanies()
            ->pluck('id')
            ->merge($user->accessibleCompanies()->pluck('companies.id'))
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $companyIds
     * @return array{visible: bool, count: int, preview: list<array<string, mixed>>, view_all_url: string|null}
     */
    private function pendingLeaves(User $user, array $companyIds): array
    {
        $visible = $this->userCanSeeLeaves($user);

        if (! $visible || $companyIds === []) {
            return $this->emptyBucket(visible: $visible);
        }

        $candidates = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->whereHas('employee', fn ($q) => $q->whereIn('company_id', $companyIds))
            ->with(['employee:id,first_name,father_name,last_name,company_id,department_id', 'employee.company:id,name_ar,name_en'])
            ->latest('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $items = [];

        foreach ($candidates as $leaveRequest) {
            $employee = $leaveRequest->employee;
            $company = $employee?->company;
            if ($employee === null || $company === null) {
                continue;
            }

            if (! $this->userCanActOnLeave($user, $company, $leaveRequest)) {
                continue;
            }

            $nextStep = $this->leaveApprovalService->hasActiveStepsForCompany($company)
                ? $this->leaveApprovalService->getNextPendingStep($leaveRequest)
                : null;

            $items[] = [
                'id' => (int) $leaveRequest->id,
                'type' => 'leave',
                'title' => $employee->full_name,
                'subtitle' => $this->leaveTypeService->labelForKey($leaveRequest->leave_type),
                'meta' => trim(sprintf(
                    '%s → %s · %s',
                    $leaveRequest->start_date?->format('Y-m-d') ?? '—',
                    $leaveRequest->end_date?->format('Y-m-d') ?? '—',
                    $company->name_ar ?: $company->name_en,
                )),
                'step_title' => $nextStep?->title,
                'url' => route('companies.leaves.index', $company),
                'created_at' => $leaveRequest->created_at?->toIso8601String(),
            ];
        }

        return $this->bucketFromItems($items, $items[0]['url'] ?? null);
    }

    /**
     * @param  list<int>  $companyIds
     * @return array{visible: bool, count: int, preview: list<array<string, mixed>>, view_all_url: string|null}
     */
    private function pendingSalaryCertificates(User $user, array $companyIds): array
    {
        $visible = $this->userCanSeeLeaves($user);

        if (! $visible || $companyIds === []) {
            return $this->emptyBucket(visible: $visible);
        }

        $candidates = SalaryCertificateRequest::query()
            ->where('status', SalaryCertificateRequest::STATUS_PENDING)
            ->whereHas('employee', fn ($q) => $q->whereIn('company_id', $companyIds))
            ->with(['employee:id,first_name,father_name,last_name,company_id,department_id', 'employee.company:id,name_ar,name_en'])
            ->latest('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $items = [];

        foreach ($candidates as $request) {
            $employee = $request->employee;
            $company = $employee?->company;
            if ($employee === null || $company === null) {
                continue;
            }

            if (! $this->userCanActOnSalaryCertificate($user, $company, $request)) {
                continue;
            }

            $nextStep = $this->salaryCertificateApprovalService->hasActiveStepsForCompany($company)
                ? $this->salaryCertificateApprovalService->getNextPendingStep($request)
                : null;

            $items[] = [
                'id' => (int) $request->id,
                'type' => 'salary_certificate',
                'title' => $employee->full_name,
                'subtitle' => (string) ($request->purpose ?: __('messages.dashboard.pending.salary_certificate_fallback')),
                'meta' => $company->name_ar ?: $company->name_en,
                'step_title' => $nextStep?->title,
                'url' => route('companies.salary-certificates.index', $company),
                'created_at' => $request->created_at?->toIso8601String(),
            ];
        }

        return $this->bucketFromItems($items, $items[0]['url'] ?? null);
    }

    /**
     * @param  list<int>  $companyIds
     * @return array{visible: bool, count: int, preview: list<array<string, mixed>>, view_all_url: string|null}
     */
    private function pendingSettlements(User $user, array $companyIds): array
    {
        $visible = $user->hasRole('super-admin')
            || $user->ownedCompanies()->exists()
            || $this->roleService->canInAnyAssignedTeam($user, 'employees.entitlements.approve');

        if (! $visible || $companyIds === []) {
            return $this->emptyBucket(visible: $visible);
        }

        $candidates = EmployeeEntitlementSettlement::query()
            ->where('status', EmployeeEntitlementSettlement::STATUS_PENDING)
            ->whereHas('employee', fn ($q) => $q->whereIn('company_id', $companyIds))
            ->with(['employee:id,first_name,father_name,last_name,company_id,department_id', 'employee.company:id,name_ar,name_en'])
            ->latest('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $items = [];

        foreach ($candidates as $settlement) {
            $employee = $settlement->employee;
            $company = $employee?->company;
            if ($employee === null || $company === null) {
                continue;
            }

            if (! $this->userCanActOnSettlement($user, $company, $settlement)) {
                continue;
            }

            $nextStep = $this->settlementApprovalService->hasActiveStepsForCompany($company)
                ? $this->settlementApprovalService->getNextPendingStep($settlement)
                : null;

            $items[] = [
                'id' => (int) $settlement->id,
                'type' => 'entitlement_settlement',
                'title' => $employee->full_name,
                'subtitle' => (string) ($settlement->reason ?: __('messages.dashboard.pending.settlement_fallback')),
                'meta' => trim(sprintf(
                    '%s · %s SAR · %s',
                    $settlement->settlement_date?->format('Y-m-d') ?? '—',
                    number_format((float) $settlement->net_due, 2),
                    $company->name_ar ?: $company->name_en,
                )),
                'step_title' => $nextStep?->title,
                'url' => route('employees.entitlement-settlement.show', [$employee, $settlement]),
                'created_at' => $settlement->created_at?->toIso8601String(),
            ];
        }

        return $this->bucketFromItems($items, null);
    }

    /**
     * @param  list<int>  $companyIds
     * @return array{visible: bool, count: int, preview: list<array<string, mixed>>, view_all_url: string|null}
     */
    private function pendingSalaryRuns(User $user, array $companyIds): array
    {
        $visible = $user->hasRole('super-admin')
            || $user->ownedCompanies()->exists()
            || $this->roleService->canInAnyAssignedTeam($user, 'salary-runs.approve');

        if (! $visible || $companyIds === []) {
            return $this->emptyBucket(visible: $visible);
        }

        $candidates = SalaryRun::query()
            ->whereIn('company_id', $companyIds)
            ->where('status', '!=', 'finalized')
            ->with(['company:id,name_ar,name_en'])
            ->latest('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $items = [];

        foreach ($candidates as $salaryRun) {
            $company = $salaryRun->company;
            if ($company === null) {
                continue;
            }

            $nextStep = $this->salaryRunApprovalService->getNextPendingStep($salaryRun);
            if ($nextStep === null) {
                continue;
            }

            if (! $this->salaryRunApprovalService->canUserApproveStep($user, $company, $salaryRun, $nextStep)) {
                continue;
            }

            $items[] = [
                'id' => (int) $salaryRun->id,
                'type' => 'salary_run',
                'title' => trim(sprintf('%s / %s', $salaryRun->month, $salaryRun->year)),
                'subtitle' => $company->name_ar ?: $company->name_en,
                'meta' => $salaryRun->label ?: __('messages.dashboard.pending.salary_run_fallback'),
                'step_title' => $nextStep->title,
                'url' => route('salary-runs.show', [$company, $salaryRun]),
                'created_at' => $salaryRun->created_at?->toIso8601String(),
            ];
        }

        $viewAll = $companyIds !== []
            ? route('salary-runs.index', $companyIds[0])
            : null;

        return $this->bucketFromItems($items, $viewAll);
    }

    private function userCanSeeLeaves(User $user): bool
    {
        return $user->hasRole('super-admin')
            || $user->ownedCompanies()->exists()
            || $this->roleService->canInAnyAssignedTeam($user, 'leaves.approve')
            || $this->roleService->canInAnyAssignedTeam($user, 'leaves.create');
    }

    private function userCanActOnLeave(User $user, Company $company, LeaveRequest $leaveRequest): bool
    {
        if ($this->leaveApprovalService->hasActiveStepsForCompany($company)) {
            $nextStep = $this->leaveApprovalService->getNextPendingStep($leaveRequest);

            return $nextStep !== null
                && $this->leaveApprovalService->canUserApproveStep($user, $company, $leaveRequest, $nextStep);
        }

        if ($user->hasRole('super-admin') || $user->ownedCompanies()->whereKey($company->id)->exists()) {
            return true;
        }

        return $this->roleService->canAccessEmployeeInCompanyDepartment(
            $user,
            'leaves.create',
            (int) $company->id,
            $this->roleService->departmentIdForEmployeeScope($leaveRequest->employee),
        );
    }

    private function userCanActOnSalaryCertificate(
        User $user,
        Company $company,
        SalaryCertificateRequest $request,
    ): bool {
        if ($this->salaryCertificateApprovalService->hasActiveStepsForCompany($company)) {
            $nextStep = $this->salaryCertificateApprovalService->getNextPendingStep($request);

            return $nextStep !== null
                && $this->salaryCertificateApprovalService->canUserApproveStep($user, $company, $request, $nextStep);
        }

        if ($user->hasRole('super-admin') || $user->ownedCompanies()->whereKey($company->id)->exists()) {
            return true;
        }

        return $this->roleService->canAccessEmployeeInCompanyDepartment(
            $user,
            'leaves.create',
            (int) $company->id,
            $this->roleService->departmentIdForEmployeeScope($request->employee),
        );
    }

    private function userCanActOnSettlement(
        User $user,
        Company $company,
        EmployeeEntitlementSettlement $settlement,
    ): bool {
        if (! $this->settlementApprovalService->hasActiveStepsForCompany($company)) {
            return $user->hasRole('super-admin')
                || $user->ownedCompanies()->whereKey($company->id)->exists()
                || $this->roleService->canAccessEmployeeInCompanyDepartment(
                    $user,
                    'employees.entitlements.settle',
                    (int) $company->id,
                    $this->roleService->departmentIdForEmployeeScope($settlement->employee),
                );
        }

        $nextStep = $this->settlementApprovalService->getNextPendingStep($settlement);

        return $nextStep !== null
            && $this->settlementApprovalService->canUserApproveStep($user, $company, $settlement, $nextStep);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{visible: bool, count: int, preview: list<array<string, mixed>>, view_all_url: string|null}
     */
    private function bucketFromItems(array $items, ?string $viewAllUrl): array
    {
        $collection = collect($items)->values();

        return [
            'visible' => true,
            'count' => $collection->count(),
            'preview' => $collection->take(self::PREVIEW_LIMIT)->values()->all(),
            'view_all_url' => $viewAllUrl,
        ];
    }

    /**
     * @return array{visible: bool, count: int, preview: list<array<string, mixed>>, view_all_url: string|null}
     */
    private function emptyBucket(bool $visible = false): array
    {
        return [
            'visible' => $visible,
            'count' => 0,
            'preview' => [],
            'view_all_url' => null,
        ];
    }
}
