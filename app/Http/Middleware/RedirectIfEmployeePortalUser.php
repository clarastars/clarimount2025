<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfEmployeePortalUser
{
    /**
     * If the user has the "employee" role, only allow dashboard + explicitly granted sections.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $isEmployee = $user->roles()->where('name', 'employee')->exists() || $user->employee()->exists();
        if (! $isEmployee) {
            return $next($request);
        }

        if ($user->team_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->team_id);
        }

        $allowed = ['dashboard', 'logout', 'password.request', 'password.email', 'password.reset', 'verification.notice', 'profile.show'];

        // Allow employees to access only the sections explicitly granted by team permissions.
        if ($user->can('asset-inventory.access')) {
            $allowed = array_merge($allowed, [
                'locations.*',
                'assets.*',
                'asset-templates.*',
                'asset-categories.*',
                'api.locations.search',
                'api.asset-templates.search',
                'api.asset-templates.by-category',
                'api.assets.*',
            ]);
        }

        if ($user->can('settings.access')) {
            $allowed = array_merge($allowed, [
                'settings.*',
                'appearance',
                'password.*',
                'profile.*',
                'shifts.*',
                'labor-law-rules.*',
            ]);
        }

        if ($request->routeIs($allowed)) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
