{{-- Summary: the company's fee, what agents earn (from the fee or as an additional cost), the company's net, and what is owed. --}}
@php $s = $summary; $statementUrl = fn ($id) => route('tenant_management.agent_commissions.statement', ['agentId' => $id] + request()->only('period', 'from', 'to')); @endphp
@if($ownAgent)
<div class="tenant-metrics mb-6" data-commission-summary data-commission-own>
    <div><span>Your commission</span><strong>{{ $money($s['agent_total']) }}</strong><small>Earned on rent collected in this period</small></div>
    <div><span>Owed to you</span><strong class="{{ $s['owed'] > 0 ? 'text-warning' : '' }}">{{ $money($s['owed']) }}</strong><small>Earned or approved, not paid yet</small></div>
    <div><span>Paid to you</span><strong>{{ $money($s['paid']) }}</strong><small>Settled in payouts</small></div>
    <div><span>Waiting for approval</span><strong>{{ $money($s['pending']) }}</strong><small>{{ $s['pending_count'] }} {{ \Illuminate\Support\Str::plural('entry', $s['pending_count']) }}@if($s['reversals']) · {{ $money($s['reversals']) }} reversed @endif</small></div>
</div>
<section class="tenant-panel tenant-panel--padded">
    <h2 class="fs-5 fw-bolder mb-1">How it is worked out</h2>
    <p class="text-muted fs-7 mb-4">Commission is recorded from rent collected on the units you were responsible for on the day the money came in, at the rate in force then. The ledger tab lists every payment it came from.</p>
    <div class="d-flex flex-wrap gap-3">
        <a class="btn btn-sm btn-light-primary" href="{{ $statementUrl($ownAgent->id) }}">Your statement and payouts</a>
        <a class="btn btn-sm btn-light" href="{{ $tabUrl('ledger') }}">Ledger</a>
        <a class="btn btn-sm btn-light" href="{{ route('business.my_agent') }}">My agent page</a>
    </div>
</section>
@else
<div class="tenant-metrics mb-6" data-commission-summary>
    <div><span>Company fee (gross)</span><strong>{{ $money($s['gross_fee']) }}</strong><small>Management commission on rent collected</small></div>
    <div><span>Agent commission from the fee</span><strong>{{ $money($s['agent_from_fee']) }}</strong><small>Paid out of the company's fee</small></div>
    <div><span>Company net</span><strong>{{ $money($s['company_net']) }}</strong><small>Fee less agent commission funded by it</small></div>
    <div><span>Additional cost</span><strong>{{ $money($s['additional_cost']) }}</strong><small>Agent commission on top of the fee</small></div>
</div>
<div class="tenant-metrics mb-6">
    <div><span>Owed to agents</span><strong class="{{ $s['owed'] > 0 ? 'text-warning' : '' }}">{{ $money($s['owed']) }}</strong><small>Earned or approved, not paid yet</small></div>
    <div><span>Paid to agents</span><strong>{{ $money($s['paid']) }}</strong><small>Settled in payouts</small></div>
    <div><span>Waiting for approval</span><strong>{{ $money($s['pending']) }}</strong><small>{{ $s['pending_count'] }} {{ \Illuminate\Support\Str::plural('entry', $s['pending_count']) }} · <a href="{{ $tabUrl('periods') }}">review</a></small></div>
    <div><span>Reversals</span><strong>{{ $money($s['reversals']) }}</strong><small>{{ $s['reversal_count'] }} reversed or clawed back</small></div>
</div>

