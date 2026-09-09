<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class EmployeePortalUserService
{
    private const DEFAULT_PASSWORD = '12345678';

    private const EMPLOYEE_ROLE_NAME = 'employee';

    /**
     * Resolve the portal user for a work email, creating or linking the employee account when needed.
     */
    public function resolveByLoginEmail(string $email): ?User
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return null;
        }

        $employee = $this->findEmployeeByWorkEmail($email);
        if ($employee) {
            return $this->createOrSyncPortalUser($employee);
        }

        return $this->findUserByEmail($email);
    }

    /**
     * Link an already-authenticated user to their employee row when work_email matches.
     */
    public function ensureLinkedEmployee(User $user): ?Employee
    {
        if ($user->employee) {
            return $user->employee;
        }

        $this->resolveByLoginEmail((string) $user->email);
        $user->unsetRelation('employee');

        return $user->employee;
    }

    /**
     * Create or get portal user for the employee. Assigns role "employee" and links employee.user_id.
     * Uses work_email; OTP users get a random password unless one is provided.
     */
    public function createOrSyncPortalUser(
        Employee $employee,
        ?string $plainPassword = null,
        bool $forcePasswordReset = false
    ): ?User {
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
            $user = $this->findUserByEmail($email);
        }

        if ($user) {
            if ($this->normalizeEmail((string) $user->email) !== $email) {
                $emailTaken = $this->findUserByEmail($email, $user->id) !== null;

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
                $user->update([
                    'password' => Hash::make($plainPassword),
                    'uses_password_login' => true,
                ]);
            } elseif ($forcePasswordReset) {
                $user->update([
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'uses_password_login' => true,
                ]);
            }

            $hasEmployeeRole = $user->roles()->where('roles.id', $role->id)->wherePivot('team_id', null)->exists();
            if (! $hasEmployeeRole) {
                $user->roles()->attach($role->id, ['team_id' => null]);
            }

            $this->linkEmployeeToUser($employee, $user);

            return $user;
        }

        $name = trim($employee->first_name.' '.$employee->last_name) ?: $email;
        $passwordToUse = $plainPassword !== null && trim($plainPassword) !== ''
            ? $plainPassword
            : $this->generateRandomPassword();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($passwordToUse),
            'uses_password_login' => $plainPassword !== null && trim($plainPassword) !== '',
            'language' => 'ar',
        ]);

        $user->roles()->attach($role->id, ['team_id' => null]);

        $this->linkEmployeeToUser($employee, $user);

        return $user;
    }

    private function linkEmployeeToUser(Employee $employee, User $user): void
    {
        if ((int) ($employee->user_id ?? 0) === (int) $user->id) {
            return;
        }

        $occupiedBy = Employee::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $employee->id)
            ->first();

        if ($occupiedBy !== null) {
            Log::warning('[EmployeePortalUser] User already linked to another employee', [
                'employee_id' => $employee->id,
                'other_employee_id' => $occupiedBy->id,
                'user_id' => $user->id,
            ]);

            return;
        }

        $employee->forceFill(['user_id' => $user->id])->save();
    }

    private function findEmployeeByWorkEmail(string $normalizedEmail): ?Employee
    {
        return Employee::query()
            ->whereNotNull('work_email')
            ->where('work_email', '!=', '')
            ->whereRaw('LOWER(TRIM(work_email)) = ?', [$normalizedEmail])
            ->first();
    }

    private function findUserByEmail(string $normalizedEmail, ?int $exceptUserId = null): ?User
    {
        $query = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail]);

        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->first();
    }

    private function getLoginEmail(Employee $employee): ?string
    {
        $email = $this->normalizeEmail((string) ($employee->work_email ?? ''));

        return $email !== '' ? $email : null;
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function generateRandomPassword(): string
    {
        return Str::password(32);
    }
}
