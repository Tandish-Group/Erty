@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Review imports'])
@php
    $methods = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'check' => 'Cheque', 'card' => 'Card', 'other' => 'Other'];
    $noticeTypes = \App\Services\Tenancy\NoticeImport::TYPES;
    $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('j M Y') : '—';
    $empty = $tenants->isEmpty() && $payments->isEmpty() && $notices->isEmpty();
    $sections = [
        ['key' => 'tenant', 'rows' => $tenants, 'title' => 'Tenants and tenancies'],
        ['key' => 'payment', 'rows' => $payments, 'title' => 'Payments'],
        ['key' => 'notice', 'rows' => $notices, 'title' => 'Notices'],
    ];
@endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace imports-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow"><a href="/tenant_management">Tenants</a> · Import data</span>
                <h1>Review imports</h1>
                <p>Rows from your uploaded files wait here until you import them. Each row is checked the way it will be imported; rows with a problem say what to fix and cannot be selected. Nothing is created until you choose <strong>Import selected</strong>.</p>
            </div>
            <div class="tenant-heading-actions">
                @businessCan('tenant_management', 'import')<a href="/tenant_management?import=tenants" class="btn btn-primary"><i class="fas fa-upload me-1" aria-hidden="true"></i>Upload a file</a>@endbusinessCan
            </div>
        </header>

        @if($empty)
            <section class="tenant-panel">
                <div class="tenant-empty"><i class="fas fa-file-import" aria-hidden="true"></i><h3>Nothing waiting for review</h3>
                    <p>Upload a file of tenants and their tenancies, payments received or notices given, then check the rows here before importing them. Each kind of file has an example to download in the upload window.</p>
                    <a href="/tenant_management?import=tenants" class="btn btn-primary">Upload a file</a></div>
            </section>
        @else
            <nav class="docs-tabs mb-5" aria-label="Kinds of import">
                @foreach($sections as $s)
                    @if($s['rows']->isNotEmpty())
                        <a href="#import-{{ $s['key'] }}" class="docs-tab"><span>{{ $s['title'] }}</span><em>{{ $s['rows']->count() }}</em></a>
                    @endif
                @endforeach
            </nav>
        @endif

        {{-- Tenants and tenancies (App\Services\Tenancy\TenantImport) --}}
        @if($tenants->isNotEmpty())
            @php $ready = $tenants->filter(fn ($r) => !$r->problems)->count(); @endphp
            <section class="tenant-panel tenant-panel--padded mb-6" id="import-tenant" aria-labelledby="import-tenant-title">
                <div class="imports-head">
                    <h2 id="import-tenant-title" class="report-section-title">Tenants and tenancies</h2>
                    <span class="badge badge-light-success">{{ $ready }} ready</span>
                    @if($tenants->count() - $ready)<span class="badge badge-light-danger">{{ $tenants->count() - $ready }} cannot be imported</span>@endif
                </div>
                <p class="report-section-desc">Each row creates the tenant (or uses an existing tenant with the same e-mail or ID number) and their tenancy on the unit, with a lease. Rent periods before today are not billed: the tenancy is paid up to the end of the current period, the balance owed becomes one opening invoice (or credit), and the deposit held is recorded as brought forward. Imported tenants are not e-mailed.</p>
                <div class="tenant-table-scroll" role="region" aria-label="Tenants to import" tabindex="0">
                    <table class="tenant-table imports-table" id="tenant-mapping-table">
                        <thead><tr>
                            <th><input type="checkbox" id="tenant-bulk-select-all" aria-label="Select all tenants that can be imported"></th>
                            <th>Row</th><th>Tenant</th><th>Property and unit</th><th class="text-end">Rent</th><th>Started</th><th class="text-end">Deposit held</th><th class="text-end">Owed today</th><th>Check</th>
                        </tr></thead>
                        <tbody>
                        @foreach($tenants as $r)
                            @php $t = $r->tenancy_row; @endphp
                            <tr>
                                <td><input type="checkbox" class="row-select" data-id="{{ $r->row->id }}" data-batch-id="{{ $r->row->batch_id }}" @if($r->problems) disabled title="This row cannot be imported" @endif aria-label="Select row {{ $r->row->row_number }}"></td>
                                <td>{{ $r->row->row_number }}</td>
                                <td>{{ $r->name ?: '—' }}<small class="d-block text-muted">{{ $r->existing ? 'Existing tenant ' . $r->existing->tenant_ref : 'New ' . strtolower($r->type) . ' tenant' }}</small></td>
                                <td>{{ $r->complex?->title ?? ($t?->property ?: '—') }}<small class="d-block text-muted">{{ $r->unit ? ($r->commercial ? 'Commercial ' . $r->unit->unit_number . ($t?->occupied_sq_area ? ', ' . number_format((float) $t->occupied_sq_area) . ' sq ft' : '') : 'Unit ' . $r->unit->unit_number) : ($t?->unit_number ? 'Unit ' . $t->unit_number : '') }}</small></td>
                                <td class="text-end text-nowrap">{{ $t && (float) $t->monthly_payment > 0 ? money($t->monthly_payment, $r->currency) : '—' }}<small class="d-block text-muted">a month, billed {{ strtolower($r->frequency) }}</small></td>
                                <td class="text-nowrap">{{ $date($t?->start_date) }}@if($t?->end_date)<small class="d-block text-muted">to {{ $date($t->end_date) }}</small>@endif</td>
                                <td class="text-end text-nowrap">{{ money($r->deposit_held, $r->currency) }}@if($t && (float) $t->security_deposit !== (float) $r->deposit_held)<small class="d-block text-muted">of {{ money($t->security_deposit, $r->currency) }}</small>@endif</td>
                                <td class="text-end text-nowrap">{{ $t && (float) $t->balance_owed !== 0.0 ? ((float) $t->balance_owed < 0 ? money(abs($t->balance_owed), $r->currency) . ' credit' : money($t->balance_owed, $r->currency)) : 'Nothing' }}</td>
                                <td>
                                    @if($r->problems)
                                        <span class="badge badge-light-danger">Cannot import</span><small class="d-block text-danger mt-1">{{ ucfirst(implode('; ', $r->problems)) }}.</small>
                                    @else
                                        <span class="badge badge-light-success">Ready</span>
                                        <small class="d-block text-muted mt-1">{{ $r->billing['paid_up_to'] ? 'Rent billed from ' . $date($r->billing['first_bill']) : 'First rent billed for ' . $date($r->billing['first_bill']) }}</small>
                                    @endif
                                    @foreach($r->notes as $note)<small class="d-block text-muted">{{ ucfirst($note) }}.</small>@endforeach
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('pages.business.tenant.partials.import-actions', ['type' => 'tenant', 'rows' => $tenants, 'formId' => 'bulk-action-form', 'noun' => 'tenancies'])
            </section>
        @endif

        {{-- Payments (App\Services\Rent\PaymentImport) --}}
        @if($payments->isNotEmpty())
            @php $ready = $payments->filter(fn ($p) => !$p->problems)->count(); $waiting = $payments->filter(fn ($p) => !$p->problems && $p->pending)->count(); @endphp
            <section class="tenant-panel tenant-panel--padded mb-6" id="import-payment" aria-labelledby="import-payment-title">
                <div class="imports-head">
                    <h2 id="import-payment-title" class="report-section-title">Payments</h2>
                    <span class="badge badge-light-success">{{ $ready }} ready</span>
                    @if($waiting)<span class="badge badge-light-warning">{{ $waiting }} waiting to clear</span>@endif
                    @if($payments->count() - $ready)<span class="badge badge-light-danger">{{ $payments->count() - $ready }} cannot be imported</span>@endif
                </div>
                <p class="report-section-desc">Each payment settles the tenant's oldest unpaid invoice first (or the invoice named in the file), and anything extra stays as credit. Payments marked as not cleared are recorded as waiting and applied when you confirm them on the tenant's account.</p>
                <div class="tenant-table-scroll" role="region" aria-label="Payments to import" tabindex="0">
                    <table class="tenant-table imports-table" id="payment-mapping-table">
                        <thead><tr>
                            <th><input type="checkbox" id="payment-bulk-select-all" aria-label="Select all payments that can be imported"></th>
                            <th>Row</th><th>Tenant</th><th>Date</th><th class="text-end">Amount</th><th>Method and reference</th><th>Pays</th><th>Check</th>
                        </tr></thead>
                        <tbody>
                        @foreach($payments as $p)
                            @php $r = $p->row; $t = $p->tenancy; @endphp
                            <tr>
                                <td><input type="checkbox" class="row-select" data-id="{{ $r->id }}" data-batch-id="{{ $r->batch_id }}" @if($p->problems) disabled title="This row cannot be imported" @endif aria-label="Select row {{ $r->row_number }}"></td>
                                <td>{{ $r->row_number }}</td>
                                <td>@if($t){{ $t->tenantProfile?->display_name ?? 'Tenant' }}<small class="d-block text-muted">{{ $t->tenancy_ref }} · {{ $t->unit_number }}</small>@else<span class="text-muted">{{ $r->tenancy_ref ?: ($r->tenant_profile_ref ?: ($r->tenant_email ?: '—')) }}</span>@endif</td>
                                <td class="text-nowrap">{{ $date($r->payment_date) }}</td>
                                <td class="text-end text-nowrap">{{ (float) $r->amount_paid > 0 ? money($r->amount_paid, $t?->currency) : '—' }}</td>
                                <td>{{ $methods[$r->payment_method] ?? ($r->payment_method ?: '—') }}@if($r->transaction_reference)<small class="d-block text-muted">{{ $r->transaction_reference }}</small>@endif</td>
                                <td>{{ $p->invoice ? 'Invoice ' . $p->invoice->invoice_number . ' first' : 'Oldest unpaid first' }}</td>
                                <td>
                                    @if($p->problems)
                                        <span class="badge badge-light-danger">Cannot import</span><small class="d-block text-danger mt-1">{{ ucfirst(implode('; ', $p->problems)) }}.</small>
                                    @else
                                        <span class="badge badge-light-{{ $p->pending ? 'warning' : 'success' }}">{{ $p->pending ? 'Ready, waiting to clear' : 'Ready' }}</span>
                                    @endif
                                    @foreach($p->notes as $note)<small class="d-block text-muted mt-1">{{ ucfirst($note) }}.</small>@endforeach
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('pages.business.tenant.partials.import-actions', ['type' => 'payment', 'rows' => $payments, 'formId' => 'payment-bulk-action-form', 'noun' => 'payments'])
            </section>
        @endif

        {{-- Notices (App\Services\Tenancy\NoticeImport) --}}
        @if($notices->isNotEmpty())
            @php $ready = $notices->filter(fn ($n) => !$n->problems)->count(); @endphp
            <section class="tenant-panel tenant-panel--padded mb-6" id="import-notice" aria-labelledby="import-notice-title">
                <div class="imports-head">
                    <h2 id="import-notice-title" class="report-section-title">Notices</h2>
                    <span class="badge badge-light-success">{{ $ready }} ready</span>
                    @if($notices->count() - $ready)<span class="badge badge-light-danger">{{ $notices->count() - $ready }} cannot be imported</span>@endif
                </div>
                <p class="report-section-desc">Notices already given outside Erty, kept on the tenancy for the record. They are not sent to the tenant again. Give new notices from the tenancy page.</p>
                <div class="tenant-table-scroll" role="region" aria-label="Notices to import" tabindex="0">
                    <table class="tenant-table imports-table" id="notice-mapping-table">
                        <thead><tr>
                            <th><input type="checkbox" id="notice-bulk-select-all" aria-label="Select all notices that can be imported"></th>
                            <th>Row</th><th>Tenant</th><th>Notice</th><th>Given</th><th>Takes effect</th><th>Check</th>
                        </tr></thead>
                        <tbody>
                        @foreach($notices as $n)
                            @php $r = $n->row; $t = $n->tenancy; @endphp
                            <tr>
                                <td><input type="checkbox" class="row-select" data-id="{{ $r->id }}" data-batch-id="{{ $r->batch_id }}" @if($n->problems) disabled title="This row cannot be imported" @endif aria-label="Select row {{ $r->row_number }}"></td>
                                <td>{{ $r->row_number }}</td>
                                <td>@if($t){{ $t->tenantProfile?->display_name ?? 'Tenant' }}<small class="d-block text-muted">{{ $t->tenancy_ref }} · {{ $t->unit_number }}</small>@else<span class="text-muted">{{ $r->tenancy_ref ?: ($r->tenant_profile_ref ?: ($r->tenant_email ?: '—')) }}</span>@endif</td>
                                <td>{{ $n->type ? $noticeTypes[$n->type] : ($r->notice_type ?: '—') }}@if($r->notice_subject)<small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($r->notice_subject, 60) }}</small>@endif</td>
                                <td class="text-nowrap">{{ $date($r->notice_date) }}</td>
                                <td class="text-nowrap">{{ $date($r->effective_date) }}</td>
                                <td>
                                    @if($n->problems)
                                        <span class="badge badge-light-danger">Cannot import</span><small class="d-block text-danger mt-1">{{ ucfirst(implode('; ', $n->problems)) }}.</small>
                                    @else
                                        <span class="badge badge-light-success">Ready</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('pages.business.tenant.partials.import-actions', ['type' => 'notice', 'rows' => $notices, 'formId' => 'notice-bulk-action-form', 'noun' => 'notices'])
            </section>
        @endif
    </div>
</div>

<script>
    // Discard a whole uploaded file (its rows are only staged; nothing has been created from them).
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-discard-batch]');
        if (!button || !window.confirm('Discard every row from this upload? Nothing has been created from it yet.')) return;
        button.disabled = true;
        fetch('/tenant_management/bulk-upload/cancel', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ type: button.dataset.discardType, batch_id: button.dataset.discardBatch }),
        }).then(function (r) { return r.json(); }).then(function () { window.location.reload(); })
          .catch(function () { button.disabled = false; window.alert('The upload could not be discarded. Try again.'); });
    });
</script>
@endsection
