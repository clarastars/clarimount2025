<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailSubject }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.6;">
    <h2 style="margin-bottom: 12px;">HR System Test Email</h2>

    <p style="margin: 0 0 8px 0;">
        This is a test email sent from your HR system.
    </p>
    <p style="margin: 0 0 16px 0;">
        If you received this, your email setup is working correctly.
    </p>

    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 16px 0;">

    <p style="margin: 0 0 8px 0;"><strong>Message:</strong></p>
    <div style="background: #f9fafb; border: 1px solid #e5e7eb; padding: 12px; white-space: pre-line;">{{ $bodyText }}</div>

    <p style="margin: 16px 0 4px 0; font-size: 13px; color: #6b7280;">
        Sent by: {{ $senderName }}
    </p>
    <p style="margin: 0; font-size: 13px; color: #6b7280;">
        Sent at: {{ $sentAt }}
    </p>
</body>
</html>
