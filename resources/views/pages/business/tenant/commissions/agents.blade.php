<section class="tenant-panel tenant-panel--padded">
    @if($rows->isEmpty())<div class="tenant-empty"><i class="fas fa-user-tie" aria-hidden="true"></i><h3>No agent commission in this period</h3></div>@else
    <div class="tenant-table-scroll" role="region" aria-label="Commission by agent" tabindex="0">
        <table class="tenant-table">
            <thead><tr><th>Agent</th><th class="text-end">Rent on their units</th><th class="text-end">Own commission</th><th class="text-end">As primary manager</th><th class="text-end">Waiting</th><th class="text-end">Owed</th><th></th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr><td><strong>{{ trim($r->first_name.' '.$r->last_name) }}</strong></td><td class="text-end">{{ number_format($r->agent_rent, 2) }}</td><td class="text-end">{{ number_format($r->own_commission, 2) }}</td>
                    <td class="text-end">{{ number_format($r->primary_commission, 2) }}</td><td class="text-end">{{ (float) $r->pending ? number_format($r->pending, 2) : '—' }}</td><td class="text-end fw-bold">{{ number_format($r->owed, 2) }}</td>
                    <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('tenant_management.agent_commissions.statement', ['agentId' => $r->agent_id] + request()->only('period', 'from', 'to')) }}">Statement & payout</a></td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</section>
