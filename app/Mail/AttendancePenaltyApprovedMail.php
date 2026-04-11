<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AttendancePenalty;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AttendancePenaltyApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public AttendancePenalty $penalty)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.attendance.penalty_email_subject'),
        );
    }

    public function content(): Content
    {
        $employee = $this->penalty->employee;
        $company = $employee?->company;

        // Absolute path for Laravel $message->embed() — Gmail often blocks data: URIs.
        $companyLogoPath = null;
        if (!empty($company?->logo)) {
            $logoPath = Storage::disk('public')->path($company->logo);
            if (File::isFile($logoPath)) {
                $companyLogoPath = $logoPath;
            }
        }

        return new Content(
            view: 'emails.attendance-penalty-approved',
            with: [
                'penalty' => $this->penalty,
                'employee' => $employee,
                'company' => $company,
                'companyLogoPath' => $companyLogoPath,
            ],
        );
    }
}
