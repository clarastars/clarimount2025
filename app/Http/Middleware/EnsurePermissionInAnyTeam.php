<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\EmployeeUserRoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissionInAnyTeam
{
    public function __construct(
        private EmployeeUserRoleService $roleService,
    ) {}

    /**
     * Allow access when the user is super-admin or has any of the given
     * permissions on at least one assigned team (not only the active team_id).
     *
     * @param  string  ...$permissions  Pipe-separated list is also accepted as one argument.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'USER DOES NOT HAVE ANY OF THE NECESSARY ACCESS RIGHTS.');
        }

        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        $needed = [];
        foreach ($permissions as $permission) {
            foreach (explode('|', $permission) as $part) {
                $part = trim($part);
                if ($part !== '' && $part !== 'super-admin') {
                    $needed[] = $part;
                }
            }
        }

        foreach (array_unique($needed) as $permission) {
            if ($this->roleService->canInAnyAssignedTeam($user, $permission)) {
                return $next($request);
            }
        }

        abort(403, 'USER DOES NOT HAVE ANY OF THE NECESSARY ACCESS RIGHTS.');
    }
}
