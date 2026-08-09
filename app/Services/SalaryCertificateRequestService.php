<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\SalaryCertificateRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalaryCertificateRequestService
{
    public function __construct(
        private SalaryCertificateRequestNotificationService $notificationService,
        private SalaryCertificateApprovalService $approvalService,
        private SalaryCertificateApprovalNotificationService $approvalNotificationService,
        private SalaryCertificateDocumentService $documentService,
    ) {}

    public function submitForEmployee(Employee $employee, Request $request): SalaryCertificateRequest
    {
        $validated = $request->validate([
            'purpose' => ['required', 'string', 'max:500'],
            'addressed_to' => ['nullable', 'string', 'max:255'],
            'language' => ['required', 'string', Rule::in(SalaryCertificateRequest::LANGUAGES)],
            'attestation_type' => ['required', 'string', Rule::in(SalaryCertificateRequest::ATTESTATION_TYPES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $certificateRequest = SalaryCertificateRequest::query()->create([
            'employee_id' => $employee->id,
            'purpose' => $validated['purpose'],
            'addressed_to' => $validated['addressed_to'] ?? null,
            'language' => $validated['language'],
            'attestation_type' => $validated['attestation_type'],
            'notes' => $validated['notes'] ?? null,
            'status' => SalaryCertificateRequest::STATUS_PENDING,
        ]);

        $certificateRequest->load(['employee.company']);
        $company = $certificateRequest->employee->company;
        $actor = $certificateRequest->employee->user ?? User::make(['name' => $certificateRequest->employee->full_name]);

        if ($company !== null && $this->approvalService->hasActiveStepsForCompany($company)) {
            $this->approvalNotificationService->notifyWorkflowStarted($certificateRequest, $company, $actor);
        } else {
            $this->notificationService->notifySubmitted($certificateRequest);
        }

        return $certificateRequest;
    }

    public function complete(
        SalaryCertificateRequest $certificateRequest,
        User $reviewer,
        ?string $reviewNotes = null,
        bool $skipEmployeeNotification = false,
        ?UploadedFile $uploadedCertificate = null,
    ): SalaryCertificateRequest {
        if (! $certificateRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('messages.salary_certificates.request_already_processed')],
            ]);
        }

        $previousDisk = $certificateRequest->certificateDisk();
        $previousPath = $certificateRequest->certificate_path;

        if ($certificateRequest->requiresChamberAttestation()) {
            if ($uploadedCertificate === null) {
                throw ValidationException::withMessages([
                    'certificate' => [__('messages.salary_certificates.chamber_upload_required')],
                ]);
            }

            $stored = $this->storeUploadedCertificate($certificateRequest, $uploadedCertificate);
        } else {
            $stored = $this->documentService->storeGeneratedPdf($certificateRequest);
        }

        if ($previousPath && Storage::disk($previousDisk)->exists($previousPath)) {
            Storage::disk($previousDisk)->delete($previousPath);
        }

        $certificateRequest->update([
            'status' => SalaryCertificateRequest::STATUS_COMPLETED,
            'certificate_path' => $stored['path'],
            'certificate_disk' => $stored['disk'],
            'certificate_name' => $stored['name'],
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        $fresh = $certificateRequest->fresh();

        if (! $skipEmployeeNotification) {
            $this->notificationService->notifyEmployeeCompleted($fresh);
        }

        return $fresh;
    }

    public function reject(
        SalaryCertificateRequest $certificateRequest,
        User $reviewer,
        ?string $reviewNotes = null,
    ): SalaryCertificateRequest {
        if (! $certificateRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('messages.salary_certificates.request_already_processed')],
            ]);
        }

        $certificateRequest->update([
            'status' => SalaryCertificateRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        $fresh = $certificateRequest->fresh();
        $this->notificationService->notifyEmployeeRejected($fresh);

        return $fresh;
    }

    public function cancelByEmployee(
        SalaryCertificateRequest $certificateRequest,
        Employee $employee,
    ): void {
        abort_unless((int) $certificateRequest->employee_id === (int) $employee->id, 403);

        if (! $certificateRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('messages.salary_certificates.request_already_processed')],
            ]);
        }

        $certificateRequest->update([
            'status' => SalaryCertificateRequest::STATUS_CANCELLED,
        ]);

        $certificateRequest->stepApprovals()->delete();
    }

    public function certificateDiskName(): string
    {
        return (string) config('filesystems.cloud', 's3');
    }

    /**
     * @return array{disk: string, path: string, name: string}
     */
    private function storeUploadedCertificate(
        SalaryCertificateRequest $certificateRequest,
        UploadedFile $certificate,
    ): array {
        $diskName = $this->certificateDiskName();
        $extension = $certificate->getClientOriginalExtension() ?: 'pdf';
        $filename = sprintf(
            'salary-certificate-%d-%s.%s',
            $certificateRequest->id,
            now()->format('YmdHis'),
            $extension,
        );

        $path = $certificate->storeAs(
            'salary-certificates/'.$certificateRequest->employee_id,
            $filename,
            ['disk' => $diskName, 'visibility' => 'private'],
        );

        $originalName = trim((string) $certificate->getClientOriginalName());

        return [
            'disk' => $diskName,
            'path' => $path,
            'name' => $originalName !== '' ? $originalName : $filename,
        ];
    }
}
