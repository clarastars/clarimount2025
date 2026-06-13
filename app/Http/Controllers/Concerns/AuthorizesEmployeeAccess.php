<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
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

    protected function employeeQueryableCompanyIds(User $user): Collection
    {
        if ($user->hasRole('super-admin')) {
            return Company::query()->pluck('id');
        }

        $ownedIds = $user->ownedCompanies()->pluck('id');
        if ($ownedIds->isNotEmpty()) {
            return $ownedIds;
        }

        if ($user->can('employees.readonly')
            || $user->can('employees.manage')
            || $user->can('employees.custody.update')
            || $user->can('attendance.fingerprint-month.sync')) {
            return collect($this->userAccessibleCompanyIds($user));
        }

        return collect();
    }

    protected function canViewEmployees(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $user->can('employees.readonly')
            || $user->can('employees.manage')
            || $user->can('employees.custody.update')
            || $user->can('attendance.fingerprint-month.sync');
    }

    protected function canSyncEmployeeFingerprintMonth(User $user, Employee $employee): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if (! $user->can('attendance.fingerprint-month.sync')) {
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

        return $user->can('employees.custody.update');
    }

    protected function canManageEmployees(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $user->can('employees.manage');
    }

    protected function canViewCompanyLeaves(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $user->can('leaves.company.view');
    }

    protected function canCreateLeaves(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $user->can('leaves.create');
    }

    protected function canCreateLeaveForEmployee(User $user, Employee $employee): bool
    {
        if (! $this->canCreateLeaves($user)) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($employee->company_id)->exists()) {
            return true;
        }

        return in_array((int) $employee->company_id, $this->userAccessibleCompanyIds($user), true);
    }

    protected function canAccessCompanyLeaves(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($company->id)->exists()) {
            return true;
        }

        if ($user->can('leaves.company.view') || $user->can('leaves.create')) {
            return in_array((int) $company->id, $this->userAccessibleCompanyIds($user), true);
        }

        return false;
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
        return $this->employeeQueryableCompanyIds($user)->contains($employee->company_id);
    }

    protected function abortUnlessCanViewEmployees(User $user): void
    {
        abort_unless($this->canViewEmployees($user), 403);
    }

    protected function abortUnlessCanManageEmployees(User $user): void
    {
        abort_unless($this->canManageEmployees($user), 403);
    }

    protected function abortUnlessCanAccessEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canAccessEmployee($user, $employee), 403);
    }

    protected function abortUnlessCanUpdateEmployeeCustody(User $user, Employee $employee): void
    {
        abort_unless($this->canUpdateEmployeeCustody($user), 403);
        abort_unless($this->canAccessEmployee($user, $employee), 403);
    }

    protected function abortUnlessCanSyncEmployeeFingerprintMonth(User $user, Employee $employee): void
    {
        abort_unless($this->canSyncEmployeeFingerprintMonth($user, $employee), 403);
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

        return $user->can('employees.global-search');
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

        if ($user->can('employees.global-search')) {
            return $this->roleAssignedCompanyIds($user);
        }

        return [];
    }

    protected function canViewEmployeeViaGlobalSearch(User $user, Employee $employee): bool
    {
        if (! $user->can('employees.global-search')) {
            return false;
        }

        return in_array((int) $employee->company_id, $this->roleAssignedCompanyIds($user), true);
    }

    protected function abortUnlessCanViewEmployeeProfile(User $user, Employee $employee): void
    {
        if ($this->canViewEmployees($user) && $this->canAccessEmployee($user, $employee)) {
            return;
        }

        abort_unless($this->canViewEmployeeViaGlobalSearch($user, $employee), 403);
    }
}
