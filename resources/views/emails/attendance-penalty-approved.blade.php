<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.attendance.penalty_email_subject') }}</title>
</head>
<body style="direction: rtl; text-align: right; font-family: Tahoma, Arial, sans-serif; color: #111827; line-height: 1.8; background: #f9fafb; margin: 0; padding: 24px;">
    <div style="direction: rtl; text-align: right; max-width: 680px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; background: #f3f4f6;">
            @if(!empty($companyLogoDataUri))
                <div style="margin-bottom: 12px;">
                    <img src="{{ $companyLogoDataUri }}" alt="Company Logo" style="display: block; max-height: 56px; max-width: 180px; object-fit: contain;">
                </div>
            @endif
            <h2 style="margin: 0; font-size: 20px;">{{ __('messages.attendance.penalty_email_subject') }}</h2>
        </div>

        <div style="padding: 20px;">
            <p style="margin-top: 0;">
                {{ __('messages.attendance.penalty_email_greeting', ['name' => $employee?->full_name ?? '-']) }}
            </p>
            <p>
                {{ __('messages.attendance.penalty_email_intro') }}
            </p>

            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px;">
                <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.date') }}:</strong> {{ optional($penalty->attendance_date)->format('Y-m-d') ?? '-' }}</p>
                <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.attendance_late_minutes') }}:</strong> {{ $penalty->late_minutes ?? 0 }}</p>
                <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.penalty_action') }}:</strong> {{ $penalty->action_text ?? '-' }}</p>
                <p style="margin: 0;"><strong>{{ __('messages.attendance.penalty_reason') }}:</strong> {{ $penalty->reason_text ?? '-' }}</p>
            </div>

            <p style="margin-bottom: 0; margin-top: 16px;">
                {{ __('messages.attendance.penalty_email_footer') }}
            </p>
        </div>
    </div>
</body>
</html>
