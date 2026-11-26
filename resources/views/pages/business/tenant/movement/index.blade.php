@extends('pages.business.layouts.app')

@section('content')

@include('pages.business.tenant.partials.header', ['title' => 'Unit transfers'])

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid tenant-workspace tenant-movement-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div><span class="tenant-eyebrow">Tenant workspace</span><h1>Unit transfers</h1><p>Review requests and move tenants between units.</p></div>
            <div class="tenant-heading-actions"><a href="/tenant_management" class="btn btn-light">← Back to tenant dashboard</a>@businessCan('tenant_management', 'transfers')<a href="/tenant_management/movement/create" class="btn btn-primary">＋ Initiate transfer</a>@endbusinessCan</div>
        </header>

        <div class="tenant-metrics mb-8" aria-label="Transfer status totals">
            <div id="stat_pending_card" data-status="pending" role="button" tabindex="0" aria-pressed="false" aria-label="Filter by pending transfers"><span>Pending transfers</span><strong id="stat_pending">—</strong><small>Awaiting review</small></div>
            <div id="stat_approved_card" data-status="approved" role="button" tabindex="0" aria-pressed="false" aria-label="Filter by approved transfers"><span>Approved</span><strong id="stat_approved">—</strong><small>Approved requests</small></div>
            <div id="stat_completed_card" data-status="completed" role="button" tabindex="0" aria-pressed="false" aria-label="Filter by completed transfers"><span>Completed</span><strong id="stat_completed">—</strong><small>Moves completed</small></div>
            <div id="stat_cancelled_card" data-status="cancelled" role="button" tabindex="0" aria-pressed="false" aria-label="Filter by cancelled transfers"><span>Cancelled</span><strong id="stat_cancelled">—</strong><small>Cancelled requests</small></div>
        </div>

        <section class="tenant-panel">
            <div class="tenant-records-toolbar movement-records-toolbar">
                <div class="tenant-section-heading"><div><h2>Transfer requests</h2><p>Track each move from request through completion.</p></div></div>
                <div class="movement-filter-controls">
                    <label class="visually-hidden" for="movements_search">Search by reference or tenant</label>
                    <input type="search" id="movements_search" class="form-control" placeholder="Search reference or tenant">
                    <label class="visually-hidden" for="movements_status_filter">Filter by status</label>
                    <select id="movements_status_filter" class="form-select">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <p class="tenant-feedback" data-movement-feedback role="status" hidden></p>
            <div class="tenant-table-scroll" role="region" aria-label="Unit transfer requests" tabindex="0">
                <table class="tenant-table" id="kt_table_movements">
                    <thead><tr><th>Reference</th><th>Tenant</th><th>From unit</th><th aria-label="Moves to"></th><th>To unit</th><th>Transfer date</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="movements_table_body">
                        <tr><td colspan="9" class="text-center text-muted py-10"><span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>Loading transfers…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap pt-4" id="movements_pagination_wrapper" style="display:none;">
                <div class="text-muted fs-7" id="movements_showing_text"></div>
                <div id="movements_pagination"></div>
            </div>
        </section>
    </div>
</div>
<!--end::Content-->

<!--begin::Cancel Modal-->
<div class="modal fade" id="cancelMovementModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Cancel Transfer</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-5">Are you sure you want to cancel this transfer? This cannot be undone.</p>
                <input type="hidden" id="cancel_movement_ref">
                <div class="mb-3">
                    <label class="form-label">Reason (optional)</label>
                    <textarea id="cancel_reason" class="form-control" rows="3" placeholder="Reason for cancellation..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Keep Transfer</button>
                <button class="btn btn-danger" id="confirm_cancel_btn">Cancel Transfer</button>
            </div>
        </div>
    </div>
</div>
<!--end::Cancel Modal-->

@endsection
