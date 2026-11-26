@extends('pages.business.layouts.app')

@section('content')
<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Property expenses</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">Home</li>
                <li class="breadcrumb-item text-muted">{{ auth()->user()->business['business_name'] }}</li>
                <li class="breadcrumb-item text-dark">Property expenses</li>
            </ul>
        </div>
        <div class="d-flex d-lg-none align-items-center ms-n2 me-2">
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle">
                <span class="svg-icon svg-icon-1 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                    </svg>
                </span>
            </div>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl property-expenses-workspace" id="kt_content_container">

        <div class="expense-page-heading">
            <div>
                <span class="expense-eyebrow">PROPERTY FINANCES</span>
                <h2>Expense ledger</h2>
                <p>Review costs across your properties and units.</p>
            </div>
            <a class="btn btn-light expense-back-link" href="{{ route('complex.index') }}"><i class="fas fa-arrow-left me-2"></i>Property portfolio</a>
        </div>
        @unless($workflowAvailable)<div class="alert alert-warning">Approval, evidence and payment tracking are installed but require the expense workflow database migration. The existing ledger remains available; no old expense is assumed approved or paid.</div>@endunless

        {{-- ─── Filter Card ──────────────────────────────────────────────────── --}}
        <div class="card expense-filter-card mb-6">
            <div class="card-header d-flex flex-row justify-content-between align-items-center">
                <div><h3 class="card-title fw-bolder">Find expenses</h3><p>Choose filters, then load matching records.</p></div>
                <div class="card-toolbar gap-2">
                    @if(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'expense_management', 'export'))<a id="export_ledger" href="{{ route('business.expenses.export-ledger') }}" class="btn btn-light">Export filtered CSV</a>@endif
                    @if($expenseCapabilities['create'])
                    <a id="btn_add_expense" href="{{ route('business.expenses.create') }}" class="btn btn-light-info">
                        <i class="fas fa-plus me-1"></i> Record expense
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4 align-items-end">
                    <div class="col-md-4"><label class="form-label" for="filter_q">Description search</label><input id="filter_q" class="form-control" placeholder="Repair, supplier details or invoice note"></div>
                    <div class="col-md-4"><label class="form-label" for="filter_unit_q">Unit number</label><input id="filter_unit_q" class="form-control" placeholder="Residential or commercial unit"></div>
                    <div class="col-md-4"><label class="form-label" for="filter_category">Category</label><select id="filter_category" class="form-select"><option value="">All categories</option>@foreach($expenseTypes as $type)<option value="{{ $type->id }}">{{ $type->expense_type }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" for="filter_workflow_status">Workflow queue</label><select id="filter_workflow_status" class="form-select" @disabled(!$workflowAvailable)><option value="">All non-voided records</option>@foreach(['unreviewed', 'draft', 'pending', 'approved', 'returned', 'void'] as $state)<option value="{{ $state }}">{{ ucfirst($state) }}</option>@endforeach</select></div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Property</label>
                        <select id="filter_complex" class="form-select form-select-solid"
                            data-control="select2"
                            data-placeholder="All properties"
                            data-allow-clear="true">
                            <option value=""></option>
                            @foreach($complexes as $c)
                                <option value="{{ $c->id }}">{{ $c->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Scope</label>
                        <select id="filter_scope" class="form-select form-select-solid"
                            data-control="select2"
                            data-placeholder="All Scopes"
                            data-allow-clear="true"
                            data-hide-search="true">
                            <option value=""></option>
                            <option value="complex">Whole property</option>
                            <option value="residential_unit">Residential Unit</option>
                            <option value="commercial_unit">Commercial Unit</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Date From</label>
                        <input type="text" id="filter_date_from" class="form-control form-control-solid" placeholder="Select date" readonly />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Date To</label>
                        <input type="text" id="filter_date_to" class="form-control form-control-solid" placeholder="Select date" readonly />
                    </div>
                    <div class="col-md-3">
                        <button id="btn_load" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Load Expenses
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── Summary Stat Cards ──────────────────────────────────────────── --}}
        <div class="row g-4 mb-6 expense-metrics" id="summary_cards" style="display:none!important;">
            <div class="col-6 col-md-3">
                <div class="card card-flush h-100 expense-metric">
                    <div class="card-body py-5">
                        <div class="expense-metric-value" id="stat_total_amount">0.00</div>
                        <div class="expense-metric-label">Total by currency</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-flush h-100 expense-metric">
                    <div class="card-body py-5">
                        <div class="expense-metric-value" id="stat_complex_count">0</div>
                        <div class="expense-metric-label">Whole property</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-flush h-100 expense-metric">
                    <div class="card-body py-5">
                        <div class="expense-metric-value" id="stat_residential_count">0</div>
                        <div class="expense-metric-label">Residential units</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-flush h-100 expense-metric">
                    <div class="card-body py-5">
                        <div class="expense-metric-value" id="stat_commercial_count">0</div>
                        <div class="expense-metric-label">Commercial units</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── Results Table ────────────────────────────────────────────────── --}}
        <div class="card expense-results-card" id="results_card" style="display:none;">
            <div class="card-header">
                <div><h3 class="card-title fw-bolder">Expense records</h3><p>Costs matching your selected filters</p></div>
                <div class="card-toolbar text-muted fs-7" id="pagination_info"></div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-gray-200 align-middle gs-5 gy-3">
                        <thead class="text-muted text-uppercase fs-8">
                            <tr>
                                <th>Property</th>
                                <th>Expense</th>
                                <th>Scope &amp; unit</th>
                                <th class="text-end">Amount</th>
                                <th>Date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="expense_tbody" data-can-edit="{{ $expenseCapabilities['edit'] ? '1' : '0' }}" data-can-delete="{{ $expenseCapabilities['delete'] ? '1' : '0' }}">
                            {{-- populated via JS --}}
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- Pagination --}}
            <div class="card-footer d-flex justify-content-between align-items-center py-3" id="pagination_row" style="display:none!important;">
                <button id="btn_prev" class="btn btn-sm btn-light" disabled>
                    <i class="fas fa-chevron-left me-1"></i> Prev
                </button>
                <span id="pagination_label" class="text-muted fs-7"></span>
                <button id="btn_next" class="btn btn-sm btn-light" disabled>
                    Next <i class="fas fa-chevron-right ms-1"></i>
                </button>
            </div>
        </div>

    </div>
