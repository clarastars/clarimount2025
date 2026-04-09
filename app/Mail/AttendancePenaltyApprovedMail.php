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

        $companyLogoDataUri = null;
        if (!empty($company?->logo)) {
            $logoPath = Storage::disk('public')->path($company->logo);
            if (File::exists($logoPath)) {
                $mimeType = File::mimeType($logoPath) ?: 'image/png';
                $encoded = base64_encode((string) File::get($logoPath));
                $companyLogoDataUri = "data:{$mimeType};base64,{$encoded}";
            }
        }

        return new Content(
            view: 'emails.attendance-penalty-approved',
            with: [
                'penalty' => $this->penalty,
                'employee' => $employee,
                'company' => $company,
                'companyLogoDataUri' => $companyLogoDataUri,
            ],
        );
    }
}
