<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AdvanceRequestSubmittedMail extends Mailable
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
            subject: __('messages.notifications.advance_request_email_subject'),
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
            view: 'emails.advance-request-submitted',
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
            '{amount}' => (string) ($this->payload['amount'] ?? ''),
            '{monthly}' => (string) ($this->payload['monthly_deduction'] ?? ''),
            '{months}' => (string) ($this->payload['months_count'] ?? ''),
        ];

        return strtr(__('messages.notifications.advance_request_submitted'), $replacements);
    }
}