</div>

<style>
.scope-card:has(input[type=radio]:checked) { border-color: #087d84 !important; background-color: #f0f7f6; }
.scope-card:hover { border-color: #9bb6b9 !important; background-color: #f7fafb; }
</style>

{{-- ─── Add / Edit Modal ────────────────────────────────────────────────────── --}}
<div class="modal fade" id="expense_modal" tabindex="-1" aria-labelledby="expenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-4">
                <h5 class="modal-title d-flex align-items-center gap-2" id="expenseModalLabel">
                    <i class="fas fa-receipt text-primary"></i>
                    <span>Add Expense</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-5">
                <input type="hidden" id="expense_id" value="" />

                {{-- ── Location ──────────────────────────────────────────── --}}
                <div class="d-flex align-items-center gap-2 mb-4">
                    <i class="fas fa-building text-primary fs-6"></i>
                    <span class="fw-bold text-gray-700">Location</span>
                    <div class="flex-grow-1 border-bottom border-dashed border-gray-300 ms-2"></div>
                </div>

                <div class="row g-4 mb-6">
                    {{-- Complex --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold required">Property</label>
                        <select name="complex" id="modal_complex"
                            class="form-control form-control-solid"
                            data-control="select2"
                            data-placeholder="Search or select a complex…"
                            data-allow-clear="true"
                            required>
                            <option value=""></option>
                            @foreach($complexes as $c)
                                <option value="{{ $c->id }}">{{ $c->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Scope radios --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold required">Scope of Expense</label>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="d-flex align-items-center gap-3 border border-dashed rounded-2 p-3 cursor-pointer scope-card h-100" for="scope_complex">
                                    <input type="radio" name="modal_scope" id="scope_complex" value="complex" class="form-check-input flex-shrink-0" checked />
                                    <span>
                                        <span class="fw-bold d-block fs-7"><i class="fas fa-building text-primary me-1"></i> Whole property</span>
                                        <span class="text-muted fs-8">Applies to the entire building</span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="d-flex align-items-center gap-3 border border-dashed rounded-2 p-3 cursor-pointer scope-card h-100" for="scope_residential">
                                    <input type="radio" name="modal_scope" id="scope_residential" value="residential_unit" class="form-check-input flex-shrink-0" />
                                    <span>
                                        <span class="fw-bold d-block fs-7"><i class="fas fa-home text-success me-1"></i> Residential Unit</span>
                                        <span class="text-muted fs-8">Applies to a specific home</span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="d-flex align-items-center gap-3 border border-dashed rounded-2 p-3 cursor-pointer scope-card h-100" for="scope_commercial">
                                    <input type="radio" name="modal_scope" id="scope_commercial" value="commercial_unit" class="form-check-input flex-shrink-0" />
                                    <span>
                                        <span class="fw-bold d-block fs-7"><i class="fas fa-store text-warning me-1"></i> Commercial Unit</span>
                                        <span class="text-muted fs-8">Applies to a shop or office</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Unit selector (hidden unless residential/commercial scope) --}}
                    <div class="col-12" id="unit_row" style="display:none;">
                        <label class="form-label fw-semibold required" id="unit_label">Unit</label>
                        <select id="modal_unit" class="form-control form-control-solid">
                            <option value="">— Select unit —</option>
                        </select>
                        <div class="text-muted fs-8 mt-1" id="unit_hint">Units load after selecting a complex. You may select multiple.</div>
                    </div>
                </div>

                {{-- ── Details ─────────────────────────────────────────── --}}
                <div class="d-flex align-items-center gap-2 mb-4">
                    <i class="fas fa-clipboard-list text-info fs-6"></i>
                    <span class="fw-bold text-gray-700">Details</span>
                    <div class="flex-grow-1 border-bottom border-dashed border-gray-300 ms-2"></div>
                </div>

                <div class="row g-4 mb-6">
                    {{-- Category --}}
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Category</label>
                        <select id="modal_type" class="form-control form-control-solid"
                            data-control="select2"
                            data-placeholder="— Uncategorised —"
                            data-allow-clear="true">
                            <option value=""></option>
                            @foreach($expenseTypes as $et)
                                <option value="{{ $et->id }}">{{ $et->expense_type }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Description --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold required">Description</label>
                        <textarea id="modal_description" class="form-control form-control-solid" rows="3" maxlength="1000" placeholder="e.g. Roof repair, water bill, generator maintenance…"></textarea>
                    </div>
                </div>

                <div id="allocation_row" class="mb-6">
                    <label class="form-label fw-semibold" for="modal_allocation">How should this amount be allocated?</label>
                    <select id="modal_allocation" class="form-select"><option value="split_total">One total — split equally across selected units</option><option value="per_unit">Amount per unit — repeat for each selected unit</option></select>
                    <p class="text-muted fs-7 mt-2">For a whole-property expense or one unit, the amount is recorded once. Review the combined total before saving.</p>
                </div>
                {{-- ── Amount ──────────────────────────────────────────── --}}
                <div class="d-flex align-items-center gap-2 mb-4">
                    <i class="fas fa-coins text-warning fs-6"></i>
                    <span class="fw-bold text-gray-700">Amount</span>
                    <div class="flex-grow-1 border-bottom border-dashed border-gray-300 ms-2"></div>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold required">Amount</label>
                        <input type="number" id="modal_amount" class="form-control form-control-solid" min="0" step="0.01" placeholder="0.00" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold required">Currency</label>
                        <select id="modal_currency" class="form-control form-control-solid"
                            data-control="select2"
                            data-hide-search="true">
                            <option value="KES">KES — Kenyan Shilling</option>
                            <option value="USD">USD — US Dollar</option>
                            <option value="EUR">EUR — Euro</option>
                            <option value="GBP">GBP — British Pound</option>
                            <option value="ZAR">ZAR — South African Rand</option>
                            <option value="UGX">UGX — Ugandan Shilling</option>
                            <option value="TZS">TZS — Tanzanian Shilling</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold required">Date</label>
                        <input type="text" id="modal_date" class="form-control form-control-solid" placeholder="Select date" readonly />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn_save_expense">
                    <span class="indicator-label"><i class="fas fa-save me-1"></i> Save</span>
                    <span class="indicator-progress" style="display:none;">Saving… <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </div>
        </div>
    </div>
</div>

@endsection
