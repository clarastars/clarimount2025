<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompanyTeamRoleOverviewService
{
    public function __construct(
        private EmployeeUserRoleService $employeeUserRoleService,
    ) {}

    /**
     * @return array<int, array{
     *     user_id: int,
     *     user_name: string,
     *     user_email: string,
     *     employee_id: int|null,
     *     employee_name: string|null,
     *     employee_profile_url: string|null,
     *     global_roles: array<int, array{name: string, label: string}>,
     *     team_assignments: array<int, array{
     *         team_id: int,
     *         team_name: string,
     *         role_name: string,
     *         role_label: string,
     *         scope_type: string,
     *         department_names: array<int, string>,
     *         is_primary_team: bool
     *     }>
     * }>
     */
    public function buildForCompany(Company $company): array
    {
        $companyId = (int) $company->id;

        $userIds = DB::table('company_user_access')
            ->where('company_id', $companyId)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($company->owner_id) {
            $userIds[] = (int) $company->owner_id;
            $userIds = array_values(array_unique($userIds));
        }

        if ($userIds === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $userIds)
            ->with(['employee:id,user_id,first_name,last_name,company_id'])
            ->orderBy('name')
            ->get();

        return $users
            ->map(function (User $user) use ($company, $companyId): ?array {
                $isOwner = (int) $company->owner_id === (int) $user->id;
                $roleByTeam = collect($this->employeeUserRoleService->assignedTeamRoleAssignments($user))
                    ->keyBy('team_id');

                $teamAssignments = collect($this->employeeUserRoleService->assignedTeamsForUi($user))
                    ->map(function (array $team) use ($companyId, $roleByTeam, $user): ?array {
                        $companyScopes = collect($team['company_scopes'] ?? [])
                            ->filter(fn (array $scope): bool => (int) ($scope['company_id'] ?? 0) === $companyId)
                            ->values();

                        if ($companyScopes->isEmpty()) {
                            return null;
                        }

                        $teamId = (int) $team['id'];
                        $roleName = (string) ($roleByTeam->get($teamId)['role_name'] ?? 'team-member');
                        $departmentNames = $companyScopes
                            ->flatMap(fn (array $scope): array => $scope['department_names'] ?? [])
                            ->unique()
                            ->values()
                            ->all();

                        $hasFullCompanyScope = $companyScopes->contains(
                            fn (array $scope): bool => ($scope['department_names'] ?? []) === []
                        );

                        return [
                            'team_id' => $teamId,
                            'team_name' => (string) $team['name'],
                            'role_name' => $roleName,
                            'role_label' => $this->teamRoleLabel($roleName),
                            'scope_type' => $hasFullCompanyScope && $departmentNames === []
                                ? 'full_company'
                                : 'departments',
                            'department_names' => $departmentNames,
                            'is_primary_team' => (int) $user->team_id === $teamId,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                $globalRoles = collect($this->employeeUserRoleService->assignedGlobalRoleNames($user))
                    ->map(fn (string $name): array => [
                        'name' => $name,
                        'label' => $this->globalRoleLabel($name),
                    ])
                    ->values()
                    ->all();

                if ($teamAssignments === [] && $globalRoles === [] && ! $isOwner) {
                    return null;
                }

                $employee = $user->employee;
                $employeeName = $employee
                    ? trim("{$employee->first_name} {$employee->last_name}")
                    : null;

                if ($isOwner && $teamAssignments === []) {
                    $teamAssignments[] = [
                        'team_id' => 0,
                        'team_name' => (string) __('messages.companies.team_roles_company_owner'),
                        'role_name' => 'company-owner',
                        'role_label' => (string) __('messages.companies.team_roles_company_owner'),
                        'scope_type' => 'full_company',
                        'department_names' => [],
                        'is_primary_team' => false,
                    ];
                }

                return [
                    'user_id' => (int) $user->id,
                    'user_name' => (string) $user->name,
                    'user_email' => (string) $user->email,
                    'employee_id' => $employee ? (int) $employee->id : null,
                    'employee_name' => $employeeName !== '' ? $employeeName : null,
                    'employee_profile_url' => $employee ? route('employees.show', $employee->id) : null,
                    'global_roles' => $globalRoles,
                    'team_assignments' => $teamAssignments,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function teamRoleLabel(string $roleName): string
    {
        $key = EmployeeUserRoleService::TEAM_ROLES[$roleName] ?? null;

        return $key ? (string) __($key) : $roleName;
    }

    private function globalRoleLabel(string $roleName): string
    {
        $key = EmployeeUserRoleService::GLOBAL_ROLES[$roleName] ?? null;

        return $key ? (string) __($key) : $roleName;
    }
}
