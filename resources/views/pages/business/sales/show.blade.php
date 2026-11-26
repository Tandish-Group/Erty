@extends('pages.business.layouts.app')
@section('content')
@php
    $cur = $sale->currency ?: default_currency();
    $money = fn ($v) => money($v, $cur);
    $whole = fn ($v) => money($v, $cur, 0);
    $collected = $sale->collected();
    $balance = $sale->balance();
    $pct = $sale->price > 0 ? min(100, round($collected / $sale->price * 100)) : 0;
    $tone = ['reserved' => 'is-held', 'agreed' => 'is-let', 'completed' => 'is-free', 'cancelled' => ''];
    $instTone = ['due' => '', 'part_paid' => 'is-held', 'paid' => 'is-free', 'cancelled' => ''];
    $overdue = $sale->isLive() ? $sale->overdue() : collect();
    $house = $sale->unit;
    $earned = round($commission->where('status', '!=', 'reversed')->sum('amount'), 2);
    $next = $sale->isLive() ? $sale->instalments->first(fn ($i) => $i->outstanding() > 0) : null;
@endphp
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center" id="kt_header_container">
        <div class="page-title d-flex flex-column justify-content-center">
            <h1 class="text-dark fw-bolder my-0 fs-3">Sale {{ $sale->reference }}</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-7 my-1"><li class="breadcrumb-item text-muted"><a href="{{ route('sales.index') }}" class="text-muted text-hover-primary">Sales</a></li><li class="breadcrumb-item text-dark">{{ $sale->reference }}</li></ul>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl inv-page sale-page" id="kt_content_container" data-sale-show="{{ $sale->reference }}">
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-0">{{ $error }}</p>@endforeach</div>@endif

    <header class="inv-head">
        <div>
            <span class="inv-eyebrow">House sale · <span class="inv-status {{ $tone[$sale->status] ?? '' }}" data-sale-status>{{ $sale->statusLabel() }}</span></span>
            <h2>House {{ $house?->unit_number ?: '#' . $sale->residential_unit_id }} at <a class="inv-link" href="{{ route('complex.show', $sale->complex->slug) }}#houses">{{ $sale->complex->title }}</a></h2>
            <p>{{ $sale->buyer_name }} · agreed {{ $sale->agreement_date->format('j M Y') }} · {{ $house?->layoutLabel() }}{{ $house?->plotLabel() ? ' on ' . $house->plotLabel() : '' }}</p>
        </div>
        <div class="inv-actions">
            <a class="btn btn-light" href="{{ route('sales.statement', $sale->reference) }}"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>Buyer statement</a>
            @if($sale->isLive() && $canPay)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sale-payment-modal"><i class="bi bi-cash-coin me-2" aria-hidden="true"></i>Record payment</button>@endif
        </div>
    </header>

    <div class="inv-stats">
        <div><span>Price</span><strong>{{ $whole($sale->price) }}</strong><small>{{ $sale->deposit_amount > 0 ? 'deposit ' . $whole($sale->deposit_amount) : 'no deposit' }}</small></div>
        <div><span>Paid</span><strong class="is-free">{{ $whole($collected) }}</strong><small>{{ $sale->payments->where('status', 'confirmed')->count() }} {{ \Illuminate\Support\Str::plural('payment', $sale->payments->where('status', 'confirmed')->count()) }}</small></div>
        <div><span>{{ $overdue->isNotEmpty() ? 'Overdue' : 'Balance' }}</span><strong class="{{ $overdue->isNotEmpty() ? 'text-danger' : '' }}">{{ $whole($overdue->isNotEmpty() ? $overdue->sum(fn ($i) => $i->outstanding()) : $balance) }}</strong><small>{{ $overdue->isNotEmpty() ? $money($balance) . ' still owed in all' : ($sale->status === 'completed' ? 'completed ' . $sale->completed_at?->format('j M Y') : 'still owed') }}</small></div>
        <div class="inv-stat-area"><span>Progress</span><strong>{{ $pct }}%</strong>
            <div class="inv-bar" role="img" aria-label="{{ $pct }}% paid"><i style="width: {{ $pct }}%"></i></div>
            <small>{{ $next ? 'Next: ' . $next->label . ', ' . $money($next->outstanding()) . ' due ' . $next->due_date->format('j M Y') : ($sale->status === 'cancelled' ? 'Cancelled ' . $sale->cancelled_at?->format('j M Y') : 'Plan fully paid') }}</small>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-xl-8">
            <section class="inv-panel mb-6" id="plan" aria-labelledby="plan-heading">
                <div class="sale-panel-head"><h3 id="plan-heading">Payment plan</h3><span class="inv-sub">Payments are applied to the oldest unpaid instalment first.</span></div>
                <div class="table-responsive"><table class="table inv-table align-middle mb-0">
                    <thead><tr><th>Instalment</th><th>Due</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($sale->instalments as $i)
                        <tr data-instalment="{{ $i->sequence }}"><td><div class="inv-space fs-6">{{ $i->label }}</div><div class="inv-sub">{{ $i->number }}</div></td>
                            <td class="text-nowrap">{{ $i->due_date->format('j M Y') }}@if($i->isOverdue() && $sale->isLive())<div class="text-danger fs-8">{{ $i->due_date->diffForHumans(null, true) }} overdue</div>@endif</td>
                            <td class="text-end text-nowrap">{{ $money($i->amount) }}</td><td class="text-end text-nowrap">{{ $money($i->paid_amount) }}</td>
                            <td><span class="inv-status {{ $i->isOverdue() && $sale->isLive() ? 'is-repair' : ($instTone[$i->status] ?? '') }}">{{ $i->isOverdue() && $sale->isLive() ? 'Overdue' : \App\Models\PropertySaleInstalment::STATUSES[$i->status] }}</span></td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </section>

            <section class="inv-panel mb-6" aria-labelledby="payments-heading">
                <div class="sale-panel-head"><h3 id="payments-heading">Payments received</h3><span class="inv-sub">A mistake is reversed, never edited. Each payment has a receipt.</span></div>
                <div class="table-responsive"><table class="table inv-table align-middle mb-0">
                    <thead><tr><th>Receipt</th><th>Paid on</th><th>How</th><th class="text-end">Amount</th><th>Applied to</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    @forelse($sale->payments as $p)
                        <tr class="{{ $p->status === 'reversed' ? 'text-muted' : '' }}" data-payment="{{ $p->receipt_number }}">
                            <td class="text-nowrap"><div class="inv-space fs-6">{{ $p->receipt_number }}</div>@if($p->status === 'reversed')<span class="inv-status is-repair">Reversed</span>@endif</td>
                            <td class="text-nowrap">{{ $p->paid_on->format('j M Y') }}<div class="inv-sub">by {{ $p->recorder?->first_name ?? '—' }}</div></td>
                            <td>{{ $p->methodLabel() }}@if($p->reference)<div class="inv-sub">{{ $p->reference }}</div>@endif</td>
                            <td class="text-end text-nowrap">{{ $money($p->amount) }}</td>
                            <td class="fs-7">{{ $p->allocations->map(fn ($a) => $a->instalment?->label)->filter()->implode(', ') }}@if($p->status === 'reversed')<div class="inv-sub">{{ $p->reversal_reason }}</div>@endif</td>
                            <td class="text-end text-nowrap">
                                @if($p->status === 'confirmed')<a class="btn btn-sm btn-light" href="{{ route('sales.receipt', [$sale->reference, $p->id]) }}">Receipt</a>@endif
                                @if($p->status === 'confirmed' && $canPay && $sale->status !== 'completed')
                                <form method="POST" action="{{ route('sales.payments.reverse', [$sale->reference, $p->id]) }}" class="d-inline" onsubmit="const r = prompt('Why is this payment being reversed? (e.g. cheque bounced, entered twice)'); if (!r) return false; this.reason.value = r;">@csrf<input type="hidden" name="reason"><button class="btn btn-sm btn-light-danger ms-1">Reverse</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="inv-empty"><i class="bi bi-cash-stack" aria-hidden="true"></i><strong>No payments yet</strong><span>{{ $sale->deposit_amount > 0 ? 'The sale is agreed once the deposit is paid.' : 'Record the buyer\'s first payment when it arrives.' }}</span></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>

            @if($canSeeCommission)
            <section class="inv-panel mb-6" aria-labelledby="commission-heading">
                <div class="sale-panel-head"><h3 id="commission-heading">Sales commission</h3><span class="inv-sub">{{ $sale->agent?->full_name }} earns as the buyer pays. It goes on their commission statement.</span></div>
                @if($commission->isEmpty())
                    <p class="text-muted fs-7 px-6 py-5 mb-0">No sales commission recorded yet{{ $sale->payments->where('status', 'confirmed')->isNotEmpty() ? ': the agent has no sales commission agreement. Add one under Agents → Commissions → Agreements.' : '.' }}</p>
                @else
                <div class="table-responsive"><table class="table inv-table align-middle mb-0">
                    <thead><tr><th>Date</th><th>Terms</th><th class="text-end">On</th><th class="text-end">Commission</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($commission->sortBy('id') as $e)
                        <tr><td>{{ $e->collected_on?->format('j M Y') }}</td><td class="fs-7">{{ $e->breakdown['agreement'] ?? $e->method }}@if($e->kind === 'reversal')<div class="inv-sub">Reversal</div>@endif</td><td class="text-end">{{ $money($e->basis_amount) }}</td><td class="text-end">{{ $money($e->amount) }}</td><td><span class="inv-status {{ ['paid' => 'is-free', 'reversed' => 'is-repair'][$e->status] ?? 'is-let' }}">{{ ucfirst($e->status) }}</span></td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="sale-panel-foot">Earned so far: <strong>{{ $money($earned) }}</strong></div>
                @endif
            </section>
            @endif
        </div>

        <aside class="col-xl-4">
            <section class="inv-panel sale-section">
                <h3>Buyer</h3>
                <dl class="sale-facts">
                    <div><dt>Name</dt><dd>{{ $sale->buyer_name }}</dd></div>
                    @if($sale->buyer_phone)<div><dt>Phone</dt><dd><a href="tel:{{ preg_replace('/[^+0-9]/', '', $sale->buyer_phone) }}">{{ $sale->buyer_phone }}</a></dd></div>@endif
                    @if($sale->buyer_email)<div><dt>Email</dt><dd><a href="mailto:{{ $sale->buyer_email }}">{{ $sale->buyer_email }}</a></dd></div>@endif
                    @if($sale->buyer_id_number)<div><dt>ID / passport</dt><dd>{{ $sale->buyer_id_number }}</dd></div>@endif
                    @if($sale->buyer_kra_pin)<div><dt>KRA PIN</dt><dd>{{ $sale->buyer_kra_pin }}</dd></div>@endif
                    @if($sale->lead)<div><dt>Lead</dt><dd><a href="{{ route('lead_management.show', \Illuminate\Support\Facades\Crypt::encrypt($sale->lead->id)) }}">{{ $sale->lead->full_name }}</a></dd></div>@endif
                </dl>
            </section>
            <section class="inv-panel sale-section">
                <h3>Sale</h3>
                <dl class="sale-facts">
                    <div><dt>Agent</dt><dd>{{ $sale->agent ? profile_link(agent_profile_url($sale->agent), $sale->agent->full_name) : 'No agent' }}</dd></div>
                    <div><dt>House</dt><dd>{{ $house?->label() }}{{ $house?->plot_number ? ' · plot ' . $house->plot_number : '' }}{{ $house?->title_number ? ' · title ' . $house->title_number : '' }}</dd></div>
                    <div><dt>Started</dt><dd>{{ $sale->created_at->format('j M Y') }} by {{ $sale->creator?->first_name ?? '—' }}</dd></div>
                    @if($sale->completed_at)<div><dt>Completed</dt><dd>{{ $sale->completed_at->format('j M Y') }}@if($sale->completion_note)<small class="d-block text-muted">{{ $sale->completion_note }}</small>@endif</dd></div>@endif
                    @if($sale->status === 'completed')<div><dt>Title transfer</dt><dd>{{ $sale->title_transferred_at?->format('j M Y') ?? 'Not recorded yet' }}</dd></div>@endif
                    @if($sale->cancelled_at)<div><dt>Cancelled</dt><dd>{{ $sale->cancelled_at->format('j M Y') }}<small class="d-block text-muted">{{ $sale->cancel_reason }}</small></dd></div>@endif
                    @if($sale->notes)<div><dt>Notes</dt><dd class="fs-7">{!! nl2br(e($sale->notes)) !!}</dd></div>@endif
                </dl>
            </section>

            @if($sale->isLive() && $canComplete)
            <section class="inv-panel sale-section" data-sale-complete>
                <h3>Complete the sale</h3>
                <p class="text-muted fs-7">Marks the house as sold and records the sale in the property history.</p>
                <form method="POST" action="{{ route('sales.complete', $sale->reference) }}">@csrf
                    @if($balance > 0)
                        <label class="form-label fs-7" for="completion-note">{{ $money($balance) }} is still owed. How was it settled?</label>
                        <textarea class="form-control mb-3" id="completion-note" name="completion_note" rows="2" maxlength="1000" required minlength="10" placeholder="e.g. Mortgage paid the balance directly to the seller on 12 Dec"></textarea>
                    @endif
                    <button class="btn btn-success w-100" onclick="return confirm('Complete this sale and mark the house as sold?')">Complete sale</button>
                </form>
            </section>
            @endif
            @if($sale->status === 'completed' && !$sale->title_transferred_at && $canComplete)
            <section class="inv-panel sale-section">
                <h3>Title transfer</h3>
                <form method="POST" action="{{ route('sales.title', $sale->reference) }}" class="d-flex gap-2">@csrf<input type="date" class="form-control" name="title_transferred_at" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required aria-label="Title transferred on"><button class="btn btn-light-primary text-nowrap">Record</button></form>
            </section>
            @endif
            @if($sale->isLive() && $canCancel)
            <section class="inv-panel sale-section">
                <h3>Cancel the sale</h3>
                <form method="POST" action="{{ route('sales.cancel', $sale->reference) }}">@csrf
                    <label class="form-label fs-7" for="cancel-reason">Reason</label>
                    <textarea class="form-control mb-3" id="cancel-reason" name="cancel_reason" rows="2" maxlength="1000" required minlength="5"></textarea>
                    @if($collected > 0)<label class="form-check fs-7 mb-3"><input class="form-check-input" type="checkbox" name="refund" value="1"> <span class="form-check-label">The buyer's {{ $money($collected) }} was refunded (reverses every payment and its commission)</span></label>@endif
                    <button class="btn btn-light-danger w-100" onclick="return confirm('Cancel this sale and put the house back on the market?')">Cancel sale</button>
                </form>
            </section>
            @endif
        </aside>
    </div>

    @if($sale->isLive() && $canPay)
    <div class="modal fade" id="sale-payment-modal" tabindex="-1" aria-labelledby="sale-payment-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('sales.payments.store', $sale->reference) }}">@csrf
        <div class="modal-header"><h2 class="fs-4 mb-0" id="sale-payment-title">Record a payment</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="row g-4">
                <div class="col-sm-6"><label class="form-label required" for="pay-amount">Amount ({{ $cur }})</label><input class="form-control" id="pay-amount" name="amount" type="number" min="0.01" max="{{ $balance }}" step="any" required value="{{ old('amount', $next?->outstanding()) }}"><div class="form-text">Up to {{ $money($balance) }}.</div></div>
                <div class="col-sm-6"><label class="form-label required" for="pay-date">Received on</label><input class="form-control" id="pay-date" name="paid_on" type="date" required max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}"></div>
                <div class="col-sm-6"><label class="form-label required" for="pay-method">How</label><select class="form-select" id="pay-method" name="method" required>@foreach(config('property.sale_payment_methods') as $v => $l)<option value="{{ $v }}" @selected(old('method') === $v)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-sm-6"><label class="form-label" for="pay-ref">Reference</label><input class="form-control" id="pay-ref" name="reference" maxlength="120" value="{{ old('reference') }}" placeholder="Bank or M-Pesa reference"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Record payment & issue receipt</button></div>
    </form></div></div>
    @endif
</div>
</div>
@endsection
