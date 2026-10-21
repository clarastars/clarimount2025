<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Company;
use App\Models\EmployeeEntitlementSettlement;
use App\Models\User;
use App\Services\EntitlementSettlementApprovalNotificationService;
use App\Services\EntitlementSettlementApprovalService;
use App\Services\EntitlementSettlementPaymentReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyEntitlementSettlementController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private EntitlementSettlementApprovalService $approvalService,
        private EntitlementSettlementApprovalNotificationService $approvalNotificationService,
        private EntitlementSettlementPaymentReceiptService $receiptService,
    ) {}

    public function index(Company $company): Response
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanViewCompanyEntitlementSettlements($user);
        $this->abortUnlessCanAccessCompanyEntitlementSettlements($user, $company);

        $hasApprovalWorkflow = $this->approvalService->hasActiveStepsForCompany($company);

        return Inertia::render('Companies/EntitlementSettlements', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'pendingSettlements' => $this->getCompanySettlementsByStatus(
                $company,
                $user,
                EmployeeEntitlementSettlement::STATUS_PENDING,
                'created_at',
                includeWorkflow: $hasApprovalWorkflow,
            ),
            'approvedSettlements' => $this->getCompanySettlementsByStatus(
                $company,
                $user,
                EmployeeEntitlementSettlement::STATUS_APPROVED,
                'reviewed_at',
                descending: true,
            ),
            'rejectedSettlements' => $this->getCompanySettlementsByStatus(
                $company,
                $user,
                EmployeeEntitlementSettlement::STATUS_REJECTED,
                'reviewed_at',
                descending: true,
            ),
            'hasApprovalWorkflow' => $hasApprovalWorkflow,
        ]);
    }

    public function markPaid(
        Request $request,
        Company $company,
        EmployeeEntitlementSettlement $entitlementSettlement,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanAccessCompanyEntitlementSettlements($user, $company);

        $entitlementSettlement->loadMissing('employee');
        $employee = $entitlementSettlement->employee;
        abort_unless(
            $employee !== null && (int) $employee->company_id === (int) $company->id,
            404
        );
        $this->abortUnlessCanTransferEntitlementSettlement($user, $company, $employee);

        $validated = $request->validate($this->receiptService->validationRules());

        $receiptPath = $this->receiptService->store(
            $request->file('receipt'),
            (int) $employee->id,
            (int) $entitlementSettlement->id,
        );

        try {
            $this->approvalService->markPaid(
                $entitlementSettlement,
                $user,
                $receiptPath,
                $validated['payment_notes'] ?? null,
            );
        } catch (\Throwable $exception) {
            $this->receiptService->delete($receiptPath);
            throw $exception;
        }

        $this->approvalNotificationService->notifyTransferCompleted(
            $entitlementSettlement->fresh(),
            $company,
            $user,
        );

        return back()->with('success', __('messages.entitlement_settlement.payment_marked_paid_success'));
    }

    public function downloadPaymentReceipt(
        Company $company,
        EmployeeEntitlementSettlement $entitlementSettlement,
    ): StreamedResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanAccessCompanyEntitlementSettlements($user, $company);

        $entitlementSettlement->loadMissing('employee');
        $employee = $entitlementSettlement->employee;
        abort_unless(
            $employee !== null && (int) $employee->company_id === (int) $company->id,
            404
        );

        $path = $entitlementSettlement->payment_receipt_path;
        abort_unless(is_string($path) && $path !== '', 404);

        $disk = Storage::disk($this->receiptService->diskName());
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, basename($path));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCompanySettlementsByStatus(
        Company $company,
        User $user,
        string $status,
        string $orderColumn,
        bool $descending = false,
        bool $includeWorkflow = false,
    ): array {
        $query = EmployeeEntitlementSettlement::query()
            ->where('status', $status)
            ->whereHas('employee', function ($query) use ($company, $user): void {
                $query->where('company_id', $company->id);
                $this->applyEmployeePermissionScope(
                    $query,
                    $user,
                    $this->entitlementSettlementWorkflowAccessPermissions()
                );
            })
            ->with([
                'employee:id,first_name,father_name,last_name,company_id,department_id,employee_id,job_title',
                'creator:id,name',
                'paidByUser:id,name',
            ]);

        if ($descending) {
            $query->orderByDesc($orderColumn)->orderByDesc('id');
        } else {
            $query->orderBy($orderColumn)->orderBy('id');
        }

        return $query
            ->get()
            ->map(fn (EmployeeEntitlementSettlement $settlement): array => $this->mapSettlement(
                $settlement,
                $company,
                $user,
                includeWorkflow: $includeWorkflow,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSettlement(
        EmployeeEntitlementSettlement $settlement,
        Company $company,
        User $user,
        bool $includeWorkflow = false,
    ): array {
        $employee = $settlement->employee;
        $canMarkPaid = $settlement->awaitsTransfer()
            && $employee !== null
            && $this->canTransferEntitlementSettlement($user, $company, $employee);

        $payload = [
            'id' => $settlement->id,
            'settlement_date' => $settlement->settlement_date?->format('Y-m-d'),
            'reason' => $settlement->reason,
            'status' => $settlement->status,
            'payment_status' => $settlement->payment_status ?? EmployeeEntitlementSettlement::PAYMENT_UNPAID,
            'payment_notes' => $settlement->payment_notes,
            'paid_at' => $settlement->paid_at?->toIso8601String(),
            'paid_by_name' => $settlement->paidByUser?->name,
            'payment_receipt_url' => (
                is_string($settlement->payment_receipt_path) && $settlement->payment_receipt_path !== ''
            )
                ? route('companies.entitlement-settlements.payment-receipt', [$company, $settlement])
                : null,
            'can_mark_paid' => $canMarkPaid,
            'total_dues' => (float) $settlement->total_dues,
            'total_deductions' => (float) $settlement->total_deductions,
            'net_due' => (float) $settlement->net_due,
            'created_at' => $settlement->created_at?->toIso8601String(),
            'reviewed_at' => $settlement->reviewed_at?->toIso8601String(),
            'created_by_name' => $settlement->creator?->name,
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
                'job_title' => $employee->job_title,
            ],
            'url' => route('employees.entitlement-settlement.show', [$employee, $settlement], absolute: false),
            'current_step_title' => null,
        ];

        if ($includeWorkflow && $settlement->status === EmployeeEntitlementSettlement::STATUS_PENDING) {
            $nextStep = $this->approvalService->getNextPendingStep($settlement);
            $payload['current_step_title'] = $nextStep?->title;
        }

        return $payload;
    }
}
