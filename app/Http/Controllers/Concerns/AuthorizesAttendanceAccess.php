<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\User;
use App\Services\EmployeeUserRoleService;

trait AuthorizesAttendanceAccess
{
    protected function attendanceRoleService(): EmployeeUserRoleService
    {
        return app(EmployeeUserRoleService::class);
    }

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

    protected function canAccessCompanyAttendance(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->where('id', $company->id)->exists()) {
            return true;
        }

        return $this->attendanceRoleService()->canForCompany(
            $user,
            'attendance.readonly',
            (int) $company->id
        );
    }

    protected function canManageAttendanceAdjustments(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $this->attendanceRoleService()->canInAnyAssignedTeam($user, 'attendance.adjustments.manage');
    }

    protected function canManageAttendanceAdjustmentsForCompany(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->where('id', $company->id)->exists()) {
            return true;
        }

        return $this->attendanceRoleService()->canForCompany(
            $user,
            'attendance.adjustments.manage',
            (int) $company->id
        );
    }

    protected function canViewAttendanceAdjustmentsForCompany(User $user, Company $company): bool
    {
        return $this->canAccessCompanyAttendance($user, $company)
            || $this->canManageAttendanceAdjustmentsForCompany($user, $company);
    }

    /**
     * @return array<int>
     */
    protected function manageableAttendanceCompanyIds(User $user): array
    {
        if ($user->hasRole('super-admin')) {
            return Company::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        if ($user->ownedCompanies()->exists()) {
            return $user->ownedCompanies()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $this->attendanceRoleService()->companyIdsWhereCan($user, ['attendance.adjustments.manage']);
    }

    protected function abortUnlessCanViewAttendanceAdjustments(User $user, Company $company): void
    {
        abort_unless($this->canViewAttendanceAdjustmentsForCompany($user, $company), 403);
    }

    protected function abortUnlessCanManageAttendanceAdjustments(User $user, Company $company): void
    {
        abort_unless($this->canManageAttendanceAdjustmentsForCompany($user, $company), 403);
    }
}
