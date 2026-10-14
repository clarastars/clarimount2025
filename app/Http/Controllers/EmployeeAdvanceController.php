<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Employee;
use App\Services\AdvanceAmountService;
use App\Services\AdvanceEntitlementService;
use App\Services\AdvanceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeAdvanceController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private AdvanceRequestService $requestService,
        private AdvanceAmountService $amountService,
        private AdvanceEntitlementService $entitlementService,
    ) {}

    public function create(Employee $employee): Response
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $this->abortUnlessCanSubmitAdvanceForEmployee($user, $employee);

        $employee->load(['company']);

        $gross = $this->amountService->grossMonthlyFor($employee);
        $entitlement = $this->entitlementService->entitlementFor($employee);
        $hasPendingRequest = $employee->advanceRequests()
            ->where('status', \App\Models\AdvanceRequest::STATUS_PENDING)
            ->exists();

        return Inertia::render('Employees/AdvanceCreate', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'company_name' => $employee->company?->name_ar ?: $employee->company?->name_en,
                'gross_monthly' => $gross,
            ],
            'entitlement' => $entitlement,
            'rules' => $this->entitlementService->activeTiersPayload(),
            'hasPendingRequest' => $hasPendingRequest,
        ]);
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $this->abortUnlessCanSubmitAdvanceForEmployee($user, $employee);

        $this->requestService->submitForEmployee($employee, $request, $user);

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', __('messages.advances.request_submitted_on_behalf_success'));
    }
}
