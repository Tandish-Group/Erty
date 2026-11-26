@php
    $cur = $sale->currency ?: default_currency();
    $money = fn ($v) => money($v, $cur);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Receipt {{ $payment->receipt_number }}</title>
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #2f3044; }
    h1 { font-size: 17px; margin: 0 0 2px; }
    .muted { color: #7e8299; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th { text-align: left; font-size: 10px; color: #7e8299; border-bottom: 1px solid #e4e6ef; padding: 5px 4px; }
    td { border-bottom: 1px solid #f1f1f4; padding: 5px 4px; vertical-align: top; }
    .r { text-align: right; }
    .amount { font-size: 20px; font-weight: bold; }
</style>
</head>
<body>
    <table style="margin-top:0;"><tr>
        <td style="border:none;padding:0;"><h1>{{ $business }}</h1><div class="muted">Payment receipt</div></td>
        <td style="border:none;padding:0;" class="r"><strong>{{ $payment->receipt_number }}</strong><br><span class="muted">{{ $payment->paid_on->format('j M Y') }}</span></td>
    </tr></table>
    <p style="margin-top:16px;">Received from <strong>{{ $sale->buyer_name }}</strong>{{ $sale->buyer_id_number ? ' (ID ' . $sale->buyer_id_number . ')' : '' }}</p>
    <div class="amount">{{ $money($payment->amount) }}</div>
    <p class="muted">{{ $payment->methodLabel() }}{{ $payment->reference ? ' · ref. ' . $payment->reference : '' }}</p>
    <p>For the purchase of house {{ $sale->unit?->unit_number }} at {{ $sale->complex->title }}{{ $sale->unit?->plot_number ? ', plot ' . $sale->unit->plot_number : '' }}. Sale {{ $sale->reference }}, price {{ $money($sale->price) }}.</p>
    <table>
        <thead><tr><th>Applied to</th><th class="r">Amount</th></tr></thead>
        <tbody>@foreach($payment->allocations as $a)<tr><td>{{ $a->instalment?->label }} ({{ $a->instalment?->number }})</td><td class="r">{{ $money($a->amount) }}</td></tr>@endforeach</tbody>
    </table>
    @php $paidToDate = $sale->payments->where('status', 'confirmed')->filter(fn ($p) => $p->id <= $payment->id)->sum('amount'); @endphp
    <table>
        <tr><td>Paid to date</td><td class="r">{{ $money($paidToDate) }}</td></tr>
        <tr><td><strong>Balance after this payment</strong></td><td class="r"><strong>{{ $money(max(0, $sale->price - $paidToDate)) }}</strong></td></tr>
    </table>
    @if($payment->status === 'reversed')<p style="color:#b3304a;"><strong>This payment was reversed</strong> on {{ $payment->reversed_at?->format('j M Y') }}: {{ $payment->reversal_reason }}</p>@endif
</body>
</html>
