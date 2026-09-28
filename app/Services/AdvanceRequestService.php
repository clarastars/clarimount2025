<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdvanceRequest;
use App\Models\Employee;
use App\Models\EmployeeDebt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvanceRequestService
{
    public function __construct(
        private AdvanceAmountService $amountService,
        private AdvanceEntitlementService $entitlementService,
        private AdvanceApprovalService $approvalService,
        private AdvanceRequestNotificationService $notificationService,
        private AdvanceApprovalNotificationService $approvalNotificationService,
        private SalaryRunService $salaryRunService,
    ) {}

    public function submitForEmployee(Employee $employee, Request $request): AdvanceRequest
    {
        $entitlement = $this->entitlementService->entitlementFor($employee);

        if (! $entitlement['has_hire_date']) {
            throw ValidationException::withMessages([
                'amount' => [__('messages.advances.missing_hire_date')],
            ]);
        }

        if ($entitlement['block_reason'] === 'no_matching_tier') {
            throw ValidationException::withMessages([
                'amount' => [__('messages.advances.no_matching_tier')],
            ]);
        }

        if (! $entitlement['can_request'] || $entitlement['remaining_amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => [__('messages.advances.no_remaining_entitlement')],
            ]);
        }

        $gross = $this->amountService->grossMonthlyFor($employee);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'installments' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'amount.required' => __('messages.validation.required'),
            'installments.required' => __('messages.validation.required'),
            'reason.required' => __('messages.advances.reason_required'),
        ]);

        $amount = round((float) $validated['amount'], 2);
        $installments = (int) $validated['installments'];
        $maxInstallments = (int) $entitlement['max_installments'];
        $remaining = (float) $entitlement['remaining_amount'];

        if ($amount > $remaining + 0.001) {
            throw ValidationException::withMessages([
                'amount' => [__('messages.advances.amount_exceeds_entitlement', [
                    'max' => number_format($remaining, 2),
                ])],
            ]);
        }

        if ($installments < 1 || $installments > $maxInstallments) {
            throw ValidationException::withMessages([
                'installments' => [__('messages.advances.installments_not_allowed', [
                    'max' => $maxInstallments,
                ])],
            ]);
        }

        $plan = $this->amountService->buildRepaymentScheduleForInstallments($amount, $installments);
        $monthly = (float) ($plan['monthly_deduction'] ?? 0);

        if ($plan['months_count'] < 1 || $monthly <= 0) {
            throw ValidationException::withMessages([
                'installments' => [__('messages.advances.invalid_repayment_plan')],
            ]);
        }

        if ($gross <= 0 || $monthly >= $gross) {
            throw ValidationException::withMessages([
                'installments' => [__('messages.advances.monthly_exceeds_gross')],
            ]);
        }

        if ($employee->advanceRequests()->where('status', AdvanceRequest::STATUS_PENDING)->exists()) {
            throw ValidationException::withMessages([
                'amount' => [__('messages.advances.pending_request_exists')],
            ]);
        }

        $advanceRequest = AdvanceRequest::query()->create([
            'employee_id' => $employee->id,
            'amount' => $amount,
            'monthly_deduction' => $monthly,
            'reason' => $validated['reason'],
            'months_count' => $plan['months_count'],
            'repayment_schedule' => $plan['schedule'],
            'status' => AdvanceRequest::STATUS_PENDING,
        ]);

        $advanceRequest->load(['employee.company']);
        $company = $advanceRequest->employee->company;
        $actor = $advanceRequest->employee->user ?? User::make(['name' => $advanceRequest->employee->full_name]);

        if ($company !== null && $this->approvalService->hasActiveStepsForCompany($company)) {
            $this->approvalNotificationService->notifyWorkflowStarted($advanceRequest, $company, $actor);
        } else {
            $this->notificationService->notifySubmitted($advanceRequest);
        }

        return $advanceRequest;
    }

    /**
     * Final approval: records the decision, creates the linked debt and schedules the
     * first installment in the company's open draft salary run.
     */
    public function approve(
        AdvanceRequest $advanceRequest,
        User $reviewer,
        ?string $reviewNotes = null,
        bool $skipEmployeeNotification = false,
    ): AdvanceRequest {
        if (! $advanceRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('messages.advances.request_already_processed')],
            ]);
        }

        $debt = DB::transaction(function () use ($advanceRequest, $reviewer, $reviewNotes): EmployeeDebt {
            $amount = round((float) $advanceRequest->amount, 2);

            $debt = EmployeeDebt::query()->firstOrCreate(
                ['advance_request_id' => $advanceRequest->id],
                [
                    'employee_id' => $advanceRequest->employee_id,
                    'amount' => $amount,
                    'original_amount' => $amount,
                    'monthly_installment' => round((float) $advanceRequest->monthly_deduction, 2),
                    'debt_type' => EmployeeDebt::TYPE_ADVANCE,
                    'pays_out_via_salary_run' => true,
                ],
            );

            if (! $debt->pays_out_via_salary_run) {
                $debt->update(['pays_out_via_salary_run' => true]);
            }

            $advanceRequest->update([
                'status' => AdvanceRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
                'employee_debt_id' => $debt->id,
            ]);

            return $debt;
        });

        $this->salaryRunService->includeAdvancePayoutInOpenDraft($debt);

        $fresh = $advanceRequest->fresh() ?? $advanceRequest;

        if (! $skipEmployeeNotification) {
            $this->notificationService->notifyEmployeeApproved($fresh);
        }

        return $fresh;
    }

    public function reject(
        AdvanceRequest $advanceRequest,
        User $reviewer,
        ?string $reviewNotes = null,
    ): AdvanceRequest {
        if (! $advanceRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('messages.advances.request_already_processed')],
            ]);
        }

        $advanceRequest->update([
            'status' => AdvanceRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        $fresh = $advanceRequest->fresh() ?? $advanceRequest;
        $this->notificationService->notifyEmployeeRejected($fresh);

        return $fresh;
    }

    public function cancelByEmployee(AdvanceRequest $advanceRequest, Employee $employee): void
    {
        abort_unless((int) $advanceRequest->employee_id === (int) $employee->id, 403);

        if (! $advanceRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('messages.advances.request_already_processed')],
            ]);
        }

        $advanceRequest->update([
            'status' => AdvanceRequest::STATUS_CANCELLED,
        ]);

        $advanceRequest->stepApprovals()->delete();
    }
}
