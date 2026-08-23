<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Employee;
use App\Models\EmployeeEntitlementSettlement;
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

        $this->abortUnlessCanSettleEmployeeEntitlementsForEmployee($user, $employee);
        abort_unless((int) $entitlementSettlement->employee_id === (int) $employee->id, 404);

        $entitlementSettlement->load(['creator:id,name']);

        return Inertia::render('Employees/EntitlementSettlements/Show', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
            ],
            'settlement' => $this->serializeSettlementDetail($entitlementSettlement),
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

        return Inertia::render('Employees/EntitlementSettlement', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_id' => $employee->employee_id,
            ],
            'preview' => $preview,
            'previous_settlements_count' => $previousSettlementsCount,
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

        $this->settlementService->create($employee, $validated, $user->id);

        return redirect()
            ->route('employees.entitlement-settlement.index', $employee)
            ->with('success', __('messages.entitlement_settlement.saved_success'));
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
        ];
    }
}
