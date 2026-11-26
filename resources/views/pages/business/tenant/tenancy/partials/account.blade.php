{{-- The tenant's account for this tenancy (and any tenancy it was transferred from): what is owed, credit,
     deposit, invoices, money received, and the running statement. --}}
@php
    $tenancyModel = \App\Models\TenantManagement::findOrFail($tenantData['basic_info']['id']);
    $accountService = app(\App\Services\Rent\TenantAccountService::class);
    $chain = $accountService->chainIds($tenancyModel);
    $sum = $accountService->summary($tenancyModel);
    $invoices = \App\Models\TenantInvoice::whereIn('tenancy_id', $chain)->orderByDesc('due_date')->orderByDesc('id')->get();
    $entries = \App\Models\TenantPayment::whereIn('tenant_id', $chain)->with('invoiceAllocations')->orderByDesc('payment_date')->orderByDesc('id')->get();
    $statement = $accountService->statement($tenancyModel);
    $cur = $sum['currency'];
    $money = fn ($v) => money($v, $cur);
    $statusBadge = fn ($label) => match (true) {
        str_starts_with($label, 'Overdue') => 'badge-light-danger',
        $label === 'Paid' => 'badge-light-success',
        $label === 'Partly paid' => 'badge-light-warning',
        in_array($label, ['Void', 'Waived'], true) => 'badge-light-dark',
        default => 'badge-light-primary',
    };
    $entryBadge = ['pending' => ['Awaiting clearance', 'badge-light-warning'], 'confirmed' => ['Confirmed', 'badge-light-success'], 'reversed' => ['Reversed', 'badge-light-danger']];
    $methods = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'check' => 'Cheque', 'card' => 'Card', 'other' => 'Other'];
    $canPay = auth()->user()->hasBusinessPermission('tenant_management', 'payments');
    $canBill = auth()->user()->hasBusinessPermission('tenant_management', 'billing');
    $canFollowUp = $canPay || auth()->user()->hasBusinessPermission('tenant_management', 'create');
    $followUps = \App\Models\TenantFollowUp::with('user:id,first_name,last_name')->whereIn('tenancy_id', $chain)->latest('contacted_on')->latest('id')->limit(20)->get();
    $openPromise = $followUps->firstWhere('promise_status', 'open');
    $promiseBadge = ['open' => ['Promise open', 'badge-light-primary'], 'kept' => ['Kept', 'badge-light-success'], 'broken' => ['Broken', 'badge-light-danger']];
    $openInvoices = $invoices->filter(fn ($i) => in_array($i->status, [\App\Models\TenantInvoice::UNPAID, \App\Models\TenantInvoice::PARTIAL], true))->sortBy('due_date');
