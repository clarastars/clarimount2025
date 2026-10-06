<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Company;
use App\Models\EmployeeEntitlementSettlement;
use App\Models\User;
use App\Services\EntitlementSettlementApprovalService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CompanyEntitlementSettlementController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private EntitlementSettlementApprovalService $approvalService,
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
        bool $includeWorkflow = false,
    ): array {
        $employee = $settlement->employee;

        $payload = [
            'id' => $settlement->id,
            'settlement_date' => $settlement->settlement_date?->format('Y-m-d'),
            'reason' => $settlement->reason,
            'status' => $settlement->status,
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
