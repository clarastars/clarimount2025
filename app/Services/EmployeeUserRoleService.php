<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EmployeeUserRoleService
{
    /** @var array<string, string> */
    public const GLOBAL_ROLES = [
        'employee' => 'messages.employees.portal_employee_role',
    ];

    /** @var array<string, string> */
    public const TEAM_ROLES = [
        'team-member' => 'messages.settings.team_role_member',
        'team-admin' => 'messages.settings.team_role_admin',
        'team-viewer' => 'messages.settings.team_role_viewer',
    ];

    /**
     * @return Collection<int, Team>
     */
    public function manageableTeamsFor(User $actingUser): Collection
    {
        return Team::query()
            ->where('owner_id', $actingUser->id)
            ->orWhere('id', $actingUser->team_id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->unique('id')
            ->values();
    }

    /**
     * @return array<int, array{
     *     team_id: int,
     *     role_name: string,
     *     company_ids: array<int, int>,
     *     company_departments: array<int|string, array<int, string>>
     * }>
     */
    public function assignedTeamRoleAssignments(User $portalUser): array
    {
        $teamsKey = $this->teamPivotKey();
        $pivotTable = config('permission.table_names.model_has_roles');
        $rolesTable = config('permission.table_names.roles');

        $assignments = DB::table($pivotTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.role_id")
            ->where("{$pivotTable}.model_type", $portalUser->getMorphClass())
            ->where("{$pivotTable}.model_id", $portalUser->id)
            ->whereIn("{$rolesTable}.name", array_keys(self::TEAM_ROLES))
            ->whereNotNull("{$pivotTable}.{$teamsKey}")
            ->orderBy("{$pivotTable}.{$teamsKey}")
            ->get([
                "{$pivotTable}.{$teamsKey} as team_id",
                "{$rolesTable}.name as role_name",
            ])
            ->map(fn ($row) => [
                'team_id' => (int) $row->team_id,
                'role_name' => (string) $row->role_name,
            ])
            ->unique('team_id')
            ->values();

        $companyAccessByTeam = DB::table('company_user_access')
            ->where('user_id', $portalUser->id)
            ->whereNotNull('team_id')
            ->get(['team_id', 'company_id', 'department_id'])
            ->groupBy(fn ($row) => (int) $row->team_id)
            ->map(function ($rows) {
                $companyIds = $rows->pluck('company_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $companyDepartments = $rows
                    ->groupBy(fn ($row) => (int) $row->company_id)
                    ->map(function ($companyRows) {
                        if ($companyRows->contains(fn ($row) => $row->department_id === null)) {
                            return [];
                        }

                        return $companyRows->pluck('department_id')
                            ->filter()
                            ->map(fn ($id) => (string) $id)
                            ->unique()
                            ->values()
                            ->all();
                    })
                    ->filter(fn (array $departmentIds) => $departmentIds !== [])
                    ->all();

                return [
                    'company_ids' => $companyIds,
                    'company_departments' => $companyDepartments,
                ];
            });

        // Legacy unscoped companies (team_id NULL) apply to every current team so prior access is preserved in the UI.
        $legacyCompanyIds = DB::table('company_user_access')
            ->where('user_id', $portalUser->id)
            ->whereNull('team_id')
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $assignments
            ->map(function (array $row) use ($companyAccessByTeam, $legacyCompanyIds) {
                $teamId = (int) $row['team_id'];
                $scoped = $companyAccessByTeam->get($teamId, [
                    'company_ids' => [],
                    'company_departments' => [],
                ]);
                $companyIds = array_values(array_unique(array_merge($scoped['company_ids'], $legacyCompanyIds)));

                return [
                    'team_id' => $teamId,
                    'role_name' => (string) $row['role_name'],
                    'company_ids' => $companyIds,
                    'company_departments' => $scoped['company_departments'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function assignedGlobalRoleNames(User $portalUser): array
    {
        $teamsKey = $this->teamPivotKey();
        $pivotTable = config('permission.table_names.model_has_roles');
        $rolesTable = config('permission.table_names.roles');

        return DB::table($pivotTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.role_id")
            ->where("{$pivotTable}.model_type", $portalUser->getMorphClass())
            ->where("{$pivotTable}.model_id", $portalUser->id)
            ->whereIn("{$rolesTable}.name", array_keys(self::GLOBAL_ROLES))
            ->whereNull("{$pivotTable}.{$teamsKey}")
            ->pluck("{$rolesTable}.name")
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{
     *     team_id?: int|string|null,
     *     role_name?: string|null,
     *     company_ids?: array<int, int|string>,
     *     company_departments?: array<int|string, array<int, string>>
     * }>  $teamRoleAssignments
     * @param  array<int, string>  $globalRoleNames
     * @param  array<int, int|string>|null  $legacyRoleCompanyIds  Kept for backward compatibility; applied to every assigned team when company_ids are omitted.
     */
    public function sync(
        User $portalUser,
        User $actingUser,
        array $teamRoleAssignments,
        ?int $primaryTeamId,
        array $globalRoleNames,
        ?array $legacyRoleCompanyIds = null
    ): void {
        $manageableTeamIds = $this->manageableTeamsFor($actingUser)->pluck('id')->map(fn ($id) => (int) $id);

        $normalizedAssignments = collect($teamRoleAssignments)
            ->map(function (array $row) use ($manageableTeamIds, $legacyRoleCompanyIds) {
                $teamId = isset($row['team_id']) ? (int) $row['team_id'] : 0;
                $roleName = isset($row['role_name']) ? (string) $row['role_name'] : '';

                if ($teamId === 0 || ! $manageableTeamIds->contains($teamId)) {
                    return null;
                }

                if ($roleName === '' || ! array_key_exists($roleName, self::TEAM_ROLES)) {
                    $roleName = 'team-member';
                }

                $companyIds = collect($row['company_ids'] ?? $legacyRoleCompanyIds ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $companyDepartments = collect($row['company_departments'] ?? [])
                    ->mapWithKeys(function ($departmentIds, $companyId) use ($companyIds) {
                        $companyId = (int) $companyId;

                        if (! in_array($companyId, $companyIds, true) || ! is_array($departmentIds)) {
                            return [];
                        }

                        $normalizedDepartmentIds = collect($departmentIds)
                            ->map(fn ($id) => (string) $id)
                            ->filter(fn (string $id) => $id !== '')
                            ->unique()
                            ->values()
                            ->all();

                        return [$companyId => $normalizedDepartmentIds];
                    })
                    ->mapWithKeys(function (array $departmentIds, int $companyId) {
                        if ($departmentIds === []) {
                            return [$companyId => []];
                        }

                        $validDepartmentIds = DB::table('departments')
                            ->where('company_id', $companyId)
                            ->whereIn('id', $departmentIds)
                            ->pluck('id')
                            ->map(fn ($id) => (string) $id)
                            ->values()
                            ->all();

                        return [$companyId => $validDepartmentIds];
                    })
                    ->all();

                return [
                    'team_id' => $teamId,
                    'role_name' => $roleName,
                    'company_ids' => $companyIds,
                    'company_departments' => $companyDepartments,
                ];
            })
            ->filter()
            ->unique(fn (array $row) => (string) $row['team_id'])
            ->values();

        $assignedTeamIds = $normalizedAssignments->pluck('team_id');

        if ($primaryTeamId !== null && ! $assignedTeamIds->contains($primaryTeamId)) {
            $primaryTeamId = null;
        }

        if ($primaryTeamId === null && $assignedTeamIds->isNotEmpty()) {
            $primaryTeamId = (int) $assignedTeamIds->first();
        }

        $portalUser->update([
            'team_id' => $primaryTeamId,
            'joined_team_at' => $primaryTeamId ? now() : null,
        ]);

        $this->detachManageableTeamRoles($portalUser, $manageableTeamIds);

        foreach ($normalizedAssignments as $assignment) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($assignment['team_id']);

            $role = Role::query()->firstOrCreate([
                'name' => $assignment['role_name'],
                'guard_name' => 'web',
                'team_id' => $assignment['team_id'],
            ]);

            if (! $portalUser->hasRole($role)) {
                $portalUser->assignRole($role);
            }
        }

        $this->syncGlobalRoles($portalUser, $globalRoleNames);
        $this->syncTeamCompanyAccess($portalUser, $manageableTeamIds, $normalizedAssignments);

        app(PermissionRegistrar::class)->setPermissionsTeamId($portalUser->team_id);
        $portalUser->unsetRelation('roles');
        $portalUser->unsetRelation('permissions');
        $portalUser->unsetRelation('accessibleCompanies');
    }

    /**
     * Sync company access scoped to each team assignment.
     * Only replaces rows for teams the acting user can manage; other teams' access is left intact.
     *
     * @param  Collection<int, int>  $manageableTeamIds
     * @param  Collection<int, array{
     *     team_id: int,
     *     role_name: string,
     *     company_ids: array<int, int>,
     *     company_departments: array<int|string, array<int, string>>
     * }>  $normalizedAssignments
     */
    private function syncTeamCompanyAccess(
        User $portalUser,
        Collection $manageableTeamIds,
        Collection $normalizedAssignments
    ): void {
        if ($manageableTeamIds->isEmpty()) {
            return;
        }

        DB::table('company_user_access')
            ->where('user_id', $portalUser->id)
            ->where(function ($query) use ($manageableTeamIds): void {
                $query->whereIn('team_id', $manageableTeamIds->all())
                    ->orWhereNull('team_id');
            })
            ->delete();

        $now = now();
        $rows = [];

        foreach ($normalizedAssignments as $assignment) {
            foreach ($assignment['company_ids'] as $companyId) {
                $departmentIds = collect($assignment['company_departments'][$companyId] ?? [])
                    ->map(fn ($id) => (string) $id)
                    ->filter(fn (string $id) => $id !== '')
                    ->unique()
                    ->values()
                    ->all();

                if ($departmentIds === []) {
                    $rows[] = [
                        'company_id' => (int) $companyId,
                        'department_id' => null,
                        'user_id' => $portalUser->id,
                        'team_id' => (int) $assignment['team_id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    continue;
                }

                foreach ($departmentIds as $departmentId) {
                    $rows[] = [
                        'company_id' => (int) $companyId,
                        'department_id' => $departmentId,
                        'user_id' => $portalUser->id,
                        'team_id' => (int) $assignment['team_id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('company_user_access')->insertOrIgnore($chunk);
        }
    }

    /**
     * @param  Collection<int, int>  $manageableTeamIds
     */
    private function detachManageableTeamRoles(User $portalUser, Collection $manageableTeamIds): void
    {
        if ($manageableTeamIds->isEmpty()) {
            return;
        }

        $teamsKey = $this->teamPivotKey();
        $pivotTable = config('permission.table_names.model_has_roles');
        $rolesTable = config('permission.table_names.roles');

        DB::table($pivotTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.role_id")
            ->where("{$pivotTable}.model_type", $portalUser->getMorphClass())
            ->where("{$pivotTable}.model_id", $portalUser->id)
            ->whereIn("{$rolesTable}.name", array_keys(self::TEAM_ROLES))
            ->whereIn("{$pivotTable}.{$teamsKey}", $manageableTeamIds->all())
            ->delete();
    }

    /**
     * @param  array<int, string>  $globalRoleNames
     */
    private function syncGlobalRoles(User $portalUser, array $globalRoleNames): void
    {
        $allowed = array_keys(self::GLOBAL_ROLES);
        $requested = collect($globalRoleNames)
            ->filter(fn (string $name) => in_array($name, $allowed, true))
            ->unique()
            ->values();

        $existingGlobalRoleIds = $portalUser->roles()
            ->whereNull('roles.team_id')
            ->whereIn('name', $allowed)
            ->pluck('roles.id');

        $portalUser->roles()->detach($existingGlobalRoleIds);

        foreach ($requested as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->whereNull('team_id')
                ->first();

            if (! $role) {
                $role = Role::query()->create([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'team_id' => null,
                ]);
            }

            $portalUser->roles()->syncWithoutDetaching([
                $role->id => ['team_id' => null],
            ]);
        }
    }

    /**
     * @return array<int, array{name: string, label: string}>
     */
    public function assignableGlobalRolesForUi(): array
    {
        return collect(self::GLOBAL_ROLES)
            ->map(fn (string $labelKey, string $name) => [
                'name' => $name,
                'label' => __($labelKey),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, label: string}>
     */
    public function assignableTeamRolesForUi(): array
    {
        return collect(self::TEAM_ROLES)
            ->map(fn (string $labelKey, string $name) => [
                'name' => $name,
                'label' => __($labelKey),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     company_ids: array<int, int>,
     *     company_names: array<int, string>,
     *     company_scopes: array<int, array{
     *         company_id: int,
     *         company_name: string,
     *         department_names: array<int, string>
     *     }>
     * }>
     */
    public function assignedTeamsForUi(User $portalUser): array
    {
        $assignments = $this->assignedTeamRoleAssignments($portalUser);

        if ($assignments === [] && $portalUser->team_id) {
            $assignments = [
                [
                    'team_id' => (int) $portalUser->team_id,
                    'role_name' => 'team-member',
                ],
            ];
        }

        $teamNames = Team::query()
            ->whereIn('id', collect($assignments)->pluck('team_id'))
            ->pluck('name', 'id');

        $allCompanyIds = collect($assignments)
            ->pluck('company_ids')
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $companyNames = DB::table('companies')
            ->whereIn('id', $allCompanyIds)
            ->get(['id', 'name_en', 'name_ar'])
            ->mapWithKeys(function ($company) {
                $name = trim(($company->name_ar ?? '').' '.($company->name_en ?? ''));

                return [(int) $company->id => $name !== '' ? $name : (string) $company->id];
            });

        $allDepartmentIds = collect($assignments)
            ->pluck('company_departments')
            ->filter()
            ->flatMap(function ($companyDepartments) {
                return collect($companyDepartments)->flatten();
            })
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $departmentNames = DB::table('departments')
            ->whereIn('id', $allDepartmentIds)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => (string) $name]);

        return collect($assignments)
            ->map(function (array $row) use ($teamNames, $companyNames, $departmentNames) {
                $teamId = (int) $row['team_id'];
                $companyIds = collect($row['company_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $companyDepartments = collect($row['company_departments'] ?? []);

                return [
                    'id' => $teamId,
                    'name' => (string) ($teamNames[$teamId] ?? $teamId),
                    'company_ids' => $companyIds,
                    'company_names' => collect($companyIds)
                        ->map(fn (int $id) => (string) ($companyNames[$id] ?? $id))
                        ->values()
                        ->all(),
                    'company_scopes' => collect($companyIds)
                        ->map(function (int $companyId) use ($companyNames, $companyDepartments, $departmentNames) {
                            $departmentIds = collect($companyDepartments->get($companyId, []))
                                ->map(fn ($id) => (string) $id)
                                ->values()
                                ->all();

                            return [
                                'company_id' => $companyId,
                                'company_name' => (string) ($companyNames[$companyId] ?? $companyId),
                                'department_names' => collect($departmentIds)
                                    ->map(fn (string $id) => (string) ($departmentNames[$id] ?? $id))
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function teamPivotKey(): string
    {
        return (string) config('permission.column_names.team_foreign_key', 'team_id');
    }

    /**
     * @return array<int, int>
     */
    public function assignedTeamIdsFor(User $portalUser): array
    {
        $ids = collect($this->assignedTeamRoleAssignments($portalUser))
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id);

        if ($portalUser->team_id) {
            $ids->push((int) $portalUser->team_id);
        }

        return $ids->unique()->values()->all();
    }

    public function userBelongsToTeam(User $portalUser, int $teamId): bool
    {
        return in_array($teamId, $this->assignedTeamIdsFor($portalUser), true);
    }

    /**
     * @return array<int, int>
     */
    public function userIdsForTeam(int $teamId): array
    {
        $teamsKey = $this->teamPivotKey();
        $pivotTable = config('permission.table_names.model_has_roles');
        $rolesTable = config('permission.table_names.roles');

        $fromMembership = DB::table($pivotTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.role_id")
            ->where("{$pivotTable}.model_type", User::class)
            ->whereIn("{$rolesTable}.name", array_keys(self::TEAM_ROLES))
            ->where("{$pivotTable}.{$teamsKey}", $teamId)
            ->pluck("{$pivotTable}.model_id");

        return User::query()
            ->where('team_id', $teamId)
            ->orWhereIn('id', $fromMembership)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function userIdsForTeamInCompany(int $teamId, int $companyId): array
    {
        return DB::table('company_user_access')
            ->where('team_id', $teamId)
            ->where('company_id', $companyId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function userBelongsToTeamInCompany(User $portalUser, int $teamId, int $companyId): bool
    {
        if (! $this->userBelongsToTeam($portalUser, $teamId)) {
            return false;
        }

        return DB::table('company_user_access')
            ->where('user_id', $portalUser->id)
            ->where('team_id', $teamId)
            ->where('company_id', $companyId)
            ->exists();
    }

    /**
     * @param  string|array<int, string>  $permissions
     */
    public function userCanAcrossTeams(User $user, string|array $permissions): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $teamIds = $this->assignedTeamIdsFor($user);

        if ($teamIds === []) {
            return false;
        }

        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        try {
            foreach ($teamIds as $teamId) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');

                foreach ($permissions as $permission) {
                    if ($user->can($permission)) {
                        return true;
                    }
                }
            }
        } finally {
            app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function describeAssignments(User $portalUser): array
    {
        $global = collect($this->assignedGlobalRoleNames($portalUser))
            ->map(fn (string $name) => __(self::GLOBAL_ROLES[$name] ?? $name));

        $teams = collect($this->assignedTeamsForUi($portalUser))
            ->pluck('name');

        return $global->merge($teams)->values()->all();
    }

    /**
     * Name of the user's primary assigned team (portal dashboard subtitle).
     */
    public function primaryTeamNameFor(User $portalUser): ?string
    {
        $teams = $this->assignedTeamsForUi($portalUser);

        if ($teams === []) {
            return null;
        }

        $primaryTeamId = (int) ($portalUser->team_id ?? 0);
        $primary = collect($teams)->firstWhere('id', $primaryTeamId);

        return (string) ($primary['name'] ?? $teams[0]['name']);
    }

    /**
     * Localized team role label for the user's primary team.
     */
    public function primaryTeamRoleLabelFor(User $portalUser): ?string
    {
        $assignments = $this->assignedTeamRoleAssignments($portalUser);

        if ($assignments === [] && ! $portalUser->team_id) {
            return null;
        }

        $primaryTeamId = (int) ($portalUser->team_id ?? 0);
        $assignment = collect($assignments)->firstWhere('team_id', $primaryTeamId);

        if ($assignment === null) {
            $assignment = $assignments[0] ?? [
                'team_id' => $primaryTeamId,
                'role_name' => 'team-member',
            ];
        }

        $roleName = (string) ($assignment['role_name'] ?? 'team-member');

        return __(self::TEAM_ROLES[$roleName] ?? $roleName);
    }

    /**
     * Team IDs that grant this user access to the given company.
     *
     * @return array<int, int>
     */
    public function teamIdsForCompany(User $user, int $companyId): array
    {
        $scopedTeamIds = DB::table('company_user_access')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->whereNotNull('team_id')
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($scopedTeamIds->isNotEmpty()) {
            return $scopedTeamIds->all();
        }

        // Legacy unscoped rows: company access applies under every assigned team.
        $hasLegacyAccess = DB::table('company_user_access')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->whereNull('team_id')
            ->exists();

        if (! $hasLegacyAccess) {
            return [];
        }

        return $this->assignedTeamIdsFor($user);
    }

    /**
     * Team IDs that grant this user access to the given company and department.
     *
     * A NULL department scope means whole-company access for that team.
     *
     * @return array<int, int>
     */
    public function teamIdsForScopedCompany(User $user, int $companyId, ?string $departmentId): array
    {
        $scopedTeamIds = DB::table('company_user_access')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->whereNotNull('team_id')
            ->where(function ($query) use ($departmentId): void {
                $query->whereNull('department_id');

                if ($departmentId !== null && $departmentId !== '') {
                    $query->orWhere('department_id', $departmentId);
                }
            })
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($scopedTeamIds->isNotEmpty()) {
            return $scopedTeamIds->all();
        }

        $hasLegacyAccess = DB::table('company_user_access')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->whereNull('team_id')
            ->exists();

        if (! $hasLegacyAccess) {
            return [];
        }

        return $this->assignedTeamIdsFor($user);
    }

    /**
     * Whether the user has the permission via any team linked to this company.
     */
    public function canForCompany(User $user, string $permission, int $companyId): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($companyId)->exists()) {
            return true;
        }

        $teamIds = $this->teamIdsForCompany($user, $companyId);
        if ($teamIds === []) {
            return false;
        }

        return $this->withTeamContexts($user, $teamIds, function () use ($user, $permission): bool {
            return $user->can($permission);
        });
    }

    /**
     * Whether the user has any of the permissions via any team linked to this company.
     *
     * @param  array<int, string>  $permissions
     */
    public function canAnyForCompany(User $user, array $permissions, int $companyId): bool
    {
        foreach ($permissions as $permission) {
            if ($this->canForCompany($user, $permission, $companyId)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessEmployeeInCompanyDepartment(
        User $user,
        string $permission,
        int $companyId,
        ?string $departmentId
    ): bool {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->ownedCompanies()->whereKey($companyId)->exists()) {
            return true;
        }

        $teamIds = $this->teamIdsForScopedCompany($user, $companyId, $departmentId);
        if ($teamIds === []) {
            return false;
        }

        return $this->withTeamContexts($user, $teamIds, function () use ($user, $permission): bool {
            return $user->can($permission);
        });
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function canAnyAccessEmployeeInCompanyDepartment(
        User $user,
        array $permissions,
        int $companyId,
        ?string $departmentId
    ): bool {
        foreach ($permissions as $permission) {
            if ($this->canAccessEmployeeInCompanyDepartment($user, $permission, $companyId, $departmentId)) {
                return true;
            }
        }

        return false;
    }

    public function departmentIdForEmployeeScope(?\App\Models\Employee $employee): ?string
    {
        if ($employee === null || $employee->department_id === null || $employee->department_id === '') {
            return null;
        }

        return (string) $employee->department_id;
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, array{company_id: int, department_ids: array<int, string>|null}>
     */
    public function employeeScopeWhereCan(User $user, array $permissions): array
    {
        if ($user->hasRole('super-admin')) {
            return \App\Models\Company::query()
                ->pluck('id')
                ->map(fn ($id) => [
                    'company_id' => (int) $id,
                    'department_ids' => null,
                ])
                ->all();
        }

        $ownedIds = $user->ownedCompanies()->pluck('id')->map(fn ($id) => (int) $id);
        if ($ownedIds->isNotEmpty()) {
            return $ownedIds
                ->map(fn (int $id) => [
                    'company_id' => $id,
                    'department_ids' => null,
                ])
                ->all();
        }

        $accessRows = DB::table('company_user_access')
            ->where('user_id', $user->id)
            ->get(['company_id', 'team_id', 'department_id']);

        if ($accessRows->isEmpty()) {
            return [];
        }

        $scopes = [];
        $legacyCompanyIds = $accessRows
            ->whereNull('team_id')
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($accessRows->pluck('company_id')->map(fn ($id) => (int) $id)->unique()->values() as $companyId) {
            $teamIds = $this->teamIdsForCompany($user, $companyId);
            $permittedTeamIds = [];

            foreach ($teamIds as $teamId) {
                $hasPermission = $this->withTeamContexts($user, [$teamId], function () use ($user, $permissions): bool {
                    foreach ($permissions as $permission) {
                        if ($user->can($permission)) {
                            return true;
                        }
                    }

                    return false;
                });

                if ($hasPermission) {
                    $permittedTeamIds[] = $teamId;
                }
            }

            if ($permittedTeamIds === []) {
                continue;
            }

            if (in_array($companyId, $legacyCompanyIds, true)) {
                $scopes[] = [
                    'company_id' => $companyId,
                    'department_ids' => null,
                ];
                continue;
            }

            $companyRows = $accessRows
                ->where('company_id', $companyId)
                ->whereIn('team_id', $permittedTeamIds);

            if ($companyRows->contains(fn ($row) => $row->department_id === null)) {
                $scopes[] = [
                    'company_id' => $companyId,
                    'department_ids' => null,
                ];
                continue;
            }

            $departmentIds = $companyRows->pluck('department_id')
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values()
                ->all();

            if ($departmentIds === []) {
                continue;
            }

            $scopes[] = [
                'company_id' => $companyId,
                'department_ids' => $departmentIds,
            ];
        }

        return $scopes;
    }

    /**
     * Company IDs where at least one linked team grants any of the given permissions.
     *
     * @param  array<int, string>  $permissions
     * @return array<int, int>
     */
    public function companyIdsWhereCan(User $user, array $permissions): array
    {
        if ($user->hasRole('super-admin')) {
            return \App\Models\Company::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $ownedIds = $user->ownedCompanies()->pluck('id')->map(fn ($id) => (int) $id);
        if ($ownedIds->isNotEmpty()) {
            return $ownedIds->all();
        }

        $accessRows = DB::table('company_user_access')
            ->where('user_id', $user->id)
            ->get(['company_id', 'team_id']);

        if ($accessRows->isEmpty()) {
            return [];
        }

        $companyIds = $accessRows->pluck('company_id')->map(fn ($id) => (int) $id)->unique()->values();
        $matched = [];

        foreach ($companyIds as $companyId) {
            if ($this->canAnyForCompany($user, $permissions, $companyId)) {
                $matched[] = $companyId;
            }
        }

        return $matched;
    }

    /**
     * Whether the user has the permission in any of their assigned teams.
     */
    public function canInAnyAssignedTeam(User $user, string $permission): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $teamIds = $this->assignedTeamIdsFor($user);
        if ($teamIds === []) {
            $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();
            try {
                app(PermissionRegistrar::class)->setPermissionsTeamId($user->team_id);
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');

                return $user->can($permission);
            } finally {
                app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');
            }
        }

        return $this->withTeamContexts($user, $teamIds, function () use ($user, $permission): bool {
            return $user->can($permission);
        });
    }

    /**
     * Union of permission names across all assigned teams (for menus / route allowlists).
     *
     * @return array<int, string>
     */
    public function permissionNamesAcrossAssignedTeams(User $user): array
    {
        $teamIds = $this->assignedTeamIdsFor($user);
        if ($teamIds === [] && $user->team_id) {
            $teamIds = [(int) $user->team_id];
        }

        if ($teamIds === []) {
            return $user->getAllPermissions()->pluck('name')->map(fn ($name) => (string) $name)->unique()->values()->all();
        }

        $names = [];
        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        try {
            foreach ($teamIds as $teamId) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');

                foreach ($user->getAllPermissions() as $permission) {
                    $names[] = (string) $permission->name;
                }
            }
        } finally {
            app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        }

        return array_values(array_unique($names));
    }

    /**
     * Dashboard subtitle listing all assigned teams when the user has more than one.
     */
    public function dashboardTeamSubtitleFor(User $portalUser): ?string
    {
        $teams = $this->assignedTeamsForUi($portalUser);

        if ($teams === []) {
            return null;
        }

        return collect($teams)->pluck('name')->filter()->implode('، ');
    }

    /**
     * Run a callback while temporarily switching Spatie team context for each team until one succeeds.
     *
     * @param  array<int, int>  $teamIds
     * @param  callable(): bool  $callback
     */
    private function withTeamContexts(User $user, array $teamIds, callable $callback): bool
    {
        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        try {
            foreach ($teamIds as $teamId) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');

                if ($callback()) {
                    return true;
                }
            }
        } finally {
            app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        }

        return false;
    }
}
