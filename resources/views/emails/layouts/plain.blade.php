{{-- A minimal, inline-styled email layout (email clients ignore page CSS and scripts). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f8fa;font-family:Arial,Helvetica,sans-serif;color:#181c32;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f8fa;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;">
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <a href="{{ config('app.url') }}" target="_blank" rel="noopener">
                                <img src="{{ asset(config('app.mail_logo')) }}" alt="{{ config('app.name') }}" height="40" style="height:40px;border:0;">
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;border-radius:8px;padding:28px;font-size:15px;line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-top:20px;font-size:12px;color:#7e8299;">
                            <a href="{{ config('app.url') }}" target="_blank" rel="noopener" style="color:#7e8299;">{{ config('app.name') }}</a>
                            @if(config('app.support_email'))
                                &middot; <a href="mailto:{{ config('app.support_email') }}" style="color:#7e8299;">{{ config('app.support_email') }}</a>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
