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

        if ($user->can('company.readonly')) {
            $allowed = array_merge($allowed, [
                'companies.index',
                'companies.show',
                'api.companies.search',
            ]);
        }

        if ($user->can('employees.readonly')) {
            $allowed = array_merge($allowed, [
                'employees.index',
                'employees.show',
                'employees.expiring-documents.index',
                'api.employees.search',
            ]);
        }

        if ($user->can('employees.global-search')) {
            $allowed = array_merge($allowed, [
                'employees.show',
                'api.employees.global-search',
            ]);
        }

        if ($user->can('employees.manage')) {
            $allowed = array_merge($allowed, [
                'employees.index',
                'employees.show',
                'employees.create',
                'employees.store',
                'employees.edit',
                'employees.update',
                'employees.destroy',
                'employees.fingerprint-link',
                'employees.fingerprint-device',
                'employees.expiring-documents.index',
                'employees.import',
                'employees.import.upload',
                'employees.import.sample-csv',
                'employees.export-csv',
                'employees.import.process',
                'employees.import.execute',
                'api.employees.search',
                'api.employees.fingerprint-device-list',
            ]);
        }

        if ($user->can('employees.custody.update')) {
            $allowed = array_merge($allowed, [
                'employees.index',
                'employees.show',
                'employees.custody.show',
                'employees.custody.store',
                'custody.document',
                'custody.upload',
                'api.custody.available-assets',
                'api.employees.search',
            ]);
        }

        if ($user->can('attendance.readonly')) {
            $allowed = array_merge($allowed, [
                'attendance.index',
                'attendance.late',
                'attendance.show',
            ]);
        }

        if ($user->can('attendance.adjustments.manage')) {
            $allowed = array_merge($allowed, [
                'attendance.index',
                'attendance.late',
                'attendance.deductions',
                'attendance.deductions.store',
                'attendance.deductions.update',
                'attendance.deductions.destroy',
                'attendance.additions',
                'attendance.additions.store',
                'attendance.additions.update',
                'attendance.additions.destroy',
                'attendance-penalties.approve',
                'attendance-penalties.reject',
            ]);
        }

        if ($user->can('salary-runs.readonly')) {
            $allowed = array_merge($allowed, [
                'salary-runs.index',
                'salary-runs.show',
                'salary-runs.export-excel',
            ]);
        }

        if ($user->can('salary-runs.approve')) {
            $allowed = array_merge($allowed, [
                'salary-runs.index',
                'salary-runs.show',
                'salary-runs.export-excel',
                'salary-runs.approve-step',
            ]);
        }

        if ($request->routeIs($allowed)) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
