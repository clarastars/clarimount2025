<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\AdvanceApprovalStep;
use App\Models\AdvanceRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\AdvanceAmountService;
use App\Services\AdvanceApprovalNotificationService;
use App\Services\AdvanceApprovalService;
use App\Services\AdvanceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CompanyAdvanceController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private AdvanceRequestService $requestService,
        private AdvanceApprovalService $approvalService,
        private AdvanceApprovalNotificationService $approvalNotificationService,
        private AdvanceAmountService $amountService,
    ) {}

    public function index(Company $company): Response
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanViewCompanyAdvances($user);
        $this->abortUnlessCanAccessCompanyAdvances($user, $company);

        $hasApprovalWorkflow = $this->approvalService->hasActiveStepsForCompany($company);
        $canCreateAdvances = $this->canCreateAdvances($user);

        $pendingRequests = $this->getCompanyRequestsByStatus(
            $company,
            $user,
            AdvanceRequest::STATUS_PENDING,
            'created_at',
            includeWorkflow: $hasApprovalWorkflow,
        );
        $approvedRequests = $this->getCompanyRequestsByStatus(
            $company,
            $user,
            AdvanceRequest::STATUS_APPROVED,
            'reviewed_at',
            descending: true,
        );
        $rejectedRequests = $this->getCompanyRequestsByStatus(
            $company,
            $user,
            AdvanceRequest::STATUS_REJECTED,
            'reviewed_at',
            descending: true,
        );

        return Inertia::render('Companies/Advances', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'rejectedRequests' => $rejectedRequests,
            'canReviewRequests' => $hasApprovalWorkflow
                ? $this->canApproveAdvances($user)
                : $canCreateAdvances,
            'hasApprovalWorkflow' => $hasApprovalWorkflow,
            'isReadOnly' => ! $canCreateAdvances && ! $this->canApproveAdvances($user),
        ]);
    }

    public function approve(
        Request $request,
        Company $company,
        AdvanceRequest $advanceRequest,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        if ($this->approvalService->hasActiveStepsForCompany($company)) {
            abort(403);
        }

        $this->abortUnlessCanCreateAdvances($user);
        $this->abortUnlessCanAccessCompanyAdvances($user, $company);
        $this->abortUnlessRequestBelongsToCompany($advanceRequest, $company, $user);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->requestService->approve(
            $advanceRequest,
            $user,
            $validated['review_notes'] ?? null,
        );

        return redirect()
            ->route('companies.advances.index', $company)
            ->with('success', __('messages.advances.request_approved_success'));
    }

    public function reject(
        Request $request,
        Company $company,
        AdvanceRequest $advanceRequest,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        if ($this->approvalService->hasActiveStepsForCompany($company)) {
            abort(403);
        }

        $this->abortUnlessCanCreateAdvances($user);
        $this->abortUnlessCanAccessCompanyAdvances($user, $company);
        $this->abortUnlessRequestBelongsToCompany($advanceRequest, $company, $user);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->requestService->reject(
            $advanceRequest,
            $user,
            $validated['review_notes'] ?? null,
        );

        return redirect()
            ->route('companies.advances.index', $company)
            ->with('success', __('messages.advances.request_rejected_success'));
    }

    public function approveWorkflowStep(
        Request $request,
        Company $company,
        AdvanceRequest $advanceRequest,
        AdvanceApprovalStep $advanceApprovalStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanAccessCompanyAdvances($user, $company);
        $this->abortUnlessRequestBelongsToCompany($advanceRequest, $company, $user);
        $this->abortUnlessStepIsApprovable($user, $company, $advanceRequest, $advanceApprovalStep);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->approvalService->approveStep($user, $advanceRequest, $advanceApprovalStep);
        } catch (\RuntimeException $exception) {
            return back()->with('info', $exception->getMessage());
        }

        $advanceRequest->refresh();

        if ($this->approvalService->allStepsApproved($advanceRequest)) {
            $this->requestService->approve(
                $advanceRequest,
                $user,
                $validated['review_notes'] ?? null,
                skipEmployeeNotification: true,
            );

            $this->approvalNotificationService->notifyWorkflowFinalized(
                $advanceRequest->fresh(),
                $company,
                $user,
            );

            return back()->with('success', __('messages.advances.request_approved_success'));
        }

        $this->approvalNotificationService->notifyStepApproved(
            $advanceRequest,
            $company,
            $advanceApprovalStep,
            $user,
        );

        return back()->with('success', __('messages.advances.approval_saved'));
    }

    public function rejectWorkflowStep(
        Request $request,
        Company $company,
        AdvanceRequest $advanceRequest,
        AdvanceApprovalStep $advanceApprovalStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanAccessCompanyAdvances($user, $company);
        $this->abortUnlessRequestBelongsToCompany($advanceRequest, $company, $user);
        $this->abortUnlessStepIsApprovable($user, $company, $advanceRequest, $advanceApprovalStep);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $this->approvalService->rejectStep(
                $user,
                $advanceRequest,
                $advanceApprovalStep,
                $validated['reason']
            );
        } catch (\RuntimeException $exception) {
            return back()->with('info', $exception->getMessage());
        }

        $advanceRequest->refresh();
        $this->approvalNotificationService->notifyStepRejected(
            $advanceRequest,
            $company,
            $advanceApprovalStep,
            $user,
            $validated['reason'],
        );

        return back()->with('success', __('messages.advances.request_rejected_success'));
    }

    private function abortUnlessStepIsApprovable(
        User $user,
        Company $company,
        AdvanceRequest $advanceRequest,
        AdvanceApprovalStep $step,
    ): void {
        if ((int) $step->company_id !== (int) $company->id) {
            abort(403);
        }

        if (! $step->is_active) {
            abort(403);
        }

        if (! $this->approvalService->canUserApproveStep($user, $company, $advanceRequest, $step)) {
            abort(403);
        }
    }

    private function abortUnlessRequestBelongsToCompany(
        AdvanceRequest $request,
        Company $company,
        User $user,
    ): void {
        $employee = $request->employee()->first(['id', 'company_id', 'department_id']);

        abort_unless(
            $employee !== null
            && (int) $employee->company_id === (int) $company->id
            && $this->canAccessEmployeeForAdvanceWorkflow($user, $employee),
            404
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCompanyRequestsByStatus(
        Company $company,
        User $user,
        string $status,
        string $orderColumn,
        bool $descending = false,
        bool $includeWorkflow = false,
    ): array {
        $query = AdvanceRequest::query()
            ->where('status', $status)
            ->whereHas('employee', function ($query) use ($company, $user): void {
                $query->where('company_id', $company->id);
                $this->applyEmployeePermissionScope(
                    $query,
                    $user,
                    $this->advanceWorkflowAccessPermissions()
                );
            })
            ->with([
                'employee:id,first_name,father_name,last_name,company_id,job_title,department_id,hire_date,basic_salary,allowances',
                'reviewer:id,name',
            ]);

        if ($descending) {
            $query->orderByDesc($orderColumn);
        } else {
            $query->orderBy($orderColumn);
        }

        return $query
            ->get()
            ->map(fn (AdvanceRequest $request): array => $this->mapRequest(
                $request,
                $company,
                $user,
                $includeWorkflow,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRequest(
        AdvanceRequest $request,
        Company $company,
        User $user,
        bool $includeWorkflow = false,
    ): array {
        $employee = $request->employee;

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
            'reviewer_name' => $request->reviewer?->name,
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'job_title' => $employee->job_title,
                'hire_date' => $employee->hire_date?->format('Y-m-d'),
                'gross_monthly' => $this->amountService->grossMonthlyFor($employee),
            ],
        ];

        if ($includeWorkflow && $request->isPending()) {
            $payload['approval_steps'] = $this->approvalService->buildApprovalPayload($request, $user, $company);
            $payload['latest_rejection'] = $this->approvalService->buildLatestRejectionPayload($request);
        }

        return $payload;
    }
}
