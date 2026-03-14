<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfEmployeePortalUser
{
    /**
     * If the user has the global "employee" role, only allow access to dashboard and logout.
     * Redirect to dashboard for any other route.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $isEmployee = $user->roles()->where('name', 'employee')->wherePivotNull('team_id')->exists();
        if (! $isEmployee) {
            return $next($request);
        }

        $allowed = ['dashboard', 'logout', 'password.request', 'password.email', 'password.reset', 'verification.notice', 'profile.show'];
        if ($request->routeIs($allowed)) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