@endphp
<div data-tenant-account data-tenancy-id="{{ $tenancyModel->id }}">
    <div class="row g-5 mb-7">
        <div class="col-6 col-xl"><div class="card card-flush h-100"><div class="card-body p-5">
            <div class="text-muted fs-7 fw-semibold">{{ $sum['balance'] < 0 ? 'In credit' : 'Balance due' }}</div>
            <div class="fs-2 fw-bold {{ $sum['balance'] > 0 ? 'text-dark' : 'text-success' }}" data-account="balance">{{ $money(abs($sum['balance'])) }}</div>
            <div class="text-muted fs-8">{{ $sum['open_invoices'] }} unpaid {{ \Illuminate\Support\Str::plural('invoice', $sum['open_invoices']) }}</div>
        </div></div></div>
        <div class="col-6 col-xl"><div class="card card-flush h-100"><div class="card-body p-5">
            <div class="text-muted fs-7 fw-semibold">Overdue</div>
            <div class="fs-2 fw-bold {{ $sum['overdue'] > 0 ? 'text-danger' : '' }}" data-account="overdue">{{ $money($sum['overdue']) }}</div>
        </div></div></div>
        <div class="col-6 col-xl"><div class="card card-flush h-100"><div class="card-body p-5">
            <div class="text-muted fs-7 fw-semibold">Credit</div>
            <div class="fs-2 fw-bold" data-account="credit">{{ $money($sum['credit']) }}</div>
            <div class="text-muted fs-8">Used on the next invoice</div>
        </div></div></div>
        <div class="col-6 col-xl"><div class="card card-flush h-100"><div class="card-body p-5">
            <div class="text-muted fs-7 fw-semibold">Deposit held</div>
            <div class="fs-2 fw-bold" data-account="deposit_held">{{ $money($sum['deposit_held']) }}</div>
            <div class="text-muted fs-8">of {{ $money($sum['deposit_required']) }} billed</div>
        </div></div></div>
        @if($sum['pending_payments'] > 0)
        <div class="col-6 col-xl"><div class="card card-flush h-100"><div class="card-body p-5">
            <div class="text-muted fs-7 fw-semibold">Awaiting clearance</div>
            <div class="fs-2 fw-bold text-warning" data-account="pending">{{ $money($sum['pending_payments']) }}</div>
            <div class="text-muted fs-8">Not applied until confirmed</div>
        </div></div></div>
        @endif
    </div>

    <div class="d-flex flex-wrap gap-2 mb-7">
        @if($canPay)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#account_payment_modal" data-account-action="pay"><i class="fas fa-plus"></i> Record payment</button>@endif
        @if($canBill)<button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#account_charge_modal" data-account-action="charge">Add a charge</button>@endif
        @if($canPay && ($sum['credit'] > 0 || $sum['deposit_held'] > 0))<button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#account_refund_modal" data-account-action="refund">Refund</button>@endif
        @if($canFollowUp)<button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#account_follow_up_modal" data-account-action="follow-up"><i class="fas fa-phone"></i> Log follow-up</button>@endif
        <a class="btn btn-light" href="{{ route('tenant_account.statement_pdf', $tenancyModel->id) }}" data-account-action="statement-pdf"><i class="fas fa-file-pdf"></i> Statement (PDF)</a>
    </div>
    <p class="text-muted fs-7 mb-7">Rent is billed at the start of each period and is due {{ (int) $tenancyModel->grace_period_days }} {{ \Illuminate\Support\Str::plural('day', (int) $tenancyModel->grace_period_days) }} later. Money received pays the oldest unpaid invoice first; anything extra is held as credit.@if((float) $tenancyModel->late_fee_amount > 0) A late fee of {{ $money($tenancyModel->late_fee_amount) }} is added once rent is past its due date.@endif</p>

    @if($openPromise)
        <div class="alert {{ $openPromise->promise_date->lt(today()) ? 'alert-light-danger' : 'alert-light-primary' }} d-flex flex-wrap align-items-center gap-3 py-3 fs-7 mb-7" data-open-promise>
            <i class="fas fa-handshake"></i>
            <span class="flex-grow-1">Promised to pay {{ $money($openPromise->promise_amount) }} by {{ $openPromise->promise_date->format('j M Y') }}{{ $openPromise->promise_date->lt(today()) ? ' — this date has passed' : '' }} (recorded {{ $openPromise->contacted_on->format('j M Y') }}).</span>
            @if($canFollowUp)
                <button type="button" class="btn btn-sm btn-light-success" data-promise="{{ $openPromise->id }}" data-promise-status="kept">Mark kept</button>
                <button type="button" class="btn btn-sm btn-light-danger" data-promise="{{ $openPromise->id }}" data-promise-status="broken">Mark broken</button>
            @endif
        </div>
    @endif

    {{-- Invoices --}}
    <div class="card mb-7">
        <div class="card-header"><div class="card-title"><h3 class="fw-bolder mb-0">Invoices</h3></div></div>
        <div class="card-body pt-0">
            @if($invoices->isEmpty())
                <p class="text-muted py-5 mb-0">No invoices yet.</p>
            @else
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle fs-7" data-account-invoices>
                    <thead><tr class="text-muted fw-semibold text-uppercase fs-8"><th>Invoice</th><th>For</th><th>Due</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($invoices as $invoice)
                        <tr data-invoice-row="{{ $invoice->invoice_number }}" data-kind="{{ $invoice->kind }}" data-status="{{ $invoice->display_status }}">
                            <td class="fw-bold">{{ $invoice->invoice_number }}<div class="text-muted fs-8 fw-normal">{{ $invoice->kind_label }}@if($invoice->tenancy_id !== $tenancyModel->id) · earlier unit @endif</div></td>
                            <td>{{ $invoice->line_items[0]['description'] ?? $invoice->kind_label }}@if($invoice->void_reason)<div class="text-muted fs-8">{{ $invoice->display_status }}: {{ $invoice->void_reason }}</div>@endif</td>
                            <td>{{ $invoice->due_date?->format('j M Y') }}</td>
                            <td class="text-end">{{ $money($invoice->amount) }}</td>
                            <td class="text-end">{{ $money($invoice->paid_amount) }}</td>
                            <td class="text-end fw-bold">{{ $money($invoice->balance) }}</td>
                            <td><span class="badge {{ $statusBadge($invoice->display_status) }}">{{ $invoice->display_status }}</span></td>
                            <td class="text-end">
                                @if($canBill && in_array($invoice->kind, ['late_fee', 'adjustment'], true) && !$invoice->isVoid())
                                    <button type="button" class="btn btn-sm btn-light" data-void-invoice="{{ $invoice->id }}" data-label="{{ $invoice->kind === 'late_fee' ? 'Waive' : 'Void' }} {{ $invoice->invoice_number }}">{{ $invoice->kind === 'late_fee' ? 'Waive' : 'Void' }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Money received and other entries --}}
    <div class="card mb-7">
        <div class="card-header"><div class="card-title"><h3 class="fw-bolder mb-0">Payments, credits and refunds</h3></div></div>
        <div class="card-body pt-0">
            @if($entries->isEmpty())
                <p class="text-muted py-5 mb-0">No payments recorded yet.</p>
            @else
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle fs-7" data-account-payments>
                    <thead><tr class="text-muted fw-semibold text-uppercase fs-8"><th>Date</th><th>Entry</th><th>Method · reference</th><th class="text-end">Amount</th><th>Applied to</th><th class="text-end">Credit left</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($entries as $entry)
                        @php
                            [$stLabel, $stClass] = $entryBadge[$entry->payment_status] ?? [ucfirst((string) $entry->payment_status), 'badge-light'];
                            $applied = $entry->invoiceAllocations->whereNull('reversed_at');
                            $appliedNumbers = $invoices->whereIn('id', $applied->pluck('invoice_id'))->pluck('invoice_number');
                        @endphp
                        <tr data-payment-row="{{ $entry->id }}" data-entry="{{ $entry->entry_type }}" data-status="{{ $entry->payment_status }}">
                            <td>{{ $entry->payment_date->format('j M Y') }}</td>
                            <td>{{ $entry->entry_type === 'deposit_release' && ($entry->allocation_data['purpose'] ?? null) === 'returned' ? 'Deposit returned' : (\App\Models\TenantPayment::ENTRY_LABELS[$entry->entry_type] ?? 'Payment') }}@if($entry->payment_notes)<div class="text-muted fs-8">{{ \Illuminate\Support\Str::limit($entry->payment_notes, 80) }}</div>@endif @if($entry->reversal_reason)<div class="text-danger fs-8">Reversed: {{ $entry->reversal_reason }}</div>@endif</td>
                            <td>{{ $entry->entry_type === 'payment' || $entry->entry_type === 'refund' ? ($methods[$entry->payment_method] ?? $entry->payment_method) : '—' }}{{ $entry->transaction_reference ? ' · '.$entry->transaction_reference : '' }}</td>
                            <td class="text-end {{ $entry->entry_type === 'refund' ? 'text-danger' : '' }}">{{ $entry->entry_type === 'refund' ? '−' : '' }}{{ $money($entry->amount_paid) }}</td>
                            <td class="fs-8">{{ $appliedNumbers->isEmpty() ? '—' : $appliedNumbers->implode(', ') }}</td>
                            <td class="text-end">{{ (float) $entry->unallocated_amount > 0 ? $money($entry->unallocated_amount) : '—' }}</td>
                            <td><span class="badge {{ $stClass }}">{{ $stLabel }}</span></td>
                            <td class="text-end text-nowrap">
                                @if($entry->receipt_path)<a class="btn btn-sm btn-light" href="{{ route('tenant_account.receipt', $entry->id) }}">Receipt</a>@endif
                                @if($canPay && $entry->payment_status === 'pending')
                                    <button type="button" class="btn btn-sm btn-light-success" data-confirm-payment="{{ $entry->id }}">Confirm</button>
                                @endif
                                @if($canPay && $entry->payment_status !== 'reversed' && in_array($entry->entry_type, ['payment', 'refund'], true))
                                    <button type="button" class="btn btn-sm btn-light-danger" data-reverse-payment="{{ $entry->id }}" data-label="{{ $money($entry->amount_paid) }} on {{ $entry->payment_date->format('j M Y') }}">Reverse</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Follow-ups --}}
    <div class="card mb-7" data-account-follow-ups>
        <div class="card-header"><div class="card-title"><h3 class="fw-bolder mb-0">Follow-ups</h3></div></div>
        <div class="card-body pt-0">
            @if($followUps->isEmpty())
                <p class="text-muted py-5 mb-0">No follow-ups recorded.@if($canFollowUp && $sum['overdue'] > 0) Use <strong>Log follow-up</strong> to record a call, visit or message about the overdue rent.@endif</p>
            @else
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle fs-7">
                    <thead><tr class="text-muted fw-semibold text-uppercase fs-8"><th>Date</th><th>How</th><th>What was said</th><th>Promise to pay</th><th>By</th></tr></thead>
                    <tbody>
                    @foreach($followUps as $f)
                        <tr data-follow-up-row="{{ $f->id }}">
                            <td class="text-nowrap">{{ $f->contacted_on->format('j M Y') }}</td>
                            <td>{{ \App\Models\TenantFollowUp::METHODS[$f->method] ?? $f->method }}</td>
                            <td>{{ $f->note }}</td>
                            <td class="text-nowrap">@if($f->promise_date){{ $money($f->promise_amount) }} by {{ $f->promise_date->format('j M Y') }} <span class="badge {{ $promiseBadge[$f->promise_status][1] ?? 'badge-light' }}">{{ $promiseBadge[$f->promise_status][0] ?? '' }}</span>@else — @endif</td>
                            <td class="text-nowrap">{{ $f->user ? trim($f->user->first_name.' '.$f->user->last_name) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-muted fs-8 mb-0">A record of what the team did. {{ config('app.name') }} does not send these messages.</p>
            @endif
        </div>
    </div>

    {{-- Statement --}}
    <div class="card mb-7">
        <div class="card-header"><div class="card-title"><h3 class="fw-bolder mb-0">Statement</h3></div></div>
        <div class="card-body pt-0">
            @if(empty($statement['lines']))
                <p class="text-muted py-5 mb-0">Nothing on the statement yet.</p>
            @else
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle fs-7" data-account-statement>
                    <thead><tr class="text-muted fw-semibold text-uppercase fs-8"><th>Date</th><th>Details</th><th>Reference</th><th class="text-end">Charged</th><th class="text-end">Paid / credited</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                    @foreach($statement['lines'] as $line)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($line['date'])->format('j M Y') }}</td>
                            <td>{{ $line['description'] }}</td>
                            <td class="fs-8">{{ $line['reference'] }}</td>
                            <td class="text-end">{{ $line['debit'] > 0 ? $money($line['debit']) : '' }}</td>
                            <td class="text-end">{{ $line['credit'] > 0 ? $money($line['credit']) : '' }}</td>
                            <td class="text-end fw-bold">{{ $line['balance'] < 0 ? '−' : '' }}{{ $money(abs($line['balance'])) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-muted fs-8 mb-0">Balance is what the tenant owes; a minus means they are in credit. Payments awaiting clearance are not shown until confirmed.</p>
            @endif
        </div>
    </div>
</div>

{{-- Record payment --}}
<div class="modal fade" id="account_payment_modal" tabindex="-1" aria-labelledby="account_payment_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px"><div class="modal-content">
        <form data-account-form="{{ route('tenant_account.record', $tenancyModel->id) }}" enctype="multipart/form-data">
            <div class="modal-header"><h2 class="fw-bolder fs-4 mb-0" id="account_payment_title">Record payment</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="alert alert-light-primary py-3 fs-7">Balance due {{ $money(max(0, $sum['balance'])) }}@if($sum['credit'] > 0) · credit {{ $money($sum['credit']) }}@endif. The payment settles the oldest unpaid invoice first; anything extra is held as credit.</div>
                <div class="row g-4">
                    <div class="col-md-6"><label class="form-label required" for="pay_amount">Amount received</label><input type="number" step="0.01" min="0.01" class="form-control" id="pay_amount" name="amount" value="{{ $sum['balance'] > 0 ? number_format($sum['balance'], 2, '.', '') : '' }}" required></div>
                    <div class="col-md-6"><label class="form-label required" for="pay_date">Date received</label><input type="date" class="form-control" id="pay_date" name="payment_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                    <div class="col-md-6"><label class="form-label required" for="pay_method">Method</label>
                        <select class="form-select" id="pay_method" name="payment_method">@foreach($methods as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label" for="pay_ref">Reference (e.g. M-Pesa code, cheque no.)</label><input type="text" class="form-control" id="pay_ref" name="transaction_reference" maxlength="255"></div>
                    <div class="col-12">
                        <span class="form-label d-block">Has the money cleared?</span>
                        <div class="d-flex gap-6">
                            <label class="form-check"><input class="form-check-input" type="radio" name="received" value="confirmed" checked> <span class="form-check-label">Yes, received</span></label>
                            <label class="form-check"><input class="form-check-input" type="radio" name="received" value="pending"> <span class="form-check-label">Not yet (e.g. a cheque awaiting clearance)</span></label>
                        </div>
                    </div>
                    @if($openInvoices->isNotEmpty())
                    <div class="col-12"><label class="form-label" for="pay_first">Pay this invoice first (optional)</label>
                        <select class="form-select" id="pay_first" name="first_invoice_id"><option value="">Oldest first</option>
                            @foreach($openInvoices as $open)<option value="{{ $open->id }}">{{ $open->invoice_number }} · {{ $open->kind_label }} · {{ $money($open->balance) }} due {{ $open->due_date?->format('j M Y') }}</option>@endforeach
                        </select></div>
                    @endif
                    <div class="col-12"><label class="form-label" for="pay_notes">Notes</label><textarea class="form-control" id="pay_notes" name="payment_notes" rows="2"></textarea></div>
                    <div class="col-12"><label class="form-label" for="pay_receipt">Receipt or proof (optional)</label><input type="file" class="form-control" id="pay_receipt" name="payment_receipt" accept="image/*,.pdf"></div>
                </div>
                <div class="text-danger fs-7 mt-3 d-none" data-form-error role="alert"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Record payment</button></div>
        </form>
    </div></div>
</div>

{{-- Add a charge --}}
<div class="modal fade" id="account_charge_modal" tabindex="-1" aria-labelledby="account_charge_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px"><div class="modal-content">
        <form data-account-form="{{ route('tenant_account.charge', $tenancyModel->id) }}">
            <div class="modal-header"><h2 class="fw-bolder fs-4 mb-0" id="account_charge_title">Add a charge</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="text-muted fs-7">A one-off charge such as a repair, a replaced key or utilities. To change the rent, use Edit tenancy.</p>
                <div class="row g-4">
                    <div class="col-12"><label class="form-label required" for="charge_desc">What it is for</label><input type="text" class="form-control" id="charge_desc" name="description" maxlength="255" required></div>
                    <div class="col-md-6"><label class="form-label required" for="charge_amount">Amount</label><input type="number" step="0.01" min="0.01" class="form-control" id="charge_amount" name="amount" required></div>
                    <div class="col-md-6"><label class="form-label" for="charge_due">Due date</label><input type="date" class="form-control" id="charge_due" name="due_date" value="{{ now()->toDateString() }}"></div>
                </div>
                <div class="text-danger fs-7 mt-3 d-none" data-form-error role="alert"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add charge</button></div>
        </form>
    </div></div>
</div>

{{-- Refund --}}
<div class="modal fade" id="account_refund_modal" tabindex="-1" aria-labelledby="account_refund_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px"><div class="modal-content">
        <form data-account-form="{{ route('tenant_account.refund', $tenancyModel->id) }}">
            <div class="modal-header"><h2 class="fw-bolder fs-4 mb-0" id="account_refund_title">Refund the tenant</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-12"><span class="form-label d-block">Refund from</span>
                        <label class="form-check mb-2"><input class="form-check-input" type="radio" name="from" value="credit" @checked($sum['credit'] > 0) @disabled($sum['credit'] <= 0)> <span class="form-check-label">Credit on the account ({{ $money($sum['credit']) }})</span></label>
                        <label class="form-check"><input class="form-check-input" type="radio" name="from" value="deposit" @checked($sum['credit'] <= 0) @disabled($sum['deposit_held'] <= 0)> <span class="form-check-label">Deposit held ({{ $money($sum['deposit_held']) }})</span></label>
                    </div>
                    <div class="col-md-6"><label class="form-label required" for="refund_amount">Amount</label><input type="number" step="0.01" min="0.01" class="form-control" id="refund_amount" name="amount" required></div>
                    <div class="col-md-6"><label class="form-label required" for="refund_date">Paid on</label><input type="date" class="form-control" id="refund_date" name="payment_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                    <div class="col-md-6"><label class="form-label" for="refund_method_acc">Method</label><select class="form-select" id="refund_method_acc" name="payment_method">@foreach($methods as $v => $l)<option value="{{ $v }}" @selected($v === 'bank_transfer')>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label" for="refund_ref">Reference</label><input type="text" class="form-control" id="refund_ref" name="transaction_reference" maxlength="255"></div>
                </div>
                <div class="text-danger fs-7 mt-3 d-none" data-form-error role="alert"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Record refund</button></div>
        </form>
    </div></div>
</div>

{{-- Log follow-up --}}
@if($canFollowUp)
<div class="modal fade" id="account_follow_up_modal" tabindex="-1" aria-labelledby="account_follow_up_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px"><div class="modal-content">
        <form data-account-form="{{ route('tenant_account.follow_up', $tenancyModel->id) }}">
            <div class="modal-header"><h2 class="fw-bolder fs-4 mb-0" id="account_follow_up_title">Log follow-up</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="text-muted fs-7">Record a call, visit or message about this account. Nothing is sent to the tenant from {{ config('app.name') }}.@if($sum['overdue'] > 0) Overdue now: <strong>{{ $money($sum['overdue']) }}</strong>.@endif</p>
                <div class="row g-4">
                    <div class="col-md-6"><label class="form-label required" for="fu_method">How</label>
                        <select class="form-select" id="fu_method" name="method">@foreach(\App\Models\TenantFollowUp::METHODS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label required" for="fu_date">Date</label><input type="date" class="form-control" id="fu_date" name="contacted_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                    <div class="col-12"><label class="form-label required" for="fu_note">What was said or agreed</label><textarea class="form-control" id="fu_note" name="note" rows="3" maxlength="2000" required></textarea></div>
                    <div class="col-12"><span class="form-label d-block">Promise to pay (optional)</span></div>
                    <div class="col-md-6"><label class="form-label" for="fu_promise_amount">Amount</label><input type="number" step="0.01" min="0.01" class="form-control" id="fu_promise_amount" name="promise_amount"></div>
                    <div class="col-md-6"><label class="form-label" for="fu_promise_date">By</label><input type="date" class="form-control" id="fu_promise_date" name="promise_date"></div>
                </div>
                <div class="text-danger fs-7 mt-3 d-none" data-form-error role="alert"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save follow-up</button></div>
        </form>
    </div></div>
</div>
@endif

<script>
(function () {
    // The dialogs are rendered inside the Billing tab; move them to <body> so the header's
    // "Record payment" works from any tab (a dialog inside a hidden tab can't be seen).
    ['account_payment_modal', 'account_charge_modal', 'account_refund_modal', 'account_follow_up_modal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal && modal.parentElement !== document.body) document.body.appendChild(modal);
    });
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const reloadOnBilling = () => { window.location.hash = 'payments'; window.location.reload(); };
    const send = (url, body) => fetch(url, {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}, body})
        .then(async r => { const json = await r.json().catch(() => ({})); if (!r.ok) throw json; return json; });
    const firstError = (e) => e && e.errors ? Object.values(e.errors).flat()[0] : (e && e.message) || 'Something went wrong. Please try again.';

    document.querySelectorAll('form[data-account-form]').forEach(form => {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            const err = form.querySelector('[data-form-error]');
            const btn = form.querySelector('[type="submit"]');
            err.classList.add('d-none'); btn.disabled = true;
            send(form.dataset.accountForm, new FormData(form))
                .then(json => { if (window.toastr) toastr.success(json.message); reloadOnBilling(); })
                .catch(e => { err.textContent = firstError(e); err.classList.remove('d-none'); btn.disabled = false; });
        });
    });

    const ask = (title, text, placeholder) => window.Swal
        ? Swal.fire({title, text, input: placeholder ? 'text' : undefined, inputPlaceholder: placeholder, showCancelButton: true, confirmButtonText: 'Continue',
            inputValidator: placeholder ? (v) => !v ? 'Please give a reason.' : undefined : undefined}).then(r => r.isConfirmed ? (r.value ?? true) : null)
        : Promise.resolve(placeholder ? window.prompt(title + '\n' + text) : window.confirm(title + '\n' + text));
    const act = (url, fields) => { const fd = new FormData(); Object.entries(fields || {}).forEach(([k, v]) => fd.append(k, v));
        return send(url, fd).then(json => { if (window.toastr) toastr.success(json.message); reloadOnBilling(); })
            .catch(e => window.Swal ? Swal.fire({icon: 'error', text: firstError(e)}) : alert(firstError(e))); };

    document.addEventListener('click', function (ev) {
        const confirmBtn = ev.target.closest('[data-confirm-payment]');
        const reverseBtn = ev.target.closest('[data-reverse-payment]');
        const voidBtn = ev.target.closest('[data-void-invoice]');
        const promiseBtn = ev.target.closest('[data-promise]');
        if (promiseBtn) {
            ask('Mark this promise ' + promiseBtn.dataset.promiseStatus + '?', '').then(ok => ok && act('/tenant_management/account/follow-ups/' + promiseBtn.dataset.promise + '/promise', {promise_status: promiseBtn.dataset.promiseStatus}));
            return;
        }
        if (confirmBtn) {
            ask('Confirm this payment?', 'Do this once the money has cleared. It will then be applied to the unpaid invoices.').then(ok => ok && act('/tenant_management/account/payments/' + confirmBtn.dataset.confirmPayment + '/confirm'));
        } else if (reverseBtn) {
            ask('Reverse ' + reverseBtn.dataset.label + '?', 'Use this for a bounced cheque or a payment entered in error. What it paid will be owed again.', 'Reason, e.g. cheque bounced').then(reason => reason && act('/tenant_management/account/payments/' + reverseBtn.dataset.reversePayment + '/reverse', {reason}));
        } else if (voidBtn) {
            ask(voidBtn.dataset.label + '?', 'The tenant will no longer owe this charge.', 'Reason').then(reason => reason && act('/tenant_management/account/invoices/' + voidBtn.dataset.voidInvoice + '/void', {reason}));
        }
    });

    // Open the tab named in the URL (e.g. after recording a payment).
    // DOMContentLoaded (not load): the page's external resources can delay "load" by several seconds.
    const openBillingTab = function () {
        if (window.location.hash !== '#payments') return;
        const link = document.querySelector('a[data-bs-toggle="tab"][href="#payments"]');
        if (!link) return;
        window.bootstrap ? bootstrap.Tab.getOrCreateInstance(link).show() : link.click();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', openBillingTab); else openBillingTab();
})();
</script>
