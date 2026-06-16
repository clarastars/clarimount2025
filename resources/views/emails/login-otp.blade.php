@php
    $isRtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" @if($isRtl) dir="rtl" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.auth.otp_email_subject') }}</title>
</head>
<body style="font-family: Tahoma, Arial, sans-serif; color: #111827; line-height: 1.8; background: #f9fafb; margin: 0; padding: 24px; @if($isRtl) direction: rtl; text-align: right; @endif">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; @if($isRtl) direction: rtl; text-align: right; @endif">
        <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; background: #f3f4f6;">
            <h2 style="margin: 0; font-size: 20px;">{{ __('messages.auth.otp_email_heading') }}</h2>
        </div>

        <div style="padding: 20px;">
            <p style="margin: 0 0 8px 0;">
                {{ __('messages.auth.otp_email_greeting', ['name' => $userName]) }}
            </p>
            <p style="margin: 0 0 16px 0;">
                {{ __('messages.auth.otp_email_body') }}
            </p>

            <div style="background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; text-align: center; margin: 16px 0;">
                <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #1d4ed8; direction: ltr; display: inline-block;">{{ $otp }}</span>
            </div>

            <p style="margin: 0 0 8px 0; font-size: 13px; color: #6b7280;">
                {{ __('messages.auth.otp_email_expires', ['minutes' => $expiresInMinutes]) }}
            </p>
            <p style="margin: 0; font-size: 13px; color: #6b7280;">
                {{ __('messages.auth.otp_email_ignore') }}
            </p>
        </div>
    </div>
</body>
</html>
