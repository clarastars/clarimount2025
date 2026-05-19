<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\PermissionRegistrar;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $userLanguage = $request->user()?->language ?? 'ar';
        $user = $request->user();
        if ($user?->team_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->team_id);
        }

        $isSuperAdmin = $user?->hasRole('super-admin') ?? false;
        $permissionNames = $user?->getAllPermissions()->pluck('name')->values()->all() ?? [];
        $globalEmployeeSearchEnabled = $this->isEmployeeGlobalSearchEnabled();
        
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'is_employee' => ($user?->roles()->where('name', 'employee')->exists() ?? false) || ($user?->employee()->exists() ?? false),
                'permissions' => $permissionNames,
                'can_access_settings' => $isSuperAdmin || in_array('settings.access', $permissionNames, true),
                'can_access_asset_inventory' => $isSuperAdmin || in_array('asset-inventory.access', $permissionNames, true),
                'can_view_company_readonly' => $isSuperAdmin || in_array('company.readonly', $permissionNames, true),
                'can_view_employees_readonly' => $isSuperAdmin || in_array('employees.readonly', $permissionNames, true) || in_array('employees.manage', $permissionNames, true),
                'can_manage_employees' => $isSuperAdmin
                    || ($user !== null && $user->ownedCompanies()->exists())
                    || in_array('employees.manage', $permissionNames, true),
                'can_update_employee_custody' => $isSuperAdmin
                    || ($user !== null && $user->ownedCompanies()->exists())
                    || in_array('employees.custody.update', $permissionNames, true),
                'can_view_attendance_readonly' => $isSuperAdmin || in_array('attendance.readonly', $permissionNames, true),
                'can_manage_attendance_adjustments' => $isSuperAdmin
                    || ($user !== null && $user->ownedCompanies()->exists())
                    || in_array('attendance.adjustments.manage', $permissionNames, true),
                'can_view_salary_runs_readonly' => $isSuperAdmin
                    || in_array('salary-runs.readonly', $permissionNames, true)
                    || in_array('salary-runs.approve', $permissionNames, true)
                    || in_array('salary-runs.create', $permissionNames, true)
                    || in_array('salary-runs.delete', $permissionNames, true),
                'can_approve_salary_runs' => $isSuperAdmin
                    || ($user !== null && $user->ownedCompanies()->exists())
                    || in_array('salary-runs.approve', $permissionNames, true),
                'can_use_employee_global_search' => $user !== null && (
                    $isSuperAdmin
                    || $user->ownedCompanies()->exists()
                    || $user->can('employees.global-search')
                ),
                'can_view_salary_run_notifications' => $user !== null && (
                    $isSuperAdmin
                    || $user->ownedCompanies()->exists()
                    || in_array('salary-runs.readonly', $permissionNames, true)
                    || in_array('salary-runs.approve', $permissionNames, true)
                ),
                'unread_notifications_count' => $user?->unreadNotifications()->count() ?? 0,
            ],
            'locale' => $userLanguage,
            'translations' => $this->getTranslations($userLanguage),
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'ui' => [
                'show_employee_global_search' => $globalEmployeeSearchEnabled,
            ],
        ];
    }

    /**
     * Get translations for the current language
     */
    private function getTranslations(string $locale): array
    {
        $translationPath = resource_path("lang/{$locale}/messages.php");
        
        if (file_exists($translationPath)) {
            return require $translationPath;
        }
        
        // Fallback to English if the language file doesn't exist
        return require resource_path('lang/en/messages.php');
    }

    private function isEmployeeGlobalSearchEnabled(): bool
    {
        $value = SystemSetting::query()
            ->where('key', 'employee_global_search_enabled')
            ->value('value');

        if ($value === null) {
            return true;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}
