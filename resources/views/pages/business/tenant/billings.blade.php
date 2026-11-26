@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Tenancy billings'])

<div class="content d-flex flex-column flex-column-fluid tenant-workspace tenant-billings" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div><span class="tenant-eyebrow">Tenant workspace</span><h1>Tenancy billings</h1><p>What each tenant owes now, what is overdue, and credit and deposits held. Collection reports are under Reports.</p></div>
            <div class="tenant-heading-actions"><a href="/tenant_management" class="btn btn-light">← Back to tenant dashboard</a>@businessCan('reports', 'financial')<a href="{{ route('reports.show', 'rent-roll') }}" class="btn btn-light-primary"><i class="fas fa-chart-bar me-1" aria-hidden="true"></i>Rent roll &amp; arrears report</a>@endbusinessCan@businessCan('agent_management', 'view')<a href="{{ route('tenant_management.agent_commissions') }}" class="btn btn-light-primary"><i class="fas fa-user-tie me-1" aria-hidden="true"></i>Agent commissions</a>@endbusinessCan</div>
        </header>

        <section class="tenant-panel tenant-billing-workspace">
            <div class="tenant-records-toolbar">
                <nav class="nav tenant-tabs" role="tablist" aria-label="Billing views">
                    <a class="nav-link active" id="tab_billing_lookup_link" data-bs-toggle="tab" href="#tab_billing_lookup" role="tab" aria-controls="tab_billing_lookup" aria-selected="true">Accounts</a>
                </nav>
            </div>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_billing_lookup" role="tabpanel" aria-labelledby="tab_billing_lookup_link">
                    <div class="tenant-section-heading"><div><h2>Tenant accounts</h2><p>What each tenant owes now, what is overdue, credit held and deposit held. Open a tenancy to record a payment, refund or charge.</p></div></div>
                    @php
                        $acc = collect($accounts ?? []);
                        $cur = $acc->first()['currency'] ?? default_currency();
                        $m = fn ($v) => money($v, $cur);
                    @endphp
                    <div class="tenant-metrics tenant-billing-summary mb-5" data-accounts-summary>
                        <div><span>Owed now</span><strong>{{ $m($acc->sum(fn ($r) => max(0, $r['balance']))) }}</strong><small>Across {{ $acc->where('balance', '>', 0)->count() }} tenants</small></div>
                        <div><span>Overdue</span><strong class="{{ $acc->sum('overdue') > 0 ? 'text-danger' : '' }}">{{ $m($acc->sum('overdue')) }}</strong><small>{{ $acc->where('overdue', '>', 0)->count() }} tenants in arrears</small></div>
                        <div><span>Credit held</span><strong>{{ $m($acc->sum('credit')) }}</strong><small>Prepaid or overpaid</small></div>
                        <div><span>Deposits held</span><strong>{{ $m($acc->sum('deposit_held')) }}</strong><small>Refundable</small></div>
                    </div>
                    @if($acc->isEmpty())
                        <div class="tenant-empty"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i><h3>No tenant accounts yet</h3><p>Accounts appear once a tenant is onboarded.</p></div>
                    @else
                    <div class="tenant-table-scroll" role="region" aria-label="Tenant accounts" tabindex="0">
                        <table class="tenant-table" data-accounts-table>
                            <thead><tr><th>Tenant</th><th>Property · unit</th><th class="text-end">Balance</th><th class="text-end">Overdue</th><th class="text-end">Credit</th><th class="text-end">Deposit held</th><th class="text-end">Awaiting clearance</th><th>Follow-up</th><th></th></tr></thead>
                            <tbody>
                            @foreach($acc as $row)
                                <tr data-account-row="{{ $row['tenancy_ref'] }}">
                                    <td><strong>{{ $row['tenant_name'] }}</strong><div class="text-muted fs-8">{{ $row['tenancy_ref'] }}{{ $row['status'] !== 'Active' ? ' · '.$row['status'] : '' }}</div></td>
                                    <td>{{ $row['property'] }}</td>
                                    <td class="text-end fw-bold">{{ $row['balance'] < 0 ? 'Credit '.$m(abs($row['balance'])) : $m($row['balance']) }}</td>
                                    <td class="text-end {{ $row['overdue'] > 0 ? 'text-danger fw-bold' : '' }}">{{ $m($row['overdue']) }}</td>
                                    <td class="text-end">{{ $m($row['credit']) }}</td>
                                    <td class="text-end">{{ $m($row['deposit_held']) }}@if($row['deposit_required'] > $row['deposit_held'] + 0.01 && $row['status'] === 'Active')<div class="text-danger fs-8">{{ $m($row['deposit_required'] - $row['deposit_held']) }} to pay</div>@endif</td>
                                    <td class="text-end">{{ $row['pending_payments'] > 0 ? $m($row['pending_payments']) : '—' }}</td>
                                    <td class="fs-8">@if($row['promise'] ?? null)<span class="{{ $row['promise']['passed'] ? 'text-danger fw-bold' : 'text-primary' }}">Promised {{ $m($row['promise']['amount']) }} by {{ $row['promise']['date'] }}{{ $row['promise']['passed'] ? ' (passed)' : '' }}</span><br>@endif{{ $row['last_follow_up'] ?? ($row['overdue'] > 0 ? 'None yet' : '—') }}</td>
                                    <td class="text-end"><a class="btn btn-sm btn-light-primary" href="/tenant_management/tenancy/{{ urlencode($row['tenancy_ref']) }}/view#payments">Open</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

            </div>
        </section>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="payment_details_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Payment Details</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <i class="fas fa-times"></i>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="payment_details_content">
                    <!-- Payment details will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

