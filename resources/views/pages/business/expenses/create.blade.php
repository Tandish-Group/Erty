@extends('pages.business.layouts.app')

@section('content')
<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Record expense</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">Home</li>
                <li class="breadcrumb-item"><a href="{{ route('business.expenses.index') }}">Property expenses</a></li>
                <li class="breadcrumb-item text-dark">New expense</li>
            </ul>
        </div>
        <div class="d-flex d-lg-none align-items-center ms-n2 me-2">
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle"><span class="svg-icon svg-icon-1 mt-1"><i class="fas fa-bars"></i></span></div>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl property-expenses-workspace expense-create-workspace" id="kt_content_container">
        <div class="expense-page-heading">
            <div>
                <span class="expense-eyebrow">PROPERTY FINANCES</span>
                <h2>Record an expense</h2>
                <p>Enter the cost details and choose where it should be recorded.</p>
            </div>
            <a class="btn btn-light expense-back-link" href="{{ route('business.expenses.index') }}"><i class="fas fa-arrow-left me-2"></i>Back to expense ledger</a>
        </div>

        <form id="expense_create_form" class="expense-create-form" data-ledger-url="{{ route('business.expenses.index', $selectedComplexId ? ['complex_id' => encrypt($selectedComplexId)] : []) }}" novalidate>
            @csrf
            <section class="expense-form-section">
                <div class="expense-form-section-heading"><span>01</span><div><h3>Where did the expense occur?</h3><p>Select the property and the units it applies to.</p></div></div>
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label required" for="create_complex">Property</label>
                        <select id="create_complex" class="form-select" required>
                            <option value="">Select a property</option>
                            @foreach($complexes as $complex)
                                <option value="{{ $complex->id }}" @selected((string) $selectedComplexId === (string) $complex->id)>{{ $complex->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label required">Expense scope</label>
                        <div class="expense-scope-options">
                            <label class="expense-scope-option is-selected" for="create_scope_complex">
                                <input type="radio" name="create_scope" id="create_scope_complex" value="complex" checked>
                                <span class="expense-scope-option-icon"><i class="fas fa-building"></i></span>
                                <span><strong>Whole property</strong><small>Shared building or estate costs</small></span>
                            </label>
                            <label class="expense-scope-option" for="create_scope_residential">
                                <input type="radio" name="create_scope" id="create_scope_residential" value="residential_unit">
                                <span class="expense-scope-option-icon"><i class="fas fa-house"></i></span>
                                <span><strong>Residential unit</strong><small>One or more homes</small></span>
                            </label>
                            <label class="expense-scope-option" for="create_scope_commercial">
                                <input type="radio" name="create_scope" id="create_scope_commercial" value="commercial_unit">
                                <span class="expense-scope-option-icon"><i class="fas fa-store"></i></span>
                                <span><strong>Commercial unit</strong><small>One or more shops or offices</small></span>
                            </label>
                        </div>
                    </div>
                    <div class="col-12" id="create_unit_wrap" hidden>
                        <label class="form-label required" id="create_unit_label" for="create_units">Units</label>
                        <select id="create_units" class="form-select" multiple disabled></select>
                        <small class="form-hint">Select every unit this expense applies to.</small>
                    </div>
                    <div class="col-md-6" id="create_allocation_wrap" hidden>
                        <label class="form-label" for="create_allocation">Amount allocation</label>
                        <select id="create_allocation" class="form-select">
                            <option value="split_total">Split the total equally across selected units</option>
                            <option value="per_unit">Apply the amount to each selected unit</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end" id="create_allocation_hint_wrap" hidden>
                        <p class="form-hint expense-allocation-hint">The entered amount will be split across the selected units. You can review the total before saving.</p>
                    </div>
                </div>
            </section>

            <section class="expense-form-section">
                <div class="expense-form-section-heading"><span>02</span><div><h3>Expense details</h3><p>Describe the cost and enter the amount paid or owed.</p></div></div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="create_category">Category</label>
                        <select id="create_category" class="form-select">
                            <option value="">Uncategorised</option>
                            @foreach($expenseTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->expense_type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="create_description">Description</label>
                        <textarea id="create_description" class="form-control" rows="3" maxlength="1000" placeholder="For example, roof repair or water bill" required></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="create_amount">Amount</label>
                        <input type="number" id="create_amount" class="form-control" min="0.01" max="999999999" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="create_currency">Currency</label>
                        <select id="create_currency" class="form-select" required>
                            <option value="KES">KES — Kenyan Shilling</option><option value="USD">USD — US Dollar</option>
                            <option value="EUR">EUR — Euro</option><option value="GBP">GBP — British Pound</option>
                            <option value="ZAR">ZAR — South African Rand</option><option value="UGX">UGX — Ugandan Shilling</option>
                            <option value="TZS">TZS — Tanzanian Shilling</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="create_date">Expense date</label>
                        <input type="text" id="create_date" class="form-control" placeholder="Select a date" autocomplete="off" required>
                    </div>
                </div>
            </section>

            <div class="expense-create-footer">
                <p><i class="fas fa-circle-info me-2"></i>This records an expense. Approval and payment are handled separately.</p>
                <div>
                    <a href="{{ route('business.expenses.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" id="create_save_expense" class="btn btn-primary">
                        <span class="indicator-label"><i class="fas fa-check me-2"></i>Review and save</span>
                        <span class="indicator-progress" hidden>Saving… <span class="spinner-border spinner-border-sm ms-2"></span></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
