<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdvanceRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\AdvanceAmountService;
use App\Services\AdvanceApprovalService;
use App\Services\AdvanceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmployeePortalAdvanceController extends Controller
{
    public function __construct(
        private AdvanceRequestService $requestService,
        private AdvanceApprovalService $approvalService,
        private AdvanceAmountService $amountService,
    ) {}

    public function index(): Response|RedirectResponse
    {
        $employee = $this->resolvePortalEmployee();
        if ($employee === null) {
            return redirect()->route('dashboard');
        }

        $employee->load(['company']);

        $requests = $employee->advanceRequests()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AdvanceRequest $request): array => $this->mapRequest($request, $employee->company))
            ->values()
            ->all();

        $gross = $this->amountService->grossMonthlyFor($employee);

        return Inertia::render('Employee/Advances', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'company_name' => $employee->company?->name_ar ?: $employee->company?->name_en,
                'gross_monthly' => $gross,
            ],
            'requests' => $requests,
            'amountOptions' => $this->amountService->allAmountOptions(),
            'monthlyDeductionOptions' => $this->amountService->optionsForGross($gross),
            'hasPendingRequest' => $employee->advanceRequests()
                ->where('status', AdvanceRequest::STATUS_PENDING)
                ->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->resolvePortalEmployee();
        abort_unless($employee !== null, 403);

        $this->requestService->submitForEmployee($employee, $request);

        return redirect()
            ->route('employee.advances.index')
            ->with('success', __('messages.advances.request_submitted_success'));
    }

    public function destroy(AdvanceRequest $advanceRequest): RedirectResponse
    {
        $employee = $this->resolvePortalEmployee();
        abort_unless($employee !== null, 403);

        $this->requestService->cancelByEmployee($advanceRequest, $employee);

        return redirect()
            ->route('employee.advances.index')
            ->with('success', __('messages.advances.request_cancelled_success'));
    }

    private function resolvePortalEmployee(): ?Employee
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        if (! $this->isEmployeePortalUser($user)) {
            return null;
        }

        return $user->employee;
    }

    private function isEmployeePortalUser(User $user): bool
    {
        return $user->roles()->where('name', 'employee')->exists() || $user->employee()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRequest(AdvanceRequest $request, ?Company $company = null): array
    {
        $payload = [
            'id' => $request->id,
            'amount' => (float) $request->amount,
            'monthly_deduction' => (float) $request->monthly_deduction,
            'reason' => $request->reason,
            'months_count' => (int) $request->months_count,
            'repayment_schedule' => $request->schedule(),
            'status' => $request->status,
            'review_notes' => $request->review_notes,
            'created_at' => $request->created_at?->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
        ];

        if ($company !== null) {
            $payload['approval_progress'] = $this->approvalService->buildEmployeeProgressPayload(
                $request,
                $company,
            );
        }

        return $payload;
    }
}
