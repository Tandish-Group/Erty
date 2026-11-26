<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tenancy assigned</title></head>
<body style="margin:0;padding:0;background:#f5f8fa;font-family:Arial,Helvetica,sans-serif;color:#2f3044;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f8fa;padding:32px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:10px;padding:32px;">
            <tr><td style="font-size:20px;font-weight:bold;padding-bottom:16px;">{{ $details['business'] }}</td></tr>
            <tr><td style="font-size:15px;line-height:1.6;">
                <p style="margin:0 0 12px;">Hello {{ $details['agent_name'] }},</p>
                @if($asLeadAgent)
                    <p style="margin:0 0 16px;"><strong>{{ $details['placed_by'] }}</strong>, who reports to you, onboarded a tenant in <strong>{{ $details['property'] }}</strong>, where you are the lead agent. Your team override applies to its rent collections.</p>
                @else
                    <p style="margin:0 0 16px;">A tenant has been onboarded and the tenancy is assigned to you. It counts towards your commission.</p>
                @endif
            </td></tr>
            <tr><td>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;border:1px solid #eff2f5;border-radius:8px;">
                    @foreach([
                        'Tenant' => $details['tenant_name'].' ('.$details['tenant_type'].')',
                        'Property' => $details['property'],
                        'Unit' => $details['unit'] ?: 'Whole property',
                        'Start date' => $details['start_date'] ?: 'Not set',
                        'Rent' => $details['rent'],
                        'Tenancy reference' => $details['reference'],
                    ] as $label => $value)
                        <tr><td style="padding:10px 14px;color:#7e8299;width:40%;border-bottom:1px solid #eff2f5;">{{ $label }}</td><td style="padding:10px 14px;border-bottom:1px solid #eff2f5;"><strong>{{ $value }}</strong></td></tr>
                    @endforeach
                </table>
            </td></tr>
            <tr><td align="left" style="padding:24px 0 8px;">
                <a href="{{ $details['dashboard_url'] }}" style="display:inline-block;background:#009ef7;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:6px;">Open my agent dashboard</a>
            </td></tr>
            <tr><td style="font-size:12px;color:#a1a5b7;">You get this email because you are an agent for {{ $details['business'] }} on {{ config('app.name') }}.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
