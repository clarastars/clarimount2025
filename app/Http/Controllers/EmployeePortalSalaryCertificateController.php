<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SalaryCertificateRequest;
use App\Models\User;
use App\Services\SalaryCertificateApprovalService;
use App\Services\SalaryCertificateRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeePortalSalaryCertificateController extends Controller
{
    public function __construct(
        private SalaryCertificateRequestService $requestService,
        private SalaryCertificateApprovalService $approvalService,
    ) {}

    public function index(): Response|RedirectResponse
    {
        $employee = $this->resolvePortalEmployee();
        if ($employee === null) {
            return redirect()->route('dashboard');
        }

        $employee->load(['company']);

        $requests = $employee->salaryCertificateRequests()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SalaryCertificateRequest $request): array => $this->mapRequest($request, $employee->company))
            ->values()
            ->all();

        return Inertia::render('Employee/SalaryCertificates', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'company_name' => $employee->company?->name_ar ?: $employee->company?->name_en,
            ],
            'requests' => $requests,
            'languages' => SalaryCertificateRequest::LANGUAGES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->resolvePortalEmployee();
        abort_unless($employee !== null, 403);

        $this->requestService->submitForEmployee($employee, $request);

        return redirect()
            ->route('employee.salary-certificates.index')
            ->with('success', __('messages.salary_certificates.request_submitted_success'));
    }

    public function destroy(SalaryCertificateRequest $salaryCertificateRequest): RedirectResponse
    {
        $employee = $this->resolvePortalEmployee();
        abort_unless($employee !== null, 403);

        $this->requestService->cancelByEmployee($salaryCertificateRequest, $employee);

        return redirect()
            ->route('employee.salary-certificates.index')
            ->with('success', __('messages.salary_certificates.request_cancelled_success'));
    }

    public function download(SalaryCertificateRequest $salaryCertificateRequest): StreamedResponse
    {
        $employee = $this->resolvePortalEmployee();
        abort_unless($employee !== null, 403);
        abort_unless((int) $salaryCertificateRequest->employee_id === (int) $employee->id, 403);
        abort_unless($salaryCertificateRequest->isCompleted() && $salaryCertificateRequest->certificate_path, 404);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($salaryCertificateRequest->certificateDisk());
        abort_unless($disk->exists($salaryCertificateRequest->certificate_path), 404);

        return $disk->download(
            $salaryCertificateRequest->certificate_path,
            $salaryCertificateRequest->certificateDownloadName()
        );
    }

    private function resolvePortalEmployee()
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
    private function mapRequest(SalaryCertificateRequest $request, ?Company $company = null): array
    {
        $payload = [
            'id' => $request->id,
            'purpose' => $request->purpose,
            'addressed_to' => $request->addressed_to,
            'language' => $request->language,
            'notes' => $request->notes,
            'status' => $request->status,
            'review_notes' => $request->review_notes,
            'has_certificate' => $request->isCompleted() && filled($request->certificate_path),
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
