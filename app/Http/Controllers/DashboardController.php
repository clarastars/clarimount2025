<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Services\DashboardPendingApprovalsService;
use App\Services\EmployeeExpiryService;
use App\Services\EmployeeUserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class DashboardController extends Controller
{
    use AuthorizesEmployeeAccess;

    /** @var list<string> */
    private const HR_DASHBOARD_PERMISSIONS = [
        'leaves.approve',
        'leaves.company.view',
        'leaves.create',
        'leaves.requests.receive-email',
        'employees.entitlements.approve',
        'employees.entitlements.settle',
        'employees.readonly',
        'employees.expiry.view',
        'employees.manage',
        'salary-runs.approve',
        'salary-runs.readonly',
        'salary-runs.create',
        'company.readonly',
    ];

    public function index(
        Request $request,
        EmployeeExpiryService $employeeExpiryService,
        DashboardPendingApprovalsService $pendingApprovalsService,
    ): Response|RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        Permission::query()->firstOrCreate([
            'name' => 'employees.expiry.view',
            'guard_name' => 'web',
        ]);

        if ($this->shouldUseEmployeeDashboard($user)) {
            $employee = $user->employee;
            if (! $employee) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with('error', __('messages.employee_portal_no_employee'));
            }
            $roleService = app(EmployeeUserRoleService::class);
            $teamNames = $roleService->dashboardTeamSubtitleFor($user);

            $dashboardSubtitle = $teamNames
                ? __('messages.dashboard.employee_subtitle_with_team', ['team' => $teamNames])
                : __('messages.dashboard.employee_subtitle');

            return Inertia::render('DashboardEmployee', [
                'employee' => $employee->only(['id', 'first_name', 'last_name', 'full_name']),
                'dashboardSubtitle' => $dashboardSubtitle,
            ]);
        }

        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');
        $accessibleCompanyIds = $ownedCompanyIds
            ->merge($user->accessibleCompanies()->pluck('companies.id'))
            ->unique()
            ->values();

        if ($accessibleCompanyIds->isEmpty() && ! $user->hasRole('super-admin')) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        $canViewExpiryDocuments = $this->canViewEmployeeExpiryDocuments($user);
        $expiryCompanyIds = $canViewExpiryDocuments
            ? $this->employeeExpiryCompanyIds($user)
            : collect();

        $expiringRows = $expiryCompanyIds->isNotEmpty()
            ? $employeeExpiryService->getExpiringDocumentRows($expiryCompanyIds, EmployeeExpiryService::DEFAULT_DAYS_THRESHOLD)
            : collect();
        $expiredRows = $expiryCompanyIds->isNotEmpty()
            ? $employeeExpiryService->getExpiredDocumentRows($expiryCompanyIds)
            : collect();

        $pendingApprovals = $pendingApprovalsService->forUser($user);

        return Inertia::render('Dashboard', [
            'canViewExpiryDocuments' => $canViewExpiryDocuments,
            'expiringEmployeesPreview' => $expiringRows->take(5)->values(),
            'expiredEmployeesPreview' => $expiredRows->take(5)->values(),
            'expiringEmployeesCount' => $expiringRows->count(),
            'expiredEmployeesCount' => $expiredRows->count(),
            'expiryDaysThreshold' => EmployeeExpiryService::DEFAULT_DAYS_THRESHOLD,
            'pendingApprovals' => $pendingApprovals,
        ]);
    }

    private function shouldUseEmployeeDashboard($user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super-admin') || $user->ownedCompanies()->exists()) {
            return false;
        }

        $roleService = app(EmployeeUserRoleService::class);
        foreach (self::HR_DASHBOARD_PERMISSIONS as $permission) {
            if ($roleService->canInAnyAssignedTeam($user, $permission)) {
                return false;
            }
        }

        return $user->roles()->where('name', 'employee')->exists() || $user->employee()->exists();
    }
}
