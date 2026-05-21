<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeExpiryService;
use App\Services\EmployeeUserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(EmployeeExpiryService $employeeExpiryService): Response|RedirectResponse
    {
        $user = Auth::user();

        if ($this->isEmployeePortalUser($user)) {
            $employee = $user->employee;
            if (! $employee) {
                return redirect()->route('logout')->with('error', __('messages.employee_portal_no_employee'));
            }
            $employee->append('remaining_annual_leave_balance');
            $roleService = app(EmployeeUserRoleService::class);
            $primaryTeamName = $roleService->primaryTeamNameFor($user);

            $dashboardSubtitle = $primaryTeamName
                ? __('messages.dashboard.employee_subtitle_with_team', ['team' => $primaryTeamName])
                : __('messages.dashboard.employee_subtitle');

            return Inertia::render('DashboardEmployee', [
                'employee' => $employee->only(['id', 'first_name', 'last_name', 'full_name', 'annual_leave_balance', 'remaining_annual_leave_balance']),
                'dashboardSubtitle' => $dashboardSubtitle,
            ]);
        }

        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        if (! $ownedCompanyIds->count()) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        $expiringRows = $employeeExpiryService->getExpiringDocumentRows($ownedCompanyIds, EmployeeExpiryService::DEFAULT_DAYS_THRESHOLD);
        $expiredRows = $employeeExpiryService->getExpiredDocumentRows($ownedCompanyIds);

        return Inertia::render('Dashboard', [
            'expiringEmployeesPreview' => $expiringRows->take(5)->values(),
            'expiredEmployeesPreview' => $expiredRows->take(5)->values(),
            'expiringEmployeesCount' => $expiringRows->count(),
            'expiredEmployeesCount' => $expiredRows->count(),
            'expiryDaysThreshold' => EmployeeExpiryService::DEFAULT_DAYS_THRESHOLD,
        ]);
    }

    private function isEmployeePortalUser($user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->roles()->where('name', 'employee')->exists() || $user->employee()->exists();
    }
}


