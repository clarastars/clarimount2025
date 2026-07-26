<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SalaryCertificateRequestDecisionMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Employee $employee,
        public string $eventType,
        public array $payload
    ) {}

    public function envelope(): Envelope
    {
        $subjectKey = $this->eventType === 'completed'
            ? 'messages.notifications.salary_certificate_request_completed_email_subject'
            : 'messages.notifications.salary_certificate_request_rejected_email_subject';

        return new Envelope(
            subject: __($subjectKey),
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

        $subjectKey = $this->eventType === 'completed'
            ? 'messages.notifications.salary_certificate_request_completed_email_subject'
            : 'messages.notifications.salary_certificate_request_rejected_email_subject';

        return new Content(
            view: 'emails.salary-certificate-request-decision',
            with: [
                'employee' => $this->employee,
                'company' => $company,
                'companyLogoPath' => $companyLogoPath,
                'emailTitle' => __($subjectKey),
                'messageText' => $this->buildMessageText(),
                'actionUrl' => $this->payload['url'] ?? null,
            ],
        );
    }

    private function buildMessageText(): string
    {
        $messageKey = $this->eventType === 'completed'
            ? 'messages.notifications.salary_certificate_request_completed'
            : 'messages.notifications.salary_certificate_request_rejected';

        $replacements = [
            '{company}' => (string) ($this->payload['company_name'] ?? ''),
            '{purpose}' => (string) ($this->payload['purpose'] ?? ''),
        ];

        $message = strtr(__($messageKey), $replacements);

        $reviewNotes = trim((string) ($this->payload['review_notes'] ?? ''));
        if ($reviewNotes !== '') {
            $message .= ' '.strtr(__('messages.notifications.salary_certificate_request_decision_notes'), [
                '{notes}' => $reviewNotes,
            ]);
        }

        return $message;
    }
}
