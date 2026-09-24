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
        private AdvanceApprovalService $approvalService,
        private AdvanceRequestNotificationService $notificationService,
        private AdvanceApprovalNotificationService $approvalNotificationService,
        private SalaryRunService $salaryRunService,
    ) {}

    public function submitForEmployee(Employee $employee, Request $request): AdvanceRequest
    {
        $gross = $this->amountService->grossMonthlyFor($employee);
        $monthlyOptions = $this->amountService->optionsForGross($gross);

        if ($monthlyOptions === []) {
            throw ValidationException::withMessages([
                'monthly_deduction' => [__('messages.advances.no_amounts_available')],
            ]);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'monthly_deduction' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'amount.required' => __('messages.validation.required'),
            'monthly_deduction.required' => __('messages.validation.required'),
            'reason.required' => __('messages.advances.reason_required'),
        ]);

        $amount = round((float) $validated['amount'], 2);
        $monthly = round((float) $validated['monthly_deduction'], 2);

        if (! $this->amountService->isAllowedAmount($amount) || abs($amount - (int) $amount) > 0.001) {
            throw ValidationException::withMessages([
                'amount' => [__('messages.advances.amount_not_allowed')],
            ]);
        }

        if (! in_array((int) $monthly, $monthlyOptions, true) || abs($monthly - (int) $monthly) > 0.001) {
            throw ValidationException::withMessages([
                'monthly_deduction' => [__('messages.advances.monthly_not_allowed')],
            ]);
        }

        if ($monthly > $amount) {
            throw ValidationException::withMessages([
                'monthly_deduction' => [__('messages.advances.monthly_exceeds_amount')],
            ]);
        }

        if ($employee->advanceRequests()->where('status', AdvanceRequest::STATUS_PENDING)->exists()) {
            throw ValidationException::withMessages([
                'amount' => [__('messages.advances.pending_request_exists')],
            ]);
        }

        $plan = $this->amountService->buildRepaymentSchedule($amount, $monthly);

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
                ],
            );

            $advanceRequest->update([
                'status' => AdvanceRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
                'employee_debt_id' => $debt->id,
            ]);

            return $debt;
        });

        $this->salaryRunService->includeAdvanceDebtInOpenDraft($debt);

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
