<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.notifications.leave_request_workflow_email_subject') }}</title>
</head>
<body style="direction: rtl; text-align: right; font-family: Tahoma, Arial, sans-serif; color: #111827; line-height: 1.8; background: #f9fafb; margin: 0; padding: 24px;">
    <div style="direction: rtl; text-align: right; max-width: 680px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; background: #f3f4f6;">
            @if(!empty($companyLogoPath))
                <div style="margin-bottom: 12px;">
                    <img src="{{ $message->embed($companyLogoPath) }}" alt="{{ __('messages.companies.logo') }}" style="display: block; max-height: 56px; max-width: 180px; object-fit: contain;">
                </div>
            @endif
            <h2 style="margin: 0; font-size: 20px;">{{ __('messages.notifications.leave_request_workflow_email_subject') }}</h2>
        </div>

        <div style="padding: 20px;">
            <p style="margin-top: 0;">
                {{ __('messages.notifications.leave_request_workflow_email_greeting', ['name' => $employee?->full_name ?? $recipient->name]) }}
            </p>
            <p>
                {{ $messageText }}
            </p>

            @if(!empty($actionUrl))
                <p style="margin-top: 20px; margin-bottom: 0;">
                    <a href="{{ $actionUrl }}" style="display: inline-block; background: #111827; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600;">
                        {{ __('messages.notifications.leave_request_workflow_email_cta') }}
                    </a>
                </p>
            @endif

            <p style="margin-bottom: 0; margin-top: 16px;">
                {{ __('messages.notifications.leave_request_workflow_email_footer') }}
            </p>
        </div>
    </div>
</body>
</html>
