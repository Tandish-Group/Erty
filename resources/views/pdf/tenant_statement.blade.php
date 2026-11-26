@php
    $profile = $tenancy->tenantProfile;
    $name = $profile?->tenant_type === 'Business' ? ($profile->business_name ?: $profile->tenant_name) : $profile?->tenant_name;
    $cur = $summary['currency'];
    $money = fn ($v) => money($v, $cur);
    $d = fn ($v) => \Carbon\Carbon::parse($v)->format('j M Y');
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Statement {{ $tenancy->tenancy_ref }}</title>
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #2f3044; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    .muted { color: #7e8299; }
    table { width: 100%; border-collapse: collapse; margin-top: 14px; }
    th { text-align: left; font-size: 10px; color: #7e8299; border-bottom: 1px solid #e4e6ef; padding: 6px 4px; }
    td { border-bottom: 1px solid #f1f1f4; padding: 6px 4px; vertical-align: top; }
    .r { text-align: right; }
    .box { border: 1px solid #e4e6ef; border-radius: 6px; padding: 8px 10px; }
</style>
</head>
<body>
    <table style="margin-top:0;"><tr>
        <td style="border:none;padding:0;"><h1>{{ $business }}</h1><div class="muted">Tenant statement</div></td>
        <td style="border:none;padding:0;" class="r"><strong>{{ $name }}</strong><br>{{ $tenancy->complex_name }}{{ $tenancy->unit_number ? ', unit '.$tenancy->unit_number : '' }}<br>Tenancy {{ $tenancy->tenancy_ref }}<br><span class="muted">Issued {{ now()->format('j M Y') }}{{ $from || $to ? ' · '.($from ? $d($from) : 'start').' – '.($to ? $d($to) : 'today') : '' }}</span></td>
    </tr></table>

    <table><tr>
        <td class="box" style="width:25%;"><div class="muted">{{ $summary['balance'] < 0 ? 'In credit' : 'Balance due' }}</div><strong>{{ $money(abs($summary['balance'])) }}</strong></td>
        <td class="box" style="width:25%;"><div class="muted">Overdue</div><strong>{{ $money($summary['overdue']) }}</strong></td>
        <td class="box" style="width:25%;"><div class="muted">Credit</div><strong>{{ $money($summary['credit']) }}</strong></td>
        <td class="box" style="width:25%;"><div class="muted">Deposit held</div><strong>{{ $money($summary['deposit_held']) }}</strong></td>
    </tr></table>

    <table>
        <thead><tr><th>Date</th><th>Details</th><th>Reference</th><th class="r">Charged</th><th class="r">Paid / credited</th><th class="r">Balance</th></tr></thead>
        <tbody>
        @if($from)
            <tr><td>{{ $d($from) }}</td><td colspan="4">Opening balance</td><td class="r">{{ $money($statement['opening_balance']) }}</td></tr>
        @endif
        @forelse($statement['lines'] as $line)
            <tr>
                <td>{{ $d($line['date']) }}</td><td>{{ $line['description'] }}</td><td>{{ $line['reference'] }}</td>
                <td class="r">{{ $line['debit'] > 0 ? $money($line['debit']) : '' }}</td>
                <td class="r">{{ $line['credit'] > 0 ? $money($line['credit']) : '' }}</td>
                <td class="r">{{ $line['balance'] < 0 ? '−' : '' }}{{ $money(abs($line['balance'])) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No entries.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="muted" style="margin-top:12px;">Balance is what is owed; a minus means the account is in credit. The deposit is held and refundable under the tenancy agreement. Please quote {{ $tenancy->tenancy_ref }} with every payment.</p>
</body>
</html>
