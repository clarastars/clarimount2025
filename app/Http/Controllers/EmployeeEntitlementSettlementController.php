<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Employee;
use App\Models\EmployeeEntitlementSettlement;
use App\Models\EntitlementSettlementApprovalStep;
use App\Services\EntitlementSettlementApprovalNotificationService;
use App\Services\EntitlementSettlementApprovalService;
use App\Services\EmployeeEntitlementSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeEntitlementSettlementController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private EmployeeEntitlementSettlementService $settlementService,
        private EntitlementSettlementApprovalService $approvalService,
        private EntitlementSettlementApprovalNotificationService $approvalNotificationService,
    ) {}

    public function index(Employee $employee): Response
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanSettleEmployeeEntitlementsForEmployee($user, $employee);

        $settlements = $employee->entitlementSettlements()
            ->with(['creator:id,name'])
            ->orderByDesc('settlement_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeEntitlementSettlement $settlement): array => $this->serializeSettlementSummary($settlement));

        return Inertia::render('Employees/EntitlementSettlements/Index', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
            ],
            'settlements' => $settlements,
        ]);
    }

    public function show(Employee $employee, EmployeeEntitlementSettlement $entitlementSettlement): Response
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        abort_unless((int) $entitlementSettlement->employee_id === (int) $employee->id, 404);
        $this->abortUnlessCanViewEntitlementSettlement($user, $employee, $entitlementSettlement);

        $employee->loadMissing('company');
        $company = $employee->company;
        abort_unless($company !== null, 404);

        $entitlementSettlement->load(['creator:id,name', 'reviewer:id,name']);

        $hasWorkflow = $this->approvalService->hasActiveStepsForCompany($company);

        return Inertia::render('Employees/EntitlementSettlements/Show', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
            ],
            'settlement' => $this->serializeSettlementDetail($entitlementSettlement),
            'has_approval_workflow' => $hasWorkflow,
            'approval_steps' => $hasWorkflow
                ? $this->approvalService->buildApprovalPayload($entitlementSettlement, $user, $company)
                : [],
        ]);
    }

    public function create(Request $request, Employee $employee): Response
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanSettleEmployeeEntitlementsForEmployee($user, $employee);

        $settlementDate = $request->query('settlement_date', now('Asia/Riyadh')->toDateString());
        $preview = $this->settlementService->buildPreview($employee, (string) $settlementDate, $request->only([
            'end_of_service_bonus',
            'travel_tickets',
            'due_commissions',
            'other_dues',
            'custody_deduction',
            'excess_leave_deduction',
            'social_insurance_deduction',
            'notes',
        ]));

        $previousSettlementsCount = $employee->entitlementSettlements()->count();
        $employee->loadMissing('company');
        $hasWorkflow = $employee->company !== null
            && $this->approvalService->hasActiveStepsForCompany($employee->company);

        return Inertia::render('Employees/EntitlementSettlement', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
            ],
            'preview' => $preview,
            'previous_settlements_count' => $previousSettlementsCount,
            'has_approval_workflow' => $hasWorkflow,
            'defaults' => [
                'settlement_date' => (string) $settlementDate,
                'reason' => (string) $request->query('reason', ''),
            ],
        ]);
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanSettleEmployeeEntitlementsForEmployee($user, $employee);

        $validated = $request->validate([
            'settlement_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'end_of_service_bonus' => ['nullable', 'numeric', 'min:0'],
            'travel_tickets' => ['nullable', 'numeric', 'min:0'],
            'due_commissions' => ['nullable', 'numeric', 'min:0'],
            'other_dues' => ['nullable', 'numeric', 'min:0'],
            'custody_deduction' => ['nullable', 'numeric', 'min:0'],
            'excess_leave_deduction' => ['nullable', 'numeric', 'min:0'],
            'social_insurance_deduction' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee->loadMissing('company');
        $company = $employee->company;
        abort_unless($company !== null, 404);

        $hasWorkflow = $this->approvalService->hasActiveStepsForCompany($company);

        if ($hasWorkflow) {
            $validated['status'] = EmployeeEntitlementSettlement::STATUS_PENDING;
        } else {
            $validated['status'] = EmployeeEntitlementSettlement::STATUS_APPROVED;
            $validated['reviewed_by'] = $user->id;
            $validated['reviewed_at'] = now();
        }

        $settlement = $this->settlementService->create($employee, $validated, $user->id);

        if ($hasWorkflow) {
            $this->approvalNotificationService->notifyWorkflowStarted($settlement, $company, $user);
        } else {
            $this->settlementService->applyApprovedSettlementAdjustments($settlement);
        }

        return redirect()
            ->route('employees.entitlement-settlement.show', [$employee, $settlement])
            ->with('success', $hasWorkflow
                ? __('messages.entitlement_settlement.saved_pending_approval')
                : __('messages.entitlement_settlement.saved_success'));
    }

    public function approveWorkflowStep(
        Employee $employee,
        EmployeeEntitlementSettlement $entitlementSettlement,
        EntitlementSettlementApprovalStep $settlementApprovalStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanAccessEmployee($user, $employee);
        abort_unless((int) $entitlementSettlement->employee_id === (int) $employee->id, 404);

        $employee->loadMissing('company');
        $company = $employee->company;
        abort_unless($company !== null, 404);

        if ((int) $settlementApprovalStep->company_id !== (int) $company->id) {
            abort(403);
        }

        if (! $settlementApprovalStep->is_active) {
            abort(403);
        }

        if (! $this->approvalService->canUserApproveStep($user, $company, $entitlementSettlement, $settlementApprovalStep)) {
            abort(403);
        }

        try {
            $this->approvalService->approveStep($user, $entitlementSettlement, $settlementApprovalStep);
        } catch (\RuntimeException $exception) {
            return back()->with('info', $exception->getMessage());
        }

        $entitlementSettlement->refresh();

        if ($this->approvalService->allStepsApproved($entitlementSettlement)) {
            $this->approvalService->markApproved($entitlementSettlement, $user);
            $this->settlementService->applyApprovedSettlementAdjustments($entitlementSettlement->fresh());
            $this->approvalNotificationService->notifyWorkflowFinalized(
                $entitlementSettlement->fresh(),
                $company,
                $user,
            );

            return back()->with('success', __('messages.entitlement_settlement.request_approved_success'));
        }

        $this->approvalNotificationService->notifyStepApproved(
            $entitlementSettlement,
            $company,
            $settlementApprovalStep,
            $user,
        );

        return back()->with('success', __('messages.entitlement_settlement.approval_saved'));
    }

    public function rejectWorkflowStep(
        Request $request,
        Employee $employee,
        EmployeeEntitlementSettlement $entitlementSettlement,
        EntitlementSettlementApprovalStep $settlementApprovalStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanAccessEmployee($user, $employee);
        abort_unless((int) $entitlementSettlement->employee_id === (int) $employee->id, 404);

        $employee->loadMissing('company');
        $company = $employee->company;
        abort_unless($company !== null, 404);

        if ((int) $settlementApprovalStep->company_id !== (int) $company->id) {
            abort(403);
        }

        if (! $settlementApprovalStep->is_active) {
            abort(403);
        }

        if (! $this->approvalService->canUserApproveStep($user, $company, $entitlementSettlement, $settlementApprovalStep)) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $this->approvalService->rejectStep(
                $user,
                $entitlementSettlement,
                $settlementApprovalStep,
                $validated['reason'],
            );
        } catch (\RuntimeException $exception) {
            return back()->with('info', $exception->getMessage());
        }

        $this->approvalNotificationService->notifyStepRejected(
            $entitlementSettlement->fresh(),
            $company,
            $settlementApprovalStep,
            $user,
            $validated['reason'],
        );

        return back()->with('success', __('messages.entitlement_settlement.request_rejected_success'));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettlementSummary(EmployeeEntitlementSettlement $settlement): array
    {
        return [
            'id' => $settlement->id,
            'settlement_date' => $settlement->settlement_date?->toDateString(),
            'reason' => $settlement->reason,
            'status' => $settlement->status,
            'service_days' => (int) $settlement->service_days,
            'total_dues' => (float) $settlement->total_dues,
            'total_deductions' => (float) $settlement->total_deductions,
            'net_due' => (float) $settlement->net_due,
            'created_by_name' => $settlement->creator?->name,
            'created_at' => $settlement->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettlementDetail(EmployeeEntitlementSettlement $settlement): array
    {
        return [
            ...$this->serializeSettlementSummary($settlement),
            'last_settlement_date' => $settlement->last_settlement_date?->toDateString(),
            'basic_salary' => (float) $settlement->basic_salary,
            'allowances' => (float) $settlement->allowances,
            'gross_salary' => (float) $settlement->gross_salary,
            'remaining_leave_days' => (float) $settlement->remaining_leave_days,
            'salary_unpaid_days' => (float) $settlement->salary_unpaid_days,
            'used_annual_leave_days' => (float) $settlement->used_annual_leave_days,
            'end_of_service_bonus' => (float) $settlement->end_of_service_bonus,
            'travel_tickets' => (float) $settlement->travel_tickets,
            'due_commissions' => (float) $settlement->due_commissions,
            'salary_dues' => (float) $settlement->salary_dues,
            'annual_leave_dues' => (float) $settlement->annual_leave_dues,
            'other_dues' => (float) $settlement->other_dues,
            'advances_deduction' => (float) $settlement->advances_deduction,
            'custody_deduction' => (float) $settlement->custody_deduction,
            'excess_leave_deduction' => (float) $settlement->excess_leave_deduction,
            'social_insurance_deduction' => (float) $settlement->social_insurance_deduction,
            'used_annual_leave_deduction' => (float) $settlement->used_annual_leave_deduction,
            'notes' => $settlement->notes,
            'reviewed_by_name' => $settlement->reviewer?->name,
            'reviewed_at' => $settlement->reviewed_at?->toIso8601String(),
            'review_notes' => $settlement->review_notes,
        ];
    }
}
