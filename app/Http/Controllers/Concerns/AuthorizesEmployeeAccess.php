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

        if ($user->can('employees.readonly') || $user->can('employees.manage') || $user->can('employees.custody.update')) {
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
            || $user->can('employees.custody.update');
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
}
