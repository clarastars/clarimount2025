<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeEntitlementSettlement;
use App\Models\EmployeeOffboardingCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Services\EmployeeUserRoleService;
use App\Services\EntitlementSettlementApprovalService;
use App\Services\OffboardingClearanceApprovalService;
use App\Services\OffboardingItemApprovalService;
use Illuminate\Support\Collection;

trait AuthorizesEmployeeAccess
{
    /**
     * @return array<int>
     */
    protected function userAccessibleCompanyIds(User $user): array
    {
        return $user->ownedCompanies()
            ->pluck('id')
            ->merge(
                $user->accessibleCompanies()->pluck('companies.id')
            )
            ->merge(
                $this->roleService()->companyIdsWhereCan($user, $this->employeeViewPermissions())
            )
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Permissions that unlock the employees directory (list/filter/search) and general profile access.
     * Feature permissions like entitlement settlement must NOT be listed here.
     *
     * @return array<int, string>
     */
    protected function employeeViewPermissions(): array
    {
        return [
            'employees.readonly',
            'employees.manage',
        ];
    }

    protected function roleService(): EmployeeUserRoleService
    {
        return app(EmployeeUserRoleService::class);
    }

    protected function employeeQueryableCompanyIds(User $user): Collection
    {
        if ($user->hasRole('super-admin')) {
            return Company::query()->pluck('id');
        }

        $ownedIds = $user->ownedCompanies()->pluck('id');
        if ($ownedIds->isNotEmpty()) {
            return $ownedIds;
        }

        return collect($this->roleService()->companyIdsWhereCan($user, $this->employeeViewPermissions()));
    }

    /**
     * @return Collection<int, int>
     */
    protected function employeeManageableCompanyIds(User $user): Collection
    {
        if ($user->hasRole('super-admin')) {
            return Company::query()->pluck('id')->map(fn ($id): int => (int) $id);
        }

        $ownedIds = $user->ownedCompanies()->pluck('id')->map(fn ($id): int => (int) $id);
        if ($ownedIds->isNotEmpty()) {
            return $ownedIds;
        }

        return collect($this->roleService()->companyIdsWhereCan($user, ['employees.manage']))
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    /**
     * Companies available when creating/editing an employee's company field.
     * Includes all companies when the user has employees.assign-any-company.
     *
     * @return Collection<int, int>
     */
    protected function employeeAssignableCompanyIds(User $user): Collection
    {
        if ($this->canAssignAnyCompany($user)) {
            return Company::query()->pluck('id')->map(fn ($id): int => (int) $id);
        }

        return $this->employeeManageableCompanyIds($user)->map(fn ($id): int => (int) $id);
    }

    protected function canAssignAnyCompany(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.assign-any-company');
    }

    protected function canViewEmployees(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        foreach ($this->employeeViewPermissions() as $permission) {
            if ($this->roleService()->canInAnyAssignedTeam($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    protected function canViewEmployeeExpiryDocuments(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.expiry.view');
    }

    /**
     * Companies whose employee expiry documents this user may see.
     *
     * @return Collection<int, int>
     */
    protected function employeeExpiryCompanyIds(User $user): Collection
    {
        if ($user->hasRole('super-admin')) {
            return Company::query()->pluck('id')->map(fn ($id): int => (int) $id);
        }

        $ownedIds = $user->ownedCompanies()->pluck('id')->map(fn ($id): int => (int) $id);
        if ($ownedIds->isNotEmpty()) {
            return $ownedIds->values();
        }

        return collect($this->roleService()->companyIdsWhereCan($user, ['employees.expiry.view']));
    }

    protected function canSyncEmployeeFingerprintMonth(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'attendance.fingerprint-month.sync',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canViewEmployeeAuditLog(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'employees.audit-log.view',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canExcludeEmployeeFromSalary(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'employees.exclude-from-salary',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canExportEmployeeProfile(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.export-profile');
    }

    protected function canExportEmployeeProfileForEmployee(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'employees.export-profile',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canExportCompanyEmployeeProfiles(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.export-company-profile');
    }

    protected function canExportCompanyEmployeeProfilesForCompany(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($company->id)->exists()) {
            return true;
        }

        if ($this->roleService()->canForCompany($user, 'employees.export-company-profile', (int) $company->id)) {
            return true;
        }

        return $this->roleService()->canAccessCompanyViaDepartmentScope(
            $user,
            (int) $company->id,
            ['employees.export-company-profile'],
        );
    }

    protected function canUpdateEmployeeCustody(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.custody.update');
    }

    protected function canUpdateEmployeeCustodyForEmployee(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'employees.custody.update',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canSettleEmployeeEntitlements(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.entitlements.settle');
    }

    protected function canSettleEmployeeEntitlementsForEmployee(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'employees.entitlements.settle',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canApproveEmployeeEntitlements(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.entitlements.approve');
    }

    protected function canViewEntitlementSettlementForEmployee(User $user, Employee $employee): bool
    {
        if ($this->canSettleEmployeeEntitlementsForEmployee($user, $employee)) {
            return true;
        }

        if ($user->hasRole('super-admin') || $user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        // Chain approvers may only have entitlements.approve (not employees.readonly/manage).
        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'employees.entitlements.approve',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function abortUnlessCanViewEntitlementSettlementForEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canViewEntitlementSettlementForEmployee($user, $employee), 403);
    }

    protected function canViewEntitlementSettlement(
        User $user,
        Employee $employee,
        EmployeeEntitlementSettlement $settlement,
    ): bool {
        if ($this->canViewEntitlementSettlementForEmployee($user, $employee)) {
            return true;
        }

        $employee->loadMissing('company');
        $company = $employee->company;

        if ($company === null) {
            return false;
        }

        $approvalService = app(EntitlementSettlementApprovalService::class);

        foreach ($approvalService->activeStepsForCompany($company) as $step) {
            if ($approvalService->canUserApproveStep($user, $company, $settlement, $step)) {
                return true;
            }
        }

        if ($settlement->stepApprovals()->where('approved_by', $user->id)->exists()) {
            return true;
        }

        return (int) $settlement->created_by === (int) $user->id;
    }

    protected function abortUnlessCanViewEntitlementSettlement(
        User $user,
        Employee $employee,
        EmployeeEntitlementSettlement $settlement,
    ): void {
        abort_unless($this->canViewEntitlementSettlement($user, $employee, $settlement), 403);
    }

    protected function canStartEmployeeOffboarding(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.offboarding.start');
    }

    protected function canStartEmployeeOffboardingForEmployee(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'employees.offboarding.start',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function abortUnlessCanStartEmployeeOffboarding(User $user, Employee $employee): void
    {
        abort_unless($this->canStartEmployeeOffboardingForEmployee($user, $employee), 403);
    }

    protected function canSeeFullOffboardingCase(User $user, Employee $employee): bool
    {
        if ($this->canStartEmployeeOffboardingForEmployee($user, $employee)) {
            return true;
        }

        if ($user->hasRole('super-admin') || $user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAnyAccessEmployeeInCompanyDepartment(
            $user,
            [
                'employees.offboarding.view',
                'employees.offboarding.clearance-approve',
            ],
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canViewOffboardingCase(
        User $user,
        Employee $employee,
        EmployeeOffboardingCase $case,
    ): bool {
        if ($this->canSeeFullOffboardingCase($user, $employee)) {
            return true;
        }

        $employee->loadMissing('company');
        $company = $employee->company;

        if ($company === null) {
            return false;
        }

        if ((int) $case->started_by === (int) $user->id) {
            return true;
        }

        $case->loadMissing('items');

        $itemApproval = app(OffboardingItemApprovalService::class);
        if ($itemApproval->userCanActOnAnyPendingItemStep($user, $company, $case)) {
            return true;
        }

        foreach ($case->items as $item) {
            if ($item->stepApprovals()->where('approved_by', $user->id)->exists()) {
                return true;
            }
        }

        $clearanceApproval = app(OffboardingClearanceApprovalService::class);
        if ($case->isPendingClearance()) {
            $next = $clearanceApproval->getNextPendingStep($case);
            if ($next !== null && $clearanceApproval->canUserApproveStep($user, $company, $case, $next)) {
                return true;
            }
        }

        return $case->clearanceStepApprovals()->where('approved_by', $user->id)->exists();
    }

    protected function abortUnlessCanViewOffboardingCase(
        User $user,
        Employee $employee,
        EmployeeOffboardingCase $case,
    ): void {
        abort_unless($this->canViewOffboardingCase($user, $employee, $case), 403);
    }

    protected function canManageEmployees(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.manage');
    }

    protected function canAssignAnyDepartment(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.assign-any-department');
    }

    protected function canManageTeamRoleAssignments(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.team-roles.assign')
            || $this->roleService()->canInAnyAssignedTeam($user, 'employees.team-roles.assign-any-company');
    }

    protected function canAssignAnyCompanyForTeamRoles(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.team-roles.assign-any-company');
    }

    /**
     * Companies the acting user may scope when assigning team roles (owned + role-access companies).
     *
     * @return array<int, int>
     */
    protected function roleAssignableCompanyIds(User $user): array
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

    protected function canManageEmployee(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'employees.manage',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canViewCompanyLeaves(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'leaves.company.view')
            || $this->roleService()->canInAnyAssignedTeam($user, 'leaves.approve')
            || $this->roleService()->canInAnyAssignedTeam($user, 'leaves.create');
    }

    protected function canCreateLeaves(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'leaves.create');
    }

    protected function canCreateLeaveForEmployee(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'leaves.create',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canViewCompanyAdvances(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        foreach ($this->advanceWorkflowAccessPermissions() as $permission) {
            if ($this->roleService()->canInAnyAssignedTeam($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    protected function canCreateAdvances(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'advances.create');
    }

    protected function canApproveAdvances(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'advances.approve')
            || $this->roleService()->canInAnyAssignedTeam($user, 'advances.create');
    }

    /**
     * Permissions that allow seeing/acting on advance requests for an employee.
     *
     * @return array<int, string>
     */
    protected function advanceWorkflowAccessPermissions(): array
    {
        return [
            'advances.approve',
            'advances.company.view',
            'advances.create',
        ];
    }

    protected function canAccessEmployeeForAdvanceWorkflow(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAnyAccessEmployeeInCompanyDepartment(
            $user,
            $this->advanceWorkflowAccessPermissions(),
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canAccessCompanyAdvances(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($company->id)->exists()) {
            return true;
        }

        $permissions = $this->advanceWorkflowAccessPermissions();

        if ($this->roleService()->canAnyForCompany($user, $permissions, (int) $company->id)) {
            return true;
        }

        return $this->roleService()->canAccessCompanyViaDepartmentScope(
            $user,
            (int) $company->id,
            $permissions,
        );
    }

    protected function abortUnlessCanViewCompanyAdvances(User $user): void
    {
        abort_unless($this->canViewCompanyAdvances($user), 403);
    }

    protected function abortUnlessCanCreateAdvances(User $user): void
    {
        abort_unless($this->canCreateAdvances($user), 403);
    }

    protected function abortUnlessCanAccessCompanyAdvances(User $user, Company $company): void
    {
        abort_unless($this->canAccessCompanyAdvances($user, $company), 403);
    }

    /**
     * Permissions that allow viewing company entitlement settlements (all statuses).
     *
     * @return array<int, string>
     */
    protected function entitlementSettlementWorkflowAccessPermissions(): array
    {
        return [
            'employees.entitlements.approve',
            'employees.entitlements.settle',
        ];
    }

    protected function canViewCompanyEntitlementSettlements(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        foreach ($this->entitlementSettlementWorkflowAccessPermissions() as $permission) {
            if ($this->roleService()->canInAnyAssignedTeam($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    protected function canAccessCompanyEntitlementSettlements(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($company->id)->exists()) {
            return true;
        }

        $permissions = $this->entitlementSettlementWorkflowAccessPermissions();

        if ($this->roleService()->canAnyForCompany($user, $permissions, (int) $company->id)) {
            return true;
        }

        return $this->roleService()->canAccessCompanyViaDepartmentScope(
            $user,
            (int) $company->id,
            $permissions,
        );
    }

    protected function abortUnlessCanViewCompanyEntitlementSettlements(User $user): void
    {
        abort_unless($this->canViewCompanyEntitlementSettlements($user), 403);
    }

    protected function abortUnlessCanAccessCompanyEntitlementSettlements(User $user, Company $company): void
    {
        abort_unless($this->canAccessCompanyEntitlementSettlements($user, $company), 403);
    }

    /**
     * Permissions that allow seeing/acting on leave & salary-certificate requests for an employee.
     *
     * @return array<int, string>
     */
    protected function leaveWorkflowAccessPermissions(): array
    {
        return [
            'leaves.approve',
            'leaves.company.view',
            'leaves.create',
        ];
    }

    protected function canAccessEmployeeForLeaveWorkflow(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return $this->roleService()->canAnyAccessEmployeeInCompanyDepartment(
            $user,
            $this->leaveWorkflowAccessPermissions(),
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        );
    }

    protected function canAccessCompanyLeaves(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($company->id)->exists()) {
            return true;
        }

        $permissions = $this->leaveWorkflowAccessPermissions();

        if ($this->roleService()->canAnyForCompany($user, $permissions, (int) $company->id)) {
            return true;
        }

        return $this->roleService()->canAccessCompanyViaDepartmentScope(
            $user,
            (int) $company->id,
            $permissions,
        );
    }

    protected function abortUnlessCanViewCompanyLeaves(User $user): void
    {
        abort_unless($this->canViewCompanyLeaves($user), 403);
    }

    protected function abortUnlessCanCreateLeaves(User $user): void
    {
        abort_unless($this->canCreateLeaves($user), 403);
    }

    protected function abortUnlessCanCreateLeaveForEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canCreateLeaveForEmployee($user, $employee), 403);
    }

    protected function abortUnlessCanAccessCompanyLeaves(User $user, Company $company): void
    {
        abort_unless($this->canAccessCompanyLeaves($user, $company), 403);
    }

    protected function canAccessEmployee(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        if (! $this->employeeQueryableCompanyIds($user)->contains($employee->company_id)) {
            return false;
        }

        foreach ($this->employeeViewPermissions() as $permission) {
            if ($this->roleService()->canAccessEmployeeInCompanyDepartment(
                $user,
                $permission,
                (int) $employee->company_id,
                $employee->department_id ? (string) $employee->department_id : null
            )) {
                return true;
            }
        }

        return false;
    }

    protected function abortUnlessCanViewEmployees(User $user): void
    {
        abort_unless($this->canViewEmployees($user), 403);
    }

    protected function abortUnlessCanViewEmployeeExpiryDocuments(User $user): void
    {
        abort_unless($this->canViewEmployeeExpiryDocuments($user), 403);
    }

    protected function abortUnlessCanManageEmployees(User $user): void
    {
        abort_unless($this->canManageEmployees($user), 403);
    }

    protected function abortUnlessCanManageEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canManageEmployee($user, $employee), 403);
    }

    protected function abortUnlessCanAccessEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canAccessEmployee($user, $employee), 403);
    }

    protected function abortUnlessCanUpdateEmployeeCustody(User $user, Employee $employee): void
    {
        abort_unless($this->canUpdateEmployeeCustodyForEmployee($user, $employee), 403);
    }

    protected function abortUnlessCanSyncEmployeeFingerprintMonth(User $user, Employee $employee): void
    {
        abort_unless($this->canSyncEmployeeFingerprintMonth($user, $employee), 403);
    }

    protected function abortUnlessCanExcludeEmployeeFromSalary(User $user, Employee $employee): void
    {
        abort_unless($this->canExcludeEmployeeFromSalary($user, $employee), 403);
    }

    protected function abortUnlessCanExportEmployeeProfileForEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canExportEmployeeProfileForEmployee($user, $employee), 403);
    }

    protected function abortUnlessCanExportCompanyEmployeeProfilesForCompany(User $user, Company $company): void
    {
        abort_unless($this->canExportCompanyEmployeeProfilesForCompany($user, $company), 403);
    }

    protected function abortUnlessCanSettleEmployeeEntitlementsForEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canSettleEmployeeEntitlementsForEmployee($user, $employee), 403);
    }

    /**
     * Companies assigned to the user's team role only (not owned companies).
     * Includes companies reached via department-scoped assignments.
     *
     * @return array<int>
     */
    protected function roleAssignedCompanyIds(User $user): array
    {
        return collect($user->accessibleCompanies()->pluck('companies.id'))
            ->merge($this->roleService()->companyIdsWhereCan($user, $this->employeeViewPermissions()))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function canUseEmployeeGlobalSearch(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->roleService()->canInAnyAssignedTeam($user, 'employees.global-search');
    }

    /**
     * @return array<int>|null null = all companies (super-admin)
     */
    protected function globalSearchCompanyIdsForUser(User $user): ?array
    {
        if ($user->hasRole('super-admin')) {
            return null;
        }

        if ($user->ownedCompanies()->exists()) {
            return $this->userAccessibleCompanyIds($user);
        }

        if ($this->roleService()->canInAnyAssignedTeam($user, 'employees.global-search')) {
            return $this->roleService()->companyIdsWhereCan($user, ['employees.global-search']);
        }

        return [];
    }

    protected function canViewEmployeeViaGlobalSearch(User $user, Employee $employee): bool
    {
        if (! $this->roleService()->canAccessEmployeeInCompanyDepartment(
            $user,
            'employees.global-search',
            (int) $employee->company_id,
            $employee->department_id ? (string) $employee->department_id : null
        )) {
            return false;
        }

        return in_array((int) $employee->company_id, $this->roleAssignedCompanyIds($user), true);
    }

    /**
     * Restrict an employee query to the departments allowed by the role scope.
     *
     * @param  array<int, string>  $permissions
     */
    protected function applyEmployeePermissionScope(Builder $query, User $user, array $permissions): void
    {
        if ($user->hasRole('super-admin') || $user->ownedCompanies()->exists()) {
            return;
        }

        $scopes = $this->roleService()->employeeScopeWhereCan($user, $permissions);

        if ($scopes === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function (Builder $scopeQuery) use ($scopes): void {
            foreach ($scopes as $scope) {
                $companyId = $scope['company_id'];
                $departmentIds = $scope['department_ids'];

                if ($companyId === null) {
                    if (is_array($departmentIds) && $departmentIds !== []) {
                        $scopeQuery->orWhereIn('department_id', $departmentIds);
                    }

                    continue;
                }

                $companyId = (int) $companyId;

                if ($departmentIds === null) {
                    $scopeQuery->orWhere('company_id', $companyId);
                    continue;
                }

                $scopeQuery->orWhere(function (Builder $companyQuery) use ($companyId, $departmentIds): void {
                    $companyQuery
                        ->where('company_id', $companyId)
                        ->whereIn('department_id', $departmentIds);
                });
            }
        });
    }

    protected function abortUnlessCanViewEmployeeProfile(User $user, Employee $employee): void
    {
        if ($this->canViewEmployees($user) && $this->canAccessEmployee($user, $employee)) {
            return;
        }

        abort_unless($this->canViewEmployeeViaGlobalSearch($user, $employee), 403);
    }
}
