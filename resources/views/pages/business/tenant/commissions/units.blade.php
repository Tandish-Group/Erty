<section class="tenant-panel tenant-panel--padded">
    @if($rows->isEmpty())<div class="tenant-empty"><i class="fas fa-door-open" aria-hidden="true"></i><h3>No unit commission in this period</h3></div>@else
    <div class="tenant-table-scroll" role="region" aria-label="Commission by unit" tabindex="0">
        @if($ownAgent)
        <table class="tenant-table">
            <thead><tr><th>Unit</th><th class="text-end">Rent on your units</th><th class="text-end">Your commission</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr><td><strong>{{ $r->unit_number ?: '#'.$r->unit_id }}</strong><small>{{ $r->title }} · {{ $r->unit_type }}</small></td><td class="text-end">{{ number_format($r->own_rent, 2) }}</td><td class="text-end fw-bold">{{ number_format($r->agent_commission, 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @else
        <table class="tenant-table">
            <thead><tr><th>Unit</th><th class="text-end">Rent collected</th><th class="text-end">Company fee</th><th class="text-end">Agent commission</th><th class="text-end">Company net</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr><td><strong>{{ $r->unit_number ?: '#'.$r->unit_id }}</strong><small>{{ $r->title }} · {{ $r->unit_type }}</small></td>
                    <td class="text-end">{{ number_format($r->rent, 2) }}</td><td class="text-end">{{ number_format($r->company_fee, 2) }}</td><td class="text-end">{{ number_format($r->agent_commission, 2) }}</td><td class="text-end fw-bold">{{ number_format($r->company_fee - $r->agent_from_fee, 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endif
</section>
