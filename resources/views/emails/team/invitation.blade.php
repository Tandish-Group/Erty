<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Team invitation</title></head>
<body style="margin:0;padding:0;background:#f5f8fa;font-family:Arial,Helvetica,sans-serif;color:#2f3044;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f8fa;padding:32px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:10px;padding:32px;">
            <tr><td style="font-size:20px;font-weight:bold;padding-bottom:16px;">{{ config('app.name') }}</td></tr>
            <tr><td style="font-size:15px;line-height:1.6;">
                <p style="margin:0 0 12px;">Hello {{ $staff->first_name }},</p>
                <p style="margin:0 0 12px;">{{ $invitedByName }} has invited you to join <strong>{{ $businessName }}</strong> on {{ config('app.name') }} as <strong>{{ $roleName }}</strong>.</p>
                <p style="margin:0 0 20px;">Set your password to accept the invitation and sign in with <strong>{{ $staff->email }}</strong>.</p>
            </td></tr>
            <tr><td align="left" style="padding-bottom:20px;">
                <a href="{{ $acceptUrl }}" style="display:inline-block;background:#009ef7;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:6px;">Set your password</a>
            </td></tr>
            <tr><td style="font-size:13px;line-height:1.6;color:#7e8299;">
                <p style="margin:0 0 8px;">This link expires in {{ $expiresInMinutes }} minutes. If it has expired, ask your administrator to resend the invitation.</p>
                <p style="margin:0;">If you weren't expecting this invitation you can ignore this email.</p>
                <p style="margin:12px 0 0;word-break:break-all;">{{ $acceptUrl }}</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
