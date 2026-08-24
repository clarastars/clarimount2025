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

class EntitlementSettlementApprovalWorkflowMail extends Mailable
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
            subject: __('messages.notifications.entitlement_settlement_workflow_email_subject'),
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
            view: 'emails.entitlement-settlement-approval-workflow',
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
            '{date}' => (string) ($this->payload['settlement_date'] ?? ''),
            '{reason}' => (string) ($this->payload['settlement_reason'] ?? ''),
            '{net}' => (string) ($this->payload['net_due'] ?? ''),
            '{step}' => (string) ($this->payload['step_title'] ?? ''),
            '{name}' => (string) ($this->payload['actor_name'] ?? ''),
            '{remaining}' => (string) ($this->payload['remaining_steps'] ?? ''),
            '{reject_reason}' => (string) ($this->payload['reason'] ?? ''),
        ];

        $key = match ($this->eventType) {
            'your_turn' => ! empty($this->payload['after_rejection'])
                ? 'messages.notifications.entitlement_settlement_workflow_your_turn_after_rejection'
                : 'messages.notifications.entitlement_settlement_workflow_your_turn',
            'step_approved' => 'messages.notifications.entitlement_settlement_workflow_step_approved',
            'step_progress' => 'messages.notifications.entitlement_settlement_workflow_step_progress',
            'rejected' => 'messages.notifications.entitlement_settlement_workflow_rejected',
            'workflow_rejected' => 'messages.notifications.entitlement_settlement_workflow_employee_rejected',
            'finalized', 'approved' => 'messages.notifications.entitlement_settlement_workflow_finalized',
            default => 'messages.notifications.entitlement_settlement_workflow_your_turn',
        };

        return strtr(__($key), $replacements);
    }
}
