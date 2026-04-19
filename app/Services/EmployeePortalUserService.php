<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class EmployeePortalUserService
{
    private const DEFAULT_PASSWORD = '12345678';

    private const EMPLOYEE_ROLE_NAME = 'employee';

    /**
     * Create or get portal user for the employee. Assigns role "employee" and links employee.user_id.
     * Uses work_email or email; password defaults to 12345678.
     */
    public function createOrSyncPortalUser(
        Employee $employee,
        ?string $plainPassword = null,
        bool $forcePasswordReset = false
    ): ?User
    {
        $email = $this->getLoginEmail($employee);
        if ($email === null || $email === '') {
            Log::warning('[EmployeePortalUser] No email for employee', ['employee_id' => $employee->id]);
            return null;
        }

        $role = Role::where('name', self::EMPLOYEE_ROLE_NAME)->first();
        if (! $role) {
            Log::warning('[EmployeePortalUser] Role "employee" not found. Run RolesAndPermissionsSeeder.');
            return null;
        }

        $user = $employee->user()->first();
        if (! $user) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            if ($user->email !== $email) {
                $emailTaken = User::query()
                    ->where('email', $email)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if (! $emailTaken) {
                    $user->update(['email' => $email]);
                } else {
                    Log::warning('[EmployeePortalUser] Cannot sync login email because it is already used', [
                        'employee_id' => $employee->id,
                        'user_id' => $user->id,
                        'email' => $email,
                    ]);
                }
            }

            if ($plainPassword !== null && trim($plainPassword) !== '') {
                $user->update(['password' => Hash::make($plainPassword)]);
            } elseif ($forcePasswordReset) {
                $user->update(['password' => Hash::make(self::DEFAULT_PASSWORD)]);
            }

            $hasEmployeeRole = $user->roles()->where('roles.id', $role->id)->wherePivot('team_id', null)->exists();
            if (! $hasEmployeeRole) {
                $user->roles()->attach($role->id, ['team_id' => null]);
            }
            if (! $user->employee) {
                $employee->update(['user_id' => $user->id]);
            }
            return $user;
        }

        $name = trim($employee->first_name . ' ' . $employee->last_name) ?: $email;
        $passwordToUse = $plainPassword !== null && trim($plainPassword) !== ''
            ? $plainPassword
            : self::DEFAULT_PASSWORD;

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($passwordToUse),
            'language' => 'ar',
        ]);

        $user->roles()->attach($role->id, ['team_id' => null]);

        $employee->update(['user_id' => $user->id]);

        return $user;
    }

    private function getLoginEmail(Employee $employee): ?string
    {
        $email = $employee->work_email ?? $employee->personal_email ?? null;
        return $email ? trim((string) $email) : null;
    }
}
