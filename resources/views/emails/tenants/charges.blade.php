@php
    $profile = $tenancy->tenantProfile;
    $name = $profile?->tenant_type === 'Business' ? ($profile->business_name ?: $profile->tenant_name) : ($profile?->tenant_name ?: 'there');
    $cur = $summary['currency'];
    $money = fn ($v) => money($v, $cur);
@endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Invoice</title></head>
<body style="margin:0;padding:0;background:#f5f8fa;font-family:Arial,Helvetica,sans-serif;color:#2f3044;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f8fa;padding:32px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;padding:32px;">
            <tr><td style="font-size:20px;font-weight:bold;padding-bottom:16px;">{{ $business }}</td></tr>
            <tr><td style="font-size:15px;line-height:1.6;">
                <p style="margin:0 0 12px;">Hello {{ $name }},</p>
                <p style="margin:0 0 16px;">{{ $invoices->count() === 1 ? 'A new invoice has' : 'New invoices have' }} been issued for {{ $tenancy->complex_name }}{{ $tenancy->unit_number ? ', unit '.$tenancy->unit_number : '' }} (tenancy {{ $tenancy->tenancy_ref }}).</p>
            </td></tr>
            <tr><td>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;border:1px solid #eff2f5;border-radius:8px;">
                    <tr style="background:#f9fafb;"><td style="padding:10px 14px;color:#7e8299;">Invoice</td><td style="padding:10px 14px;color:#7e8299;">For</td><td style="padding:10px 14px;color:#7e8299;">Due</td><td style="padding:10px 14px;color:#7e8299;text-align:right;">Amount</td></tr>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td style="padding:10px 14px;border-top:1px solid #eff2f5;"><strong>{{ $invoice->invoice_number }}</strong></td>
                            <td style="padding:10px 14px;border-top:1px solid #eff2f5;">{{ $invoice->line_items[0]['description'] ?? $invoice->kind_label }}</td>
                            <td style="padding:10px 14px;border-top:1px solid #eff2f5;">{{ $invoice->due_date?->format('j M Y') }}</td>
                            <td style="padding:10px 14px;border-top:1px solid #eff2f5;text-align:right;">{{ $money($invoice->amount) }}@if((float) $invoice->paid_amount > 0)<br><span style="color:#50cd89;font-size:12px;">{{ $money($invoice->paid_amount) }} covered by credit</span>@endif</td>
                        </tr>
                    @endforeach
                </table>
            </td></tr>
            <tr><td style="padding-top:16px;font-size:15px;line-height:1.6;">
                @if($summary['balance'] > 0)
                    <p style="margin:0;">Total now due on your account: <strong>{{ $money($summary['balance']) }}</strong>@if($summary['overdue'] > 0), of which {{ $money($summary['overdue']) }} is overdue @endif.</p>
                @elseif($summary['balance'] < 0)
                    <p style="margin:0;">Your account is in credit by <strong>{{ $money(abs($summary['balance'])) }}</strong>; it will be used on your next invoice.</p>
                @else
                    <p style="margin:0;">Your account is fully paid. Thank you.</p>
                @endif
            </td></tr>
            <tr><td style="padding-top:20px;font-size:12px;color:#a1a5b7;">Please quote your tenancy reference {{ $tenancy->tenancy_ref }} with your payment. Sent by {{ $business }} via {{ config('app.name') }}.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
