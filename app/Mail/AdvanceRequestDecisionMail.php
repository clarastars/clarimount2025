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

class AdvanceRequestDecisionMail extends Mailable
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
        return new Envelope(subject: __($this->subjectKey()));
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
            view: 'emails.advance-request-decision',
            with: [
                'employee' => $this->employee,
                'company' => $company,
                'companyLogoPath' => $companyLogoPath,
                'emailTitle' => __($this->subjectKey()),
                'messageText' => $this->buildMessageText(),
                'actionUrl' => $this->payload['url'] ?? null,
            ],
        );
    }

    private function subjectKey(): string
    {
        return $this->eventType === 'approved'
            ? 'messages.notifications.advance_request_approved_email_subject'
            : 'messages.notifications.advance_request_rejected_email_subject';
    }

    private function buildMessageText(): string
    {
        $messageKey = $this->eventType === 'approved'
            ? 'messages.notifications.advance_request_approved'
            : 'messages.notifications.advance_request_rejected';

        $replacements = [
            '{company}' => (string) ($this->payload['company_name'] ?? ''),
            '{amount}' => (string) ($this->payload['amount'] ?? ''),
            '{monthly}' => (string) ($this->payload['monthly_deduction'] ?? ''),
            '{months}' => (string) ($this->payload['months_count'] ?? ''),
        ];

        $message = strtr(__($messageKey), $replacements);

        $reviewNotes = trim((string) ($this->payload['review_notes'] ?? ''));
        if ($reviewNotes !== '') {
            $message .= ' '.strtr(__('messages.notifications.advance_request_decision_notes'), [
                '{notes}' => $reviewNotes,
            ]);
        }

        return $message;
    }
}
