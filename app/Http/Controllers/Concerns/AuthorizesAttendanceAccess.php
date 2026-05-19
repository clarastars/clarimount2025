<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\User;

trait AuthorizesAttendanceAccess
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

    protected function canAccessCompanyAttendance(User $user, Company $company): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->where('id', $company->id)->exists()) {
            return true;
        }

        $allowedCompanyIds = $this->userAccessibleCompanyIds($user);

        return $user->can('attendance.readonly')
            && in_array((int) $company->id, $allowedCompanyIds, true);
    }

    protected function canManageAttendanceAdjustments(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->exists()) {
            return true;
        }

        return $user->can('attendance.adjustments.manage');
    }

    protected function canManageAttendanceAdjustmentsForCompany(User $user, Company $company): bool
    {
        if (! $this->canManageAttendanceAdjustments($user)) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->where('id', $company->id)->exists()) {
            return true;
        }

        return in_array((int) $company->id, $this->userAccessibleCompanyIds($user), true);
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

        if ($user->can('attendance.adjustments.manage')) {
            return $this->userAccessibleCompanyIds($user);
        }

        return [];
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
