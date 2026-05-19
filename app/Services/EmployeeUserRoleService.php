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
     * @return array<int, array{team_id: int, role_name: string}>
     */
    public function assignedTeamRoleAssignments(User $portalUser): array
    {
        $teamsKey = $this->teamPivotKey();
        $pivotTable = config('permission.table_names.model_has_roles');
        $rolesTable = config('permission.table_names.roles');

        return DB::table($pivotTable)
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
     * @param  array<int, array{team_id?: int|string|null, role_name?: string|null}>  $teamRoleAssignments
     * @param  array<int, string>  $globalRoleNames
     * @param  array<int, int|string>  $roleCompanyIds
     */
    public function sync(
        User $portalUser,
        User $actingUser,
        array $teamRoleAssignments,
        ?int $primaryTeamId,
        array $globalRoleNames,
        array $roleCompanyIds
    ): void {
        $manageableTeamIds = $this->manageableTeamsFor($actingUser)->pluck('id')->map(fn ($id) => (int) $id);

        $normalizedAssignments = collect($teamRoleAssignments)
            ->map(function (array $row) use ($manageableTeamIds) {
                $teamId = isset($row['team_id']) ? (int) $row['team_id'] : 0;
                $roleName = isset($row['role_name']) ? (string) $row['role_name'] : '';

                if ($teamId === 0 || ! $manageableTeamIds->contains($teamId)) {
                    return null;
                }

                if ($roleName === '' || ! array_key_exists($roleName, self::TEAM_ROLES)) {
                    $roleName = 'team-member';
                }

                return ['team_id' => $teamId, 'role_name' => $roleName];
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
        $portalUser->accessibleCompanies()->sync($roleCompanyIds);

        app(PermissionRegistrar::class)->setPermissionsTeamId($portalUser->team_id);
        $portalUser->unsetRelation('roles');
        $portalUser->unsetRelation('permissions');
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
     * @return array<int, array{id: int, name: string}>
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

        return collect($assignments)
            ->map(function (array $row) use ($teamNames) {
                $teamId = (int) $row['team_id'];

                return [
                    'id' => $teamId,
                    'name' => (string) ($teamNames[$teamId] ?? $teamId),
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
}