<div class="row g-6">
    <div class="col-xl-7">
        <section class="tenant-panel tenant-panel--padded h-100">
            <h2 class="fs-5 fw-bolder mb-1">Agents</h2>
            <p class="text-muted fs-7 mb-4">Own commission is on units they answer for; primary-manager commission is on units they delegated.</p>
            @if($byAgent->isEmpty())
                <div class="tenant-empty"><i class="fas fa-user-tie" aria-hidden="true"></i><h3>No agent commission in this period</h3></div>
            @else
            <div class="tenant-table-scroll" role="region" aria-label="Agent commission" tabindex="0">
                <table class="tenant-table" style="min-width:560px">
                    <thead><tr><th>Agent</th><th class="text-end">Rent on their units</th><th class="text-end">Own</th><th class="text-end">As primary</th><th class="text-end">Owed</th><th></th></tr></thead>
                    <tbody>
                    @foreach($byAgent as $r)
                        <tr><td><strong>{{ trim($r->first_name.' '.$r->last_name) }}</strong></td><td class="text-end">{{ number_format($r->agent_rent, 2) }}</td><td class="text-end">{{ number_format($r->own_commission, 2) }}</td><td class="text-end">{{ number_format($r->primary_commission, 2) }}</td><td class="text-end fw-bold">{{ number_format($r->owed, 2) }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-light" href="{{ $statementUrl($r->agent_id) }}">Statement</a></td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>
    </div>
    <div class="col-xl-5">
        <section class="tenant-panel tenant-panel--padded mb-6">
            <h2 class="fs-5 fw-bolder mb-3">Where commission goes</h2>
            <dl class="row mb-0 fs-7">
                <dt class="col-8 text-muted fw-semibold mb-2">Agents on units they manage themselves</dt><dd class="col-4 text-end fw-bold mb-2">{{ number_format($s['own_units'], 2) }}</dd>
                <dt class="col-8 text-muted fw-semibold mb-2">Sub-agents (on delegated units)</dt><dd class="col-4 text-end fw-bold mb-2">{{ number_format($s['sub_agents'], 2) }}</dd>
                <dt class="col-8 text-muted fw-semibold mb-2">Primary managers (on delegated units)</dt><dd class="col-4 text-end fw-bold mb-2">{{ number_format($s['primary'], 2) }}</dd>
                <dt class="col-8 text-muted fw-semibold mb-2">Company fee · residential</dt><dd class="col-4 text-end fw-bold mb-2">{{ number_format($s['residential'], 2) }}</dd>
                <dt class="col-8 text-muted fw-semibold mb-0">Company fee · commercial</dt><dd class="col-4 text-end fw-bold mb-0">{{ number_format($s['commercial'], 2) }}</dd>
            </dl>
        </section>
        <section class="tenant-panel tenant-panel--padded" data-commission-reconciliation>
            <h2 class="fs-5 fw-bolder mb-3">Reconciliation</h2>
            <dl class="row mb-3 fs-7">
                <dt class="col-8 text-muted fw-semibold mb-2">Rent collected (payments applied to rent)</dt><dd class="col-4 text-end fw-bold mb-2">{{ number_format($s['rent_collected'], 2) }}</dd>
                <dt class="col-8 text-muted fw-semibold mb-0">Rent recorded in the commission ledger</dt><dd class="col-4 text-end fw-bold mb-0">{{ number_format($s['rent_recorded'], 2) }}</dd>
            </dl>
            @if(abs($s['rent_collected'] - $s['rent_recorded']) < 0.01)
                <div class="alert alert-light-success border border-success py-3 mb-0 fs-7">Every shilling of rent collected in this period is in the ledger.</div>
            @else
                <div class="alert alert-light-warning border border-warning py-3 mb-0 fs-7">{{ number_format($s['rent_collected'] - $s['rent_recorded'], 2) }} of rent collected has no ledger entry (no agreement applied to it, or nobody was responsible).</div>
            @endif
            @if($s['gross_fee'] == 0 && $s['additional_cost'] > 0)
                <p class="text-muted fs-8 mt-3 mb-0">No company commission is set up, so agent commission is recorded as an additional cost. Set the company's fee under <a href="{{ $tabUrl('agreements') }}">Agreements</a>.</p>
            @endif
        </section>
    </div>
</div>
@endif
