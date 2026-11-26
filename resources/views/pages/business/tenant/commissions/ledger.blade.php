{{-- Every ledger entry for the filters, newest first. --}}
@php
    $statusBadge = ['pending' => 'badge-light-warning', 'earned' => 'badge-light-info', 'approved' => 'badge-light-primary', 'paid' => 'badge-light-success', 'reversed' => 'badge-light'];
    $entitlementLabel = ['management_fee' => 'Company fee', 'agent' => 'Agent', 'primary_on_delegated' => 'Primary (delegated unit)'];
@endphp
<section class="tenant-panel tenant-panel--padded" data-commission-ledger>
    @if($lines->isEmpty())
        <div class="tenant-empty"><i class="fas fa-book" aria-hidden="true"></i><h3>No entries</h3><p>Nothing was recorded for these filters.</p></div>
    @else
    <div class="tenant-table-scroll" role="region" aria-label="Commission ledger" tabindex="0">
        <table class="tenant-table" style="min-width:1050px">
            <thead><tr><th>Date</th><th>Beneficiary</th><th>Property · unit</th><th>Tenant · invoice</th><th>Agreement</th><th class="text-end">Rent</th><th class="text-end">Rate</th><th class="text-end">Commission</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($lines as $l)
                <tr data-entry="{{ $l->id }}">
                    <td class="text-nowrap">{{ $l->kind === 'period' || $l->kind === 'adjustment' ? $l->period_from?->format('M Y') : $l->collected_on?->format('j M Y') }}<small>{{ ['payment' => 'Payment', 'period' => 'Period', 'reversal' => 'Reversal', 'adjustment' => 'Adjustment'][$l->kind] }} #{{ $l->id }}@if($l->reverses_entry_id) · of #{{ $l->reverses_entry_id }}@endif</small></td>
                    <td><strong>{{ $l->beneficiary_type === 'company' ? 'Company' : trim($l->agent_first.' '.$l->agent_last) }}</strong><small>{{ $entitlementLabel[$l->entitlement] ?? $l->entitlement }}@if($l->funding) · {{ $l->funding === 'company_fee' ? 'from fee' : 'additional cost' }}@endif</small></td>
                    <td>{{ $l->property ?: '—' }}<small>{{ $l->unit_number ?: '' }}{{ $l->management_type === 'company' ? ' · company-managed' : '' }}@if($l->beneficiary_type === 'agent' && $l->entitlement === 'agent'){{ $l->primary_agent_id && (int) $l->primary_agent_id !== (int) $l->agent_id ? ' · as sub-agent' : ($l->primary_agent_id ? ' · as primary manager' : '') }}@endif</small></td>
                    <td>{{ $l->tenant_type === 'Business' ? ($l->business_name ?: $l->tenant_name) : ($l->tenant_name ?: '—') }}<small>{{ $l->invoice_number }}{{ $l->rental_period ? ' · '.$l->rental_period : '' }}</small></td>
                    <td>{{ $l->agreement_name ?: ($l->breakdown['agreement'] ?? '—') }}<small>{{ $l->breakdown['agreement'] ?? '' }}@if(!empty($l->breakdown['note'])) · {{ $l->breakdown['note'] }}@endif</small></td>
                    <td class="text-end">{{ number_format($l->basis_amount, 2) }}</td>
                    <td class="text-end">{{ $l->rate !== null ? rtrim(rtrim(number_format($l->rate, 3), '0'), '.') . '%' : '—' }}</td>
                    <td class="text-end fw-bold {{ $l->amount < 0 ? 'text-danger' : '' }}">{{ number_format($l->amount, 2) }}</td>
                    <td><span class="badge {{ $statusBadge[$l->status] ?? 'badge-light' }}">{{ ucfirst($l->status) }}</span>@if($l->payout_id)<small>Payout #{{ $l->payout_id }}</small>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $lines->links() }}</div>
    @endif
</section>
