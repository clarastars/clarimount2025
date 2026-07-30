<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Services\EmployeeUserRoleService;

class DepartmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManageDepartments($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Department $department): bool
    {
        return $this->canManageDepartment($user, $department);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canManageDepartments($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Department $department): bool
    {
        return $this->canManageDepartment($user, $department);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Department $department): bool
    {
        return $this->canManageDepartment($user, $department);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Department $department): bool
    {
        return $this->canManageDepartment($user, $department);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Department $department): bool
    {
        return $this->canManageDepartment($user, $department);
    }

    private function canManageDepartments(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return app(EmployeeUserRoleService::class)->canInAnyAssignedTeam($user, 'departments.manage');
    }

    private function canManageDepartment(User $user, Department $department): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($department->company_id)->exists()) {
            return true;
        }

        return app(EmployeeUserRoleService::class)->canForCompany(
            $user,
            'departments.manage',
            (int) $department->company_id
        );
    }
}
