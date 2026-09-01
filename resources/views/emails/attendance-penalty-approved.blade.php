<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.attendance.penalty_email_subject') }}</title>
</head>
<body style="direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}; text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }}; font-family: Tahoma, Arial, sans-serif; color: #111827; line-height: 1.8; background: #f9fafb; margin: 0; padding: 24px;">
    <div style="direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}; text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }}; max-width: 680px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; background: #f3f4f6;">
            @if(!empty($companyLogoPath))
                <div style="margin-bottom: 12px;">
                    <img src="{{ $message->embed($companyLogoPath) }}" alt="{{ __('messages.companies.logo') }}" style="display: block; max-height: 56px; max-width: 180px; object-fit: contain;">
                </div>
            @endif
            <h2 style="margin: 0; font-size: 20px;">{{ __('messages.attendance.penalty_email_subject') }}</h2>
        </div>

        <div style="padding: 20px;">
            <p style="margin-top: 0;">
                {{ __('messages.attendance.penalty_email_greeting', ['name' => $employee?->full_name ?? '-']) }}
            </p>
            <p>
                @if(($emailContext['scenario'] ?? 'generic') === 'late')
                    {{ __('messages.attendance.penalty_email_intro_late') }}
                @elseif(($emailContext['scenario'] ?? 'generic') === 'early_departure')
                    {{ __('messages.attendance.penalty_email_intro_early_departure') }}
                @else
                    {{ __('messages.attendance.penalty_email_intro_generic') }}
                @endif
            </p>

            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px;">
                <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.date') }}:</strong> {{ $emailContext['date'] ?? '-' }}</p>

                @if(($emailContext['scenario'] ?? 'generic') === 'late')
                    @if(!empty($emailContext['scheduled_check_in']))
                        <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.penalty_email_scheduled_check_in') }}:</strong> {{ $emailContext['scheduled_check_in'] }}</p>
                    @endif
                    @if(!empty($emailContext['actual_check_in']))
                        <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.penalty_email_actual_check_in') }}:</strong> {{ $emailContext['actual_check_in'] }}</p>
                    @endif
                    <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.attendance_late_minutes') }}:</strong> {{ $emailContext['late_minutes'] ?? 0 }}</p>
                @elseif(($emailContext['scenario'] ?? 'generic') === 'early_departure')
                    @if(!empty($emailContext['scheduled_check_in']))
                        <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.penalty_email_scheduled_check_in') }}:</strong> {{ $emailContext['scheduled_check_in'] }}</p>
                    @endif
                    @if(!empty($emailContext['actual_check_in']))
                        <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.penalty_email_actual_check_in') }}:</strong> {{ $emailContext['actual_check_in'] }}</p>
                    @endif
                    @if(!empty($emailContext['scheduled_check_out']))
                        <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.penalty_email_scheduled_check_out') }}:</strong> {{ $emailContext['scheduled_check_out'] }}</p>
                    @endif
                    @if(!empty($emailContext['actual_check_out']))
                        <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.penalty_email_actual_check_out') }}:</strong> {{ $emailContext['actual_check_out'] }}</p>
                    @endif
                    @if(($emailContext['early_minutes'] ?? null) !== null)
                        <p style="margin: 0 0 8px 0;"><strong>{{ __('messages.attendance.penalty_email_early_minutes') }}:</strong> {{ $emailContext['early_minutes'] }}</p>
                    @endif
                @endif

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
