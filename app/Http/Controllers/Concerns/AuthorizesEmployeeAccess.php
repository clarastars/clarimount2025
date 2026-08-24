<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeEntitlementSettlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Services\EmployeeUserRoleService;
use App\Services\EntitlementSettlementApprovalService;
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
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function employeeViewPermissions(): array
    {
        return [
            'employees.readonly',
            'employees.manage',
            'employees.custody.update',
            'employees.entitlements.settle',
            'employees.entitlements.approve',
            'attendance.fingerprint-month.sync',
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

    protected function employeeManageableCompanyIds(User $user): Collection
    {
        if ($user->hasRole('super-admin')) {
            return Company::query()->pluck('id');
        }

        $ownedIds = $user->ownedCompanies()->pluck('id');
        if ($ownedIds->isNotEmpty()) {
            return $ownedIds;
        }

        return collect($this->roleService()->companyIdsWhereCan($user, ['employees.manage']));
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

    protected function canSyncEmployeeFingerprintMonth(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if (! $this->roleService()->canForCompany($user, 'attendance.fingerprint-month.sync', (int) $employee->company_id)) {
            return false;
        }

        return $this->canAccessEmployee($user, $employee);
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

        if (! $this->canAccessEmployee($user, $employee)) {
            return false;
        }

        if ($user->hasRole('super-admin') || $user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

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

        return $this->roleService()->canAnyForCompany(
            $user,
            $this->leaveWorkflowAccessPermissions(),
            (int) $company->id
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
        if (! $this->employeeQueryableCompanyIds($user)->contains($employee->company_id)) {
            return false;
        }

        if ($user->hasRole('super-admin') || $user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
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
        abort_unless($this->canAccessEmployee($user, $employee), 403);
    }

    protected function abortUnlessCanSyncEmployeeFingerprintMonth(User $user, Employee $employee): void
    {
        abort_unless($this->canSyncEmployeeFingerprintMonth($user, $employee), 403);
    }

    protected function abortUnlessCanSettleEmployeeEntitlementsForEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canSettleEmployeeEntitlementsForEmployee($user, $employee), 403);
        abort_unless($this->canAccessEmployee($user, $employee), 403);
    }

    /**
     * Companies assigned to the user's team role only (not owned companies).
     *
     * @return array<int>
     */
    protected function roleAssignedCompanyIds(User $user): array
    {
        return $user->accessibleCompanies()
            ->pluck('companies.id')
            ->map(fn ($id): int => (int) $id)
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
                $companyId = (int) $scope['company_id'];
                $departmentIds = $scope['department_ids'];

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
