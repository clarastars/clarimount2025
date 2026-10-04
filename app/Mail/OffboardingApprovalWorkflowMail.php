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

class OffboardingApprovalWorkflowMail extends Mailable
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
            subject: __('messages.notifications.offboarding_workflow_email_subject'),
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
            view: 'emails.offboarding-approval-workflow',
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
            '{item}' => (string) ($this->payload['item_title'] ?? ''),
            '{step}' => (string) ($this->payload['step_title'] ?? ''),
            '{name}' => (string) ($this->payload['actor_name'] ?? ''),
            '{remaining}' => (string) ($this->payload['remaining_steps'] ?? ''),
            '{reject_reason}' => (string) ($this->payload['reason'] ?? ''),
        ];

        $key = match ($this->eventType) {
            'your_turn' => ! empty($this->payload['after_rejection'])
                ? 'messages.notifications.offboarding_your_turn_after_rejection'
                : 'messages.notifications.offboarding_your_turn',
            'step_approved' => 'messages.notifications.offboarding_step_approved',
            'item_finalized' => 'messages.notifications.offboarding_item_finalized',
            'clearance_started' => 'messages.notifications.offboarding_clearance_started',
            'finalized' => 'messages.notifications.offboarding_finalized',
            'rejected' => 'messages.notifications.offboarding_rejected',
            'case_started' => 'messages.notifications.offboarding_case_started',
            default => 'messages.notifications.offboarding_step_approved',
        };

        return strtr(__($key), $replacements);
    }
}
