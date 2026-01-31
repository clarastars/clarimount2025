<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EmployeeExpiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(EmployeeExpiryService $employeeExpiryService): Response|RedirectResponse
    {
        $user = Auth::user();
        $ownedCompanyIds = $user->ownedCompanies()->pluck('id');

        if (!$ownedCompanyIds) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage employees.');
        }

        $expiringRows = $employeeExpiryService->getExpiringDocumentRows($ownedCompanyIds, EmployeeExpiryService::DEFAULT_DAYS_THRESHOLD);
        $expiredRows = $employeeExpiryService->getExpiredDocumentRows($ownedCompanyIds);

        // Combine both expiring and expired, prioritizing expiring ones
        $allRows = $expiringRows->concat($expiredRows);

        return Inertia::render('Dashboard', [
            'expiringEmployeesPreview' => $expiringRows->take(5)->values(),
            'expiredEmployeesPreview' => $expiredRows->take(5)->values(),
            'expiringEmployeesCount' => $expiringRows->count(),
            'expiredEmployeesCount' => $expiredRows->count(),
            'expiryDaysThreshold' => EmployeeExpiryService::DEFAULT_DAYS_THRESHOLD,
        ]);
    }
}


