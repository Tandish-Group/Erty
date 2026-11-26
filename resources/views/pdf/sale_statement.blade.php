@php
    $cur = $sale->currency ?: default_currency();
    $money = fn ($v) => money($v, $cur);
    $collected = $sale->collected();
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Sale statement {{ $sale->reference }}</title>
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #2f3044; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    h2 { font-size: 13px; margin: 18px 0 0; }
    .muted { color: #7e8299; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { text-align: left; font-size: 10px; color: #7e8299; border-bottom: 1px solid #e4e6ef; padding: 6px 4px; }
    td { border-bottom: 1px solid #f1f1f4; padding: 6px 4px; vertical-align: top; }
    .r { text-align: right; }
    .box { border: 1px solid #e4e6ef; border-radius: 6px; padding: 8px 10px; }
</style>
</head>
<body>
    <table style="margin-top:0;"><tr>
        <td style="border:none;padding:0;"><h1>{{ $business }}</h1><div class="muted">Buyer statement</div></td>
        <td style="border:none;padding:0;" class="r"><strong>{{ $sale->buyer_name }}</strong><br>House {{ $sale->unit?->unit_number }}, {{ $sale->complex->title }}<br>Sale {{ $sale->reference }} · agreed {{ $sale->agreement_date->format('j M Y') }}<br><span class="muted">Issued {{ now()->format('j M Y') }}</span></td>
    </tr></table>
    <table><tr>
        <td class="box" style="width:33%;"><div class="muted">Price</div><strong>{{ $money($sale->price) }}</strong></td>
        <td class="box" style="width:33%;"><div class="muted">Paid</div><strong>{{ $money($collected) }}</strong></td>
        <td class="box" style="width:33%;"><div class="muted">Balance</div><strong>{{ $money($sale->balance()) }}</strong></td>
    </tr></table>
    <h2>Payment plan</h2>
    <table>
        <thead><tr><th>Instalment</th><th>Due</th><th class="r">Amount</th><th class="r">Paid</th><th>Status</th></tr></thead>
        <tbody>@foreach($sale->instalments as $i)<tr><td>{{ $i->label }}</td><td>{{ $i->due_date->format('j M Y') }}</td><td class="r">{{ $money($i->amount) }}</td><td class="r">{{ $money($i->paid_amount) }}</td><td>{{ $i->isOverdue() && $sale->isLive() ? 'Overdue' : \App\Models\PropertySaleInstalment::STATUSES[$i->status] }}</td></tr>@endforeach</tbody>
    </table>
    <h2>Payments</h2>
    <table>
        <thead><tr><th>Receipt</th><th>Date</th><th>How</th><th class="r">Amount</th></tr></thead>
        <tbody>@forelse($sale->payments as $p)<tr><td>{{ $p->receipt_number }}{{ $p->status === 'reversed' ? ' (reversed)' : '' }}</td><td>{{ $p->paid_on->format('j M Y') }}</td><td>{{ $p->methodLabel() }}{{ $p->reference ? ' · ' . $p->reference : '' }}</td><td class="r">{{ $p->status === 'reversed' ? '—' : $money($p->amount) }}</td></tr>@empty<tr><td colspan="4" class="muted">No payments yet.</td></tr>@endforelse</tbody>
    </table>
</body>
</html>
