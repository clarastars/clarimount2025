<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Company;
use App\Models\SalaryCertificateApprovalStep;
use App\Models\SalaryCertificateRequest;
use App\Models\User;
use App\Services\SalaryCertificateApprovalNotificationService;
use App\Services\SalaryCertificateApprovalService;
use App\Services\SalaryCertificateDocumentService;
use App\Services\SalaryCertificateRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanySalaryCertificateController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private SalaryCertificateRequestService $requestService,
        private SalaryCertificateApprovalService $approvalService,
        private SalaryCertificateApprovalNotificationService $approvalNotificationService,
        private SalaryCertificateDocumentService $documentService,
    ) {}

    public function index(Company $company): Response
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanViewCompanyLeaves($user);
        $this->abortUnlessCanAccessCompanyLeaves($user, $company);

        $hasApprovalWorkflow = $this->approvalService->hasActiveStepsForCompany($company);
        $canCreateLeaves = $this->canCreateLeaves($user);

        $pendingRequests = $this->getCompanyRequestsByStatus(
            $company,
            $user,
            SalaryCertificateRequest::STATUS_PENDING,
            'created_at',
            includeWorkflow: $hasApprovalWorkflow,
        );
        $completedRequests = $this->getCompanyRequestsByStatus(
            $company,
            $user,
            SalaryCertificateRequest::STATUS_COMPLETED,
            'reviewed_at',
            descending: true,
        );
        $rejectedRequests = $this->getCompanyRequestsByStatus(
            $company,
            $user,
            SalaryCertificateRequest::STATUS_REJECTED,
            'reviewed_at',
            descending: true,
        );

        return Inertia::render('Companies/SalaryCertificates', [
            'company' => $company->only(['id', 'name_en', 'name_ar']),
            'pendingRequests' => $pendingRequests,
            'completedRequests' => $completedRequests,
            'rejectedRequests' => $rejectedRequests,
            'canReviewRequests' => $hasApprovalWorkflow
                ? $this->canApproveLeaveWorkflow($user)
                : $canCreateLeaves,
            'hasApprovalWorkflow' => $hasApprovalWorkflow,
            'isReadOnly' => ! $canCreateLeaves && ! $this->canApproveLeaveWorkflow($user),
        ]);
    }

    public function complete(
        Request $request,
        Company $company,
        SalaryCertificateRequest $salaryCertificateRequest,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        if ($this->approvalService->hasActiveStepsForCompany($company)) {
            abort(403);
        }

        $this->abortUnlessCanCreateLeaves($user);
        $this->abortUnlessCanAccessCompanyLeaves($user, $company);
        $this->abortUnlessRequestBelongsToCompany($salaryCertificateRequest, $company, $user);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->requestService->complete(
            $salaryCertificateRequest,
            $user,
            $validated['review_notes'] ?? null,
        );

        return redirect()
            ->route('companies.salary-certificates.index', $company)
            ->with('success', __('messages.salary_certificates.request_completed_success'));
    }

    public function reject(
        Request $request,
        Company $company,
        SalaryCertificateRequest $salaryCertificateRequest,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        if ($this->approvalService->hasActiveStepsForCompany($company)) {
            abort(403);
        }

        $this->abortUnlessCanCreateLeaves($user);
        $this->abortUnlessCanAccessCompanyLeaves($user, $company);
        $this->abortUnlessRequestBelongsToCompany($salaryCertificateRequest, $company, $user);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->requestService->reject(
            $salaryCertificateRequest,
            $user,
            $validated['review_notes'] ?? null,
        );

        return redirect()
            ->route('companies.salary-certificates.index', $company)
            ->with('success', __('messages.salary_certificates.request_rejected_success'));
    }

    public function approveWorkflowStep(
        Request $request,
        Company $company,
        SalaryCertificateRequest $salaryCertificateRequest,
        SalaryCertificateApprovalStep $salaryCertificateApprovalStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanAccessCompanyLeaves($user, $company);
        $this->abortUnlessRequestBelongsToCompany($salaryCertificateRequest, $company, $user);

        if ((int) $salaryCertificateApprovalStep->company_id !== (int) $company->id) {
            abort(403);
        }

        if (! $salaryCertificateApprovalStep->is_active) {
            abort(403);
        }

        if (! $this->approvalService->canUserApproveStep($user, $company, $salaryCertificateRequest, $salaryCertificateApprovalStep)) {
            abort(403);
        }

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->approvalService->approveStep($user, $salaryCertificateRequest, $salaryCertificateApprovalStep);
        } catch (\RuntimeException $exception) {
            return back()->with('info', $exception->getMessage());
        }

        $salaryCertificateRequest->refresh();

        if ($this->approvalService->allStepsApproved($salaryCertificateRequest)) {
            $this->requestService->complete(
                $salaryCertificateRequest,
                $user,
                $validated['review_notes'] ?? null,
                skipEmployeeNotification: true,
            );

            $this->approvalNotificationService->notifyWorkflowFinalized(
                $salaryCertificateRequest->fresh(),
                $company,
                $user,
            );

            return back()->with('success', __('messages.salary_certificates.request_completed_success'));
        }

        $this->approvalNotificationService->notifyStepApproved(
            $salaryCertificateRequest,
            $company,
            $salaryCertificateApprovalStep,
            $user,
        );

        return back()->with('success', __('messages.salary_certificates.approval_saved'));
    }

    public function rejectWorkflowStep(
        Request $request,
        Company $company,
        SalaryCertificateRequest $salaryCertificateRequest,
        SalaryCertificateApprovalStep $salaryCertificateApprovalStep,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanAccessCompanyLeaves($user, $company);
        $this->abortUnlessRequestBelongsToCompany($salaryCertificateRequest, $company, $user);

        if ((int) $salaryCertificateApprovalStep->company_id !== (int) $company->id) {
            abort(403);
        }

        if (! $salaryCertificateApprovalStep->is_active) {
            abort(403);
        }

        if (! $this->approvalService->canUserApproveStep($user, $company, $salaryCertificateRequest, $salaryCertificateApprovalStep)) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $this->approvalService->rejectStep(
                $user,
                $salaryCertificateRequest,
                $salaryCertificateApprovalStep,
                $validated['reason']
            );
        } catch (\RuntimeException $exception) {
            return back()->with('info', $exception->getMessage());
        }

        $salaryCertificateRequest->refresh();
        $this->approvalNotificationService->notifyStepRejected(
            $salaryCertificateRequest,
            $company,
            $salaryCertificateApprovalStep,
            $user,
            $validated['reason'],
        );

        return back()->with('success', __('messages.salary_certificates.approval_rejection_saved'));
    }

    public function preview(
        Company $company,
        SalaryCertificateRequest $salaryCertificateRequest,
    ): HttpResponse|StreamedResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanViewCompanyLeaves($user);
        $this->abortUnlessCanAccessCompanyLeaves($user, $company);
        $this->abortUnlessRequestBelongsToCompany($salaryCertificateRequest, $company, $user);
        abort_unless(
            $salaryCertificateRequest->isPending() || $salaryCertificateRequest->isCompleted(),
            404
        );

        return $this->certificateFileResponse($salaryCertificateRequest, download: false);
    }

    public function download(
        Company $company,
        SalaryCertificateRequest $salaryCertificateRequest,
    ): HttpResponse|StreamedResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->abortUnlessCanViewCompanyLeaves($user);
        $this->abortUnlessCanAccessCompanyLeaves($user, $company);
        $this->abortUnlessRequestBelongsToCompany($salaryCertificateRequest, $company, $user);
        abort_unless(
            $salaryCertificateRequest->isPending() || $salaryCertificateRequest->isCompleted(),
            404
        );

        return $this->certificateFileResponse($salaryCertificateRequest, download: true);
    }

    private function certificateFileResponse(
        SalaryCertificateRequest $salaryCertificateRequest,
        bool $download,
    ): HttpResponse|StreamedResponse {
        $filename = $salaryCertificateRequest->certificateDownloadName();

        if ($salaryCertificateRequest->isCompleted() && filled($salaryCertificateRequest->certificate_path)) {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk($salaryCertificateRequest->certificateDisk());

            if ($disk->exists($salaryCertificateRequest->certificate_path)) {
                if ($download) {
                    return $disk->download($salaryCertificateRequest->certificate_path, $filename);
                }

                return $disk->response($salaryCertificateRequest->certificate_path, $filename, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="'.$filename.'"',
                ]);
            }
        }

        if (! $download) {
            return response($this->documentService->previewHtml($salaryCertificateRequest))
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return response($this->documentService->renderPdf($salaryCertificateRequest), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function canApproveLeaveWorkflow(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('leaves.approve') || $user->can('leaves.create');
    }

    private function abortUnlessRequestBelongsToCompany(
        SalaryCertificateRequest $request,
        Company $company,
        User $user,
    ): void {
        $employee = $request->employee()->first(['id', 'company_id', 'department_id']);

        abort_unless(
            $employee !== null
            && (int) $employee->company_id === (int) $company->id
            && $this->canAccessEmployeeForLeaveWorkflow($user, $employee),
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
        $query = SalaryCertificateRequest::query()
            ->where('status', $status)
            ->whereHas('employee', function ($query) use ($company, $user): void {
                $query->where('company_id', $company->id);
                $this->applyEmployeePermissionScope(
                    $query,
                    $user,
                    $this->leaveWorkflowAccessPermissions()
                );
            })
            ->with([
                'employee:id,first_name,father_name,last_name,company_id,job_title,department_id',
                'reviewer:id,name',
            ]);

        if ($descending) {
            $query->orderByDesc($orderColumn);
        } else {
            $query->orderBy($orderColumn);
        }

        return $query
            ->get()
            ->map(fn (SalaryCertificateRequest $request): array => $this->mapRequest(
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
        SalaryCertificateRequest $request,
        Company $company,
        User $user,
        bool $includeWorkflow = false,
    ): array {
        $employee = $request->employee;

        $payload = [
            'id' => $request->id,
            'purpose' => $request->purpose,
            'addressed_to' => $request->addressed_to,
            'language' => $request->language,
            'notes' => $request->notes,
            'status' => $request->status,
            'review_notes' => $request->review_notes,
            'has_certificate' => $request->isCompleted() && filled($request->certificate_path),
            'can_preview' => $request->isPending() || $request->isCompleted(),
            'created_at' => $request->created_at?->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'reviewer_name' => $request->reviewer?->name,
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'job_title' => $employee->job_title,
            ],
        ];

        if ($includeWorkflow && $request->isPending()) {
            $payload['approval_steps'] = $this->approvalService->buildApprovalPayload($request, $user, $company);
            $payload['latest_rejection'] = $this->approvalService->buildLatestRejectionPayload($request);
        }

        return $payload;
    }
}
