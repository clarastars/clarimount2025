<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\SalaryCertificateRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SalaryCertificateApprovalWorkflowMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public User $recipient,
        public string $eventType,
        public array $payload
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.notifications.salary_certificate_request_workflow_email_subject'),
        );
    }

    public function content(): Content
    {
        $company = isset($this->payload['company_id'])
            ? Company::query()->find($this->payload['company_id'])
            : null;

        $companyLogoPath = null;
        if (! empty($company?->logo)) {
            $logoPath = Storage::disk('public')->path($company->logo);
            if (File::isFile($logoPath)) {
                $companyLogoPath = $logoPath;
            }
        }

        return new Content(
            view: 'emails.salary-certificate-approval-workflow',
            with: [
                'recipient' => $this->recipient,
                'employee' => $this->recipient->employee,
                'company' => $company,
                'companyLogoPath' => $companyLogoPath,
                'messageText' => $this->buildMessageText(),
                'actionUrl' => $this->payload['url'] ?? null,
            ],
        );
    }

    private function buildMessageText(): string
    {
        $replacements = [
            '{employee}' => (string) ($this->payload['employee_name'] ?? ''),
            '{company}' => (string) ($this->payload['company_name'] ?? ''),
            '{purpose}' => (string) ($this->payload['purpose'] ?? ''),
            '{step}' => (string) ($this->payload['step_title'] ?? ''),
            '{name}' => (string) ($this->payload['actor_name'] ?? ''),
            '{reason}' => (string) ($this->payload['reason'] ?? ''),
            '{remaining}' => (string) ($this->payload['remaining_steps'] ?? ''),
        ];

        $translationKey = match ($this->eventType) {
            'your_turn' => ! empty($this->payload['after_rejection'])
                ? 'messages.notifications.salary_certificate_request_workflow_your_turn_after_rejection'
                : 'messages.notifications.salary_certificate_request_workflow_your_turn',
            'step_approved' => 'messages.notifications.salary_certificate_request_workflow_step_approved',
            'step_progress' => 'messages.notifications.salary_certificate_request_workflow_step_progress',
            'rejected' => 'messages.notifications.salary_certificate_request_workflow_rejected',
            'workflow_rejected' => 'messages.notifications.salary_certificate_request_workflow_employee_rejected',
            'finalized', 'completed' => 'messages.notifications.salary_certificate_request_workflow_finalized',
            default => 'messages.notifications.salary_certificate_request_workflow_your_turn',
        };

        return strtr(__($translationKey), $replacements);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! in_array($this->eventType, ['completed', 'finalized'], true)) {
            return [];
        }

        $requestId = (int) ($this->payload['salary_certificate_request_id'] ?? 0);
        if ($requestId < 1) {
            return [];
        }

        $certificateRequest = SalaryCertificateRequest::query()->find($requestId);
        if ($certificateRequest === null || ! filled($certificateRequest->certificate_path)) {
            return [];
        }

        $disk = Storage::disk($certificateRequest->certificateDisk());
        if (! $disk->exists($certificateRequest->certificate_path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(
                $certificateRequest->certificateDisk(),
                $certificateRequest->certificate_path,
            )
                ->as($certificateRequest->certificateDownloadName())
                ->withMime('application/pdf'),
        ];
    }
}
