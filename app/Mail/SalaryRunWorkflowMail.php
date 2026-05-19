<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SalaryRunWorkflowMail extends Mailable
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
            subject: __('messages.notifications.salary_run_email_subject'),
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
            view: 'emails.salary-run-workflow',
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
        $month = isset($this->payload['month']) ? (int) $this->payload['month'] : null;
        $year = isset($this->payload['year']) ? (string) $this->payload['year'] : '';

        $monthLabel = $month
            ? Carbon::create((int) $year, $month, 1)->locale(app()->getLocale())->translatedFormat('F')
            : '';

        $period = trim($monthLabel.' '.$year);

        $replacements = [
            '{company}' => (string) ($this->payload['company_name'] ?? ''),
            '{period}' => $period,
            '{month}' => $monthLabel,
            '{year}' => $year,
            '{step}' => (string) ($this->payload['step_title'] ?? ''),
            '{name}' => (string) ($this->payload['actor_name'] ?? ''),
            '{reason}' => (string) ($this->payload['reason'] ?? ''),
        ];

        $translationKey = $this->eventType === 'your_turn' && ! empty($this->payload['after_rejection'])
            ? 'messages.notifications.salary_run_your_turn_after_rejection'
            : 'messages.notifications.salary_run_'.$this->eventType;

        return strtr(__($translationKey), $replacements);
    }
}
