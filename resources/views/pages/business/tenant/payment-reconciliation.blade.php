@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Payment reconciliation'])

<div class="content d-flex flex-column flex-column-fluid tenant-workspace tenant-reconciliation" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">Tenant workspace</span>
                <h1>Payment reconciliation</h1>
                <p>Attach proof of payment to the payments you recorded: pick a receipt, pick the payment it belongs to, then match them.</p>
            </div>
            <div class="tenant-heading-actions"><a href="/tenant_management" class="btn btn-light">← Back to tenant dashboard</a></div>
        </header>

        <div class="tenant-metrics recon-metrics" aria-label="Reconciliation totals">
            <div><span>Payments without proof</span><strong id="stat-unmatched-payments">{{ number_format($unmatchedPaymentsCount) }}</strong><small>Recorded payments with no receipt attached</small></div>
            <div><span>Proof files waiting</span><strong id="stat-staged-receipts">{{ $filesHidden ? '—' : number_format($stagedReceiptsCount) }}</strong><small>Uploaded receipts not yet matched</small></div>
            <div><span>Matched in the last 30 days</span><strong id="stat-recent-matches">{{ number_format($recentMatchesCount) }}</strong><small>Receipts attached to payments</small></div>
        </div>

        @if(can_open('/tenant_management/bulk-upload/template/payments') && !app(\App\Services\Access\PropertyAccess::class)->restricted())
            <div class="recon-import">
                <i class="fas fa-file-csv" aria-hidden="true"></i>
                <div>
                    <strong>Payments not recorded yet?</strong>
                    <span>Record many at once from a spreadsheet, such as a bank or M-Pesa statement: one row per payment with the tenancy reference, date, amount and method. Each payment settles the tenant's oldest unpaid invoice first. Then attach the receipts here.</span>
                </div>
                <div class="recon-import-actions">
                    <a href="/tenant_management/bulk-upload/template/payments" class="btn btn-sm btn-light"><i class="fas fa-download me-1" aria-hidden="true"></i>Example file (CSV)</a>
                    <a href="/tenant_management?import=payments" class="btn btn-sm btn-primary"><i class="fas fa-upload me-1" aria-hidden="true"></i>Import payments</a>
                </div>
            </div>
        @endif

        <div class="recon-workspace">
            {{-- Step 1: the proof of payment --}}
            <section class="tenant-panel tenant-panel--padded recon-files" aria-labelledby="recon-files-heading">
                <div class="recon-step-heading">
                    <span class="recon-step" aria-hidden="true">1</span>
                    <div><h2 id="recon-files-heading">Proof of payment</h2><p>Upload receipts, then choose one.</p></div>
                </div>

                @if($filesHidden)
                    <div class="recon-note">
                        Uploaded receipts are not linked to a property until they are matched, so they are handled by team members who work with every property.
                    </div>
                @else
                    <div id="receipt-dropzone" class="recon-dropzone" role="button" tabindex="0" aria-describedby="recon-dropzone-hint">
                        <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                        <span><strong>Upload receipts</strong><small id="recon-dropzone-hint">Drop files here or click to browse · JPG, PNG or PDF up to 5 MB</small></span>
                        <input type="file" id="receipt-file-input" accept=".jpg,.jpeg,.png,.pdf" multiple hidden>
                    </div>
                    <div id="upload-progress-area" class="recon-progress d-none" aria-live="polite">
                        <div class="d-flex justify-content-between mb-1"><span id="upload-progress-label">Uploading…</span><span id="upload-progress-pct">0%</span></div>
                        <div class="progress h-6px"><div class="progress-bar" id="upload-progress-bar" style="width:0%"></div></div>
                    </div>

                    <div class="recon-list-heading"><span>Waiting to be matched</span><span class="tenant-badge" id="staged-count-badge">{{ $stagedReceiptsCount }}</span></div>
                    <ul id="staged-receipts-list" class="recon-files-list" aria-label="Proof files waiting to be matched">
                        <li class="recon-loading"><span class="spinner-border spinner-border-sm" role="status"></span> Loading files…</li>
                    </ul>
                @endif
            </section>

            {{-- Step 2: the payment it belongs to --}}
            <section class="tenant-panel tenant-panel--padded recon-payments" aria-labelledby="recon-payments-heading">
                <div class="recon-step-heading">
                    <span class="recon-step" aria-hidden="true">2</span>
                    <div><h2 id="recon-payments-heading">Payment it belongs to</h2><p>Payments recorded without proof, newest first.</p></div>
                </div>

                <form class="recon-filters" id="recon-filters" role="search">
                    <div>
                        <label for="filter-tenant-search">Tenant or reference</label>
                        <input type="search" id="filter-tenant-search" class="form-control" placeholder="Name, tenancy ref or transaction ref" autocomplete="off">
                    </div>
                    <div>
                        <label for="filter-date-from">Paid from</label>
                        <input type="date" id="filter-date-from" class="form-control">
                    </div>
                    <div>
                        <label for="filter-date-to">Paid to</label>
                        <input type="date" id="filter-date-to" class="form-control">
                    </div>
                    <div class="recon-filter-actions">
                        <button type="submit" class="btn btn-light-primary">Filter</button>
                        <button type="button" class="btn btn-link d-none" id="btn-reset-filters">Reset</button>
                    </div>
                </form>

                <div class="tenant-table-scroll" role="region" aria-label="Payments without proof" tabindex="0">
                    <table class="table ws-table" id="unmatched-payments-table">
                        <thead>
                            <tr><th class="recon-col-pick"><span class="visually-hidden">Selected</span></th><th>Paid on</th><th>Tenant</th><th>Invoice</th><th>Method &amp; reference</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody id="unmatched-payments-tbody">
                            <tr><td colspan="6" class="recon-loading"><span class="spinner-border spinner-border-sm" role="status"></span> Loading payments…</td></tr>
                        </tbody>
                    </table>
                </div>
                <nav class="recon-pager d-none" id="payments-pager" aria-label="Payment pages">
                    <span id="payments-range"></span>
                    <div>
                        <button type="button" class="btn btn-sm btn-light" id="payments-prev">Previous</button>
                        <button type="button" class="btn btn-sm btn-light" id="payments-next">Next</button>
                    </div>
                </nav>
            </section>
        </div>

        {{-- Selection bar: stays in view while choosing --}}
        <div id="reconciliation-action-bar" class="recon-match-bar" role="region" aria-label="Match selection" aria-live="polite" hidden>
            <div class="recon-match-pair">
                <div><span>Proof</span><strong id="selected-receipt-name">Choose a receipt</strong></div>
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                <div><span>Payment</span><strong id="selected-payment-ref">Choose a payment</strong></div>
            </div>
            <div class="recon-match-actions">
                <button type="button" id="btn-clear-selection" class="btn btn-sm btn-light">Clear</button>
                <button type="button" id="btn-match" class="btn btn-sm btn-primary" disabled><i class="fas fa-link me-1" aria-hidden="true"></i>Match</button>
            </div>
        </div>

        <section class="tenant-panel recon-recent">
            <button class="recon-recent-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#recent-matches-body" aria-expanded="false" aria-controls="recent-matches-body">
                <span><strong>Recent matches</strong><small>Receipts attached in the last 30 days. Undo a match to send the receipt back to the waiting list.</small></span>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="collapse" id="recent-matches-body">
                <div class="tenant-table-scroll">
                    <table class="table ws-table" id="recent-matches-table">
                        <thead><tr><th>Matched</th><th>Receipt</th><th>Tenancy</th><th>Paid on</th><th>Method &amp; reference</th><th class="text-end">Amount</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody id="recent-matches-tbody"><tr><td colspan="7" class="recon-loading"><span class="spinner-border spinner-border-sm" role="status"></span> Loading recent matches…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
