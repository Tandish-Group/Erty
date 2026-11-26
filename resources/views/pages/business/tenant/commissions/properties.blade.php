<section class="tenant-panel tenant-panel--padded">
    @if($rows->isEmpty())<div class="tenant-empty"><i class="fas fa-building" aria-hidden="true"></i><h3>No commission in this period</h3></div>@else
    <div class="tenant-table-scroll" role="region" aria-label="Commission by property" tabindex="0">
        @if($ownAgent)
        <table class="tenant-table">
            <thead><tr><th>Property</th><th class="text-end">Rent on your units</th><th class="text-end">Your commission</th><th class="text-end">Waiting</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr><td><strong>{{ $r->title ?: 'No property' }}</strong></td><td class="text-end">{{ number_format($r->own_rent, 2) }}</td><td class="text-end fw-bold">{{ number_format($r->agent_commission, 2) }}</td><td class="text-end">{{ (float) $r->pending ? number_format($r->pending, 2) : '—' }}</td></tr>
            @endforeach
            </tbody>
            <tfoot><tr class="fw-bold"><td>Total</td><td class="text-end">{{ number_format($rows->sum('own_rent'), 2) }}</td><td class="text-end">{{ number_format($rows->sum('agent_commission'), 2) }}</td><td></td></tr></tfoot>
        </table>
        @else
        <table class="tenant-table">
            <thead><tr><th>Property</th><th class="text-end">Rent collected</th><th class="text-end">Company fee</th><th class="text-end">Agent commission</th><th class="text-end">Company net</th><th class="text-end">Waiting</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr><td><strong>{{ $r->title ?: 'No property' }}</strong><small>{{ $r->management_type === 'company' ? 'Company-managed' : ($r->management_type ? 'Primary manager' : '') }}</small></td>
                    <td class="text-end">{{ number_format($r->rent, 2) }}</td><td class="text-end">{{ number_format($r->company_fee, 2) }}</td><td class="text-end">{{ number_format($r->agent_commission, 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($r->company_fee - $r->agent_from_fee, 2) }}</td><td class="text-end">{{ (float) $r->pending ? number_format($r->pending, 2) : '—' }}</td></tr>
            @endforeach
            </tbody>
            <tfoot><tr class="fw-bold"><td>Total</td><td class="text-end">{{ number_format($rows->sum('rent'), 2) }}</td><td class="text-end">{{ number_format($rows->sum('company_fee'), 2) }}</td><td class="text-end">{{ number_format($rows->sum('agent_commission'), 2) }}</td><td class="text-end">{{ number_format($rows->sum('company_fee') - $rows->sum('agent_from_fee'), 2) }}</td><td></td></tr></tfoot>
        </table>
        @endif
    </div>
    @endif
</section>
