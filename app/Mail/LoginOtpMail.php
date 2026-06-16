<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $otp,
        public string $userName,
        public int $expiresInMinutes,
        string $locale = 'ar',
    ) {
        $this->locale(in_array($locale, ['ar', 'en'], true) ? $locale : 'ar');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.auth.otp_email_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-otp',
        );
    }
}
