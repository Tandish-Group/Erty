{{-- Commission statement: the rent payments behind an agent's commission for a period, the team override,
     clawbacks of paid commission whose payment was later reversed, and what is due now. --}}
@php
    $cur = $statement['currency'] ?? default_currency();
    $money = fn ($v) => money($v, $cur);
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('j M Y') : '';
    $due = $statement['due'];
@endphp
<div class="card border-0 shadow-sm mb-6" data-commission-statement>
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h2 class="fs-4 fw-bold mb-1">{{ $title ?? 'Commission statement' }}</h2>
            <span class="text-muted fs-7">{{ $date($statement['from']) }} – {{ $date($statement['to']) }} · rent collected on tenancies credited to {{ $statement['agent']['name'] }}</span>
        </div>
    </div>
    <div class="card-body pt-2">
        @if(empty($compact))
        <div class="row g-4 mb-6">
            <div class="col-6 col-lg-3"><div class="border border-dashed rounded p-4 h-100"><div class="text-muted fs-8 fw-semibold">Commission earned</div><div class="fs-4 fw-bold">{{ $money($statement['figures']['total_commission'] ?? 0) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="border border-dashed rounded p-4 h-100"><div class="text-muted fs-8 fw-semibold">Already paid out</div><div class="fs-4 fw-bold">{{ $money(max(0, ($statement['figures']['total_commission'] ?? 0) - $due['commission'] - $due['override'] - ($due['sales'] ?? 0))) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="border border-dashed rounded p-4 h-100"><div class="text-muted fs-8 fw-semibold">Clawbacks</div><div class="fs-4 fw-bold {{ $due['clawback'] > 0 ? 'text-danger' : '' }}">{{ $due['clawback'] > 0 ? '−' : '' }}{{ $money($due['clawback']) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="border border-dashed rounded p-4 h-100"><div class="text-muted fs-8 fw-semibold">Due now</div><div class="fs-3 fw-bold text-primary" data-statement-due>{{ $money($due['total']) }}</div></div></div>
        </div>
        @endif

        @if(!empty($compact) && !empty($statement['lines']))
        <details class="statement-lines mb-6">
        <summary><span class="fs-6 fw-bold">Rent collected</span> <span class="text-muted fs-7">{{ count($statement['lines']) }} {{ \Illuminate\Support\Str::plural('payment', count($statement['lines'])) }} · {{ $money(collect($statement['lines'])->sum('amount')) }}</span></summary>
        @else
        <h3 class="fs-6 fw-bold mb-3">Rent collected</h3>
        @endif
        @if(empty($statement['lines']))
            <p class="text-muted fs-7">No rent was collected on this agent's tenancies in this period.</p>
        @else
        <div class="table-responsive mb-6">
            <table class="table table-row-dashed align-middle fs-7 mb-0" data-statement-lines>
                <thead><tr class="text-muted fw-semibold"><th>Date</th><th>Tenant</th><th>Property · unit</th><th>Invoice</th><th>How</th><th class="text-end">Rent</th><th class="text-end">Commission</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($statement['lines'] as $line)
                    <tr>
                        <td>{{ $date($line['date']) }}</td>
                        <td>{{ profile_link($line['tenancy_url'] ?? null, $line['tenant']) }}<div class="text-muted fs-8">{{ $line['tenancy_ref'] }}</div></td>
                        <td>{{ $line['property'] }}{{ $line['unit'] ? ' · '.$line['unit'] : '' }}</td>
                        <td>{{ $line['invoice'] }}</td>
                        <td>{{ $line['via'] }}</td>
                        <td class="text-end text-nowrap">{{ $money($line['amount']) }}</td>
                        <td class="text-end text-nowrap">{{ $money($line['commission']) }}</td>
                        <td>{!! $line['paid'] ? '<span class="badge badge-light-success">Paid out</span>' : '<span class="badge badge-light-warning">Due</span>' !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
        @if(!empty($compact) && !empty($statement['lines']))</details>@endif

        @if(!empty($statement['period_lines']))
        <h3 class="fs-6 fw-bold mb-3">Period commission</h3>
        <div class="table-responsive mb-6">
            <table class="table table-row-dashed align-middle fs-7 mb-0" data-statement-periods>
                <thead><tr class="text-muted fw-semibold"><th>Period</th><th>How</th><th class="text-end">Basis</th><th class="text-end">Commission</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($statement['period_lines'] as $line)
                    <tr><td>{{ $date($line['from']) }} – {{ $date($line['to']) }}{{ $line['kind'] === 'adjustment' ? ' (adjustment)' : '' }}</td><td>{{ $line['agreement'] }}</td>
                        <td class="text-end text-nowrap">{{ $money($line['basis']) }}</td><td class="text-end text-nowrap">{{ $money($line['commission']) }}</td>
                        <td>{!! $line['paid'] ? '<span class="badge badge-light-success">Paid out</span>' : ($line['status'] === 'pending' ? '<span class="badge badge-light-warning">Waiting for approval</span>' : '<span class="badge badge-light-primary">Due</span>') !!}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($statement['sale_lines']))
        <h3 class="fs-6 fw-bold mb-3">House sales</h3>
        <div class="table-responsive mb-6">
            <table class="table table-row-dashed align-middle fs-7 mb-0" data-statement-sales>
                <thead><tr class="text-muted fw-semibold"><th>Date</th><th>Buyer</th><th>Property · house</th><th>Receipt</th><th class="text-end">Received</th><th class="text-end">Commission</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($statement['sale_lines'] as $line)
                    <tr>
                        <td>{{ $date($line['date']) }}</td>
                        <td>{{ $line['buyer'] }}<div class="text-muted fs-8">{{ $line['sale'] }}</div></td>
                        <td>{{ $line['property'] }}{{ $line['unit'] ? ' · House '.$line['unit'] : '' }}</td>
                        <td>{{ $line['receipt'] }}</td>
                        <td class="text-end text-nowrap">{{ $money($line['amount']) }}<div class="text-muted fs-8">of {{ $money($line['sale_price']) }}</div></td>
                        <td class="text-end text-nowrap">{{ $money($line['commission']) }}</td>
                        <td>{!! $line['paid'] ? '<span class="badge badge-light-success">Paid out</span>' : '<span class="badge badge-light-warning">Due</span>' !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($statement['override_lines']))
        <h3 class="fs-6 fw-bold mb-3">Team override</h3>
        <div class="table-responsive mb-6">
            <table class="table table-row-dashed align-middle fs-7 mb-0" data-statement-override>
                <thead><tr class="text-muted fw-semibold"><th>Date</th><th>Agent</th><th>Tenant</th><th>Property · unit</th><th class="text-end">Rent</th><th class="text-end">Override</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($statement['override_lines'] as $line)
                    <tr>
                        <td>{{ $date($line['date']) }}</td><td>{{ profile_link($line['agent_url'] ?? null, $line['agent']) }}</td><td>{{ profile_link($line['tenancy_url'] ?? null, $line['tenant']) }}</td>
                        <td>{{ $line['property'] }}{{ $line['unit'] ? ' · '.$line['unit'] : '' }}</td>
                        <td class="text-end text-nowrap">{{ $money($line['amount']) }}</td><td class="text-end text-nowrap">{{ $money($line['commission']) }}</td>
                        <td>{!! $line['paid'] ? '<span class="badge badge-light-success">Paid out</span>' : '<span class="badge badge-light-warning">Due</span>' !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($statement['clawbacks']))
        <h3 class="fs-6 fw-bold mb-3">Clawbacks</h3>
        <p class="text-muted fs-7">Commission already paid on these payments, which were later reversed (bounced or entered in error). It is taken off the next payout.</p>
        <div class="table-responsive mb-6">
            <table class="table table-row-dashed align-middle fs-7 mb-0" data-statement-clawbacks>
                <thead><tr class="text-muted fw-semibold"><th>Paid on</th><th>Tenant</th><th>Invoice</th><th class="text-end">Rent reversed</th><th class="text-end">Clawback</th></tr></thead>
                <tbody>
                @foreach($statement['clawbacks'] as $line)
                    <tr><td>{{ $date($line['date']) }}</td><td>{{ profile_link($line['tenancy_url'] ?? null, $line['tenant']) }}</td><td>{{ $line['invoice'] }}</td><td class="text-end text-nowrap">{{ $money($line['amount']) }}</td><td class="text-end text-nowrap text-danger">−{{ $money($line['commission']) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <h3 class="fs-6 fw-bold mb-3">Payouts</h3>
        @if($statement['payouts']->isEmpty())
            <p class="text-muted fs-7 mb-0">No commission has been paid out yet.</p>
        @else
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle fs-7 mb-0" data-statement-payouts>
                <thead><tr class="text-muted fw-semibold"><th>Paid</th><th>For</th><th>Method · reference</th><th class="text-end">Commission</th><th class="text-end">Override</th><th class="text-end">Clawback</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                @foreach($statement['payouts'] as $payout)
                    <tr>
                        <td>{{ $payout->paid_at->format('j M Y') }}</td>
                        <td>{{ $payout->period_from->format('j M') }} – {{ $payout->period_to->format('j M Y') }}</td>
                        <td>{{ str_replace('_', ' ', (string) $payout->method) ?: '—' }}{{ $payout->reference ? ' · '.$payout->reference : '' }}</td>
                        <td class="text-end text-nowrap">{{ $money($payout->commission) }}</td><td class="text-end text-nowrap">{{ $money($payout->override) }}</td>
                        <td class="text-end text-nowrap">{{ (float) $payout->clawback > 0 ? '−'.$money($payout->clawback) : '—' }}</td>
                        <td class="text-end text-nowrap fw-bold">{{ $money($payout->total) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
