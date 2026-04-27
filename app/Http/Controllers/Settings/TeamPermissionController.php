<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TeamPermissionController extends Controller
{
    /**
     * @var array<int, array<string, string>>
     */
    private const MANAGED_PERMISSIONS = [
        ['name' => 'asset-inventory.access', 'label' => 'جرد الأصول'],
        ['name' => 'settings.access', 'label' => 'الإعدادات'],
        ['name' => 'companies-salary-runs.global-read-approve', 'label' => 'عرض كل الشركات وكل مسيرات الرواتب مع الاعتماد'],
        ['name' => 'company.readonly', 'label' => 'الاطلاع على بيانات الشركة (قراءة فقط)'],
        ['name' => 'employees.readonly', 'label' => 'الاطلاع على الموظفين (قراءة فقط)'],
        ['name' => 'attendance.readonly', 'label' => 'الاطلاع على الحضور (قراءة فقط)'],
        ['name' => 'salary-runs.readonly', 'label' => 'الاطلاع على مسير الرواتب (قراءة فقط)'],
    ];

    public function index(): Response
    {
        $user = Auth::user();

        $this->ensureManagedPermissionsExist();
        $this->ensureDefaultTeamsExist();

        $managedPermissionNames = collect(self::MANAGED_PERMISSIONS)->pluck('name');
        $permissions = Permission::query()
            ->whereIn('name', $managedPermissionNames)
            ->orderBy('name')
            ->get()
            ->keyBy('name');

        $teams = Team::query()
            ->where('owner_id', $user->id)
            ->orWhere('id', $user->team_id)
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values()
            ->map(function (Team $team) use ($permissions) {
                $role = Role::query()->firstOrCreate([
                    'name' => 'team-member',
                    'guard_name' => 'web',
                    'team_id' => $team->id,
                ]);

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                    'permissions' => collect(self::MANAGED_PERMISSIONS)
                        ->map(fn (array $permission) => [
                            'name' => $permission['name'],
                            'label' => $permission['label'],
                            'enabled' => $role->permissions()->where('name', $permission['name'])->exists(),
                            'id' => $permissions[$permission['name']]->id ?? null,
                        ])
                        ->values(),
                ];
            });

        return Inertia::render('settings/TeamPermissions', [
            'teams' => $teams,
            'availablePermissions' => self::MANAGED_PERMISSIONS,
        ]);
    }

    public function storeTeam(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $team = Team::query()->create([
            'name' => $validated['name'],
            'slug' => $this->buildUniqueTeamSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'owner_id' => Auth::id(),
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        Role::query()->firstOrCreate([
            'name' => 'team-member',
            'guard_name' => 'web',
            'team_id' => $team->id,
        ]);

        return back()->with('success', 'تم إنشاء الفريق بنجاح.');
    }

    public function syncTeamPermissions(Request $request, Team $team): RedirectResponse
    {
        $user = Auth::user();
        $canManageTeam = $team->owner_id === $user->id
            || (int) $user->team_id === (int) $team->id
            || $user->hasRole('super-admin');

        abort_unless($canManageTeam, 403);

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $requestedPermissions = collect($validated['permissions'] ?? [])
            ->intersect(collect(self::MANAGED_PERMISSIONS)->pluck('name'))
            ->values();

        $this->ensureManagedPermissionsExist();

        $role = Role::query()->firstOrCreate([
            'name' => 'team-member',
            'guard_name' => 'web',
            'team_id' => $team->id,
        ]);

        $role->syncPermissions($requestedPermissions->all());

        return back()->with('success', 'تم تحديث صلاحيات الفريق.');
    }

    private function ensureManagedPermissionsExist(): void
    {
        foreach (self::MANAGED_PERMISSIONS as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission['name'],
                'guard_name' => 'web',
            ]);
        }
    }

    private function ensureDefaultTeamsExist(): void
    {
        $defaultTeams = ['الموارد البشرية', 'المحاسبين'];

        foreach ($defaultTeams as $teamName) {
            Team::query()->firstOrCreate(
                [
                    'owner_id' => Auth::id(),
                    'name' => $teamName,
                ],
                [
                    'description' => null,
                    'slug' => $this->buildUniqueTeamSlug($teamName),
                    'subscription_status' => 'active',
                    'is_active' => true,
                ]
            );
        }
    }

    private function buildUniqueTeamSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'team';
        $slug = $base;
        $counter = 1;

        while (Team::query()->where('slug', $slug)->exists()) {
            $counter++;
            $slug = $base.'-'.$counter;
        }

        return $slug;
    }
}

