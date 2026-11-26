@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Data Confirmation</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">Home</li>
                <li class="breadcrumb-item text-muted">{{auth()->user()->business['business_name']}}</li>
                <li class="breadcrumb-item text-muted">Tenant Management</li>
                <li class="breadcrumb-item text-dark">Data Confirmation</li>
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
    <div class="container-xxl" id="kt_content_container">

        {{-- Add Tenant Table if data exists --}}
        @if(isset($tenantRows) && count($tenantRows) > 0)
        <div class="card mb-8">
            <div class="card-header">
                <h3 class="card-title">Tenant Data Preview</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive mb-4">
                    @php
                        $hiddenTenant = ['id','batch_id','user_id','row_number','updated_at','created_at', 'error_message','use_existing_tenant', 'tenant_ref'];
                        $preferredTenant = ['tenant_profile_ref','first_name','last_name','email','phone','tenancy_ref','room_number'];
                        $visibleTenant = [];
                        foreach ($preferredTenant as $p) {
                            if (in_array($p, $tenantHeaders) && !in_array($p, $hiddenTenant)) $visibleTenant[] = $p;
                        }
                        if (empty($visibleTenant)) {
                            foreach ($tenantHeaders as $h) {
                                if (!in_array($h, $hiddenTenant)) { $visibleTenant[] = $h; if (count($visibleTenant) >= 6) break; }
                            }
                        }
                    @endphp
                    
                    <table class="table table-rounded table-row-bordered border gy-7 gs-7" id="tenant-mapping-table">
                        <thead class="fs-7">
                            <tr class="fw-bold fs-6 text-gray-800 border-bottom-2 border-gray-200">
                                <th>
                                    <input type="checkbox" id="tenant-bulk-select-all" aria-label="Select all tenants">
                                </th>
                                @foreach($visibleTenant as $col)
                                    <th>{{ ucwords(str_replace('_',' ',$col)) }}</th>
                                @endforeach
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold">
                            @forelse($tenantRows as $row)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="row-select" data-id="{{ $row['id'] ?? '' }}" data-batch-id="{{ $row['batch_id'] ?? '' }}" />
                                    </td>
                                    @foreach($visibleTenant as $col)
                                        <td>{{ ($row[$col] === '' || $row[$col] === null) ? '-' : $row[$col] }}</td>
                                    @endforeach
                                    <td>
                                        <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#details-{{ $loop->index }}">View</button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="details-{{ $loop->index }}">
                                    <td colspan="{{ count($visibleTenant) + 2 }}">
                                        <div class="card bg-light border-0">
                                            <div class="card-body p-4">
                                                <div class="row">
                                                    <div class="col-lg-12 mb-4">
                                                        <div class="d-flex align-items-center mb-3">
                                                            <h6 class="mb-0 fw-bold">All Imported Tenant Data</h6>
                                                            <span class="badge bg-primary ms-2">Imported</span>
                                                        </div>
                                                        @php
                                                            $importedTenantData = collect($tenantHeaders)->filter(function($h) use ($hiddenTenant) {
                                                                return !in_array($h, $hiddenTenant);
                                                            })->chunk(ceil(count($tenantHeaders) / 2));
                                                        @endphp
                                                        <div class="row">
                                                            @foreach($importedTenantData as $chunk)
                                                                <div class="col-12 mb-3">
                                                                    @foreach($chunk as $h)
                                                                        <div class="row mb-2">
                                                                            <div class="col-5">
                                                                                <span class="text-muted small fw-medium">{{ ucwords(str_replace('_',' ',$h)) }}</span>
                                                                            </div>
                                                                            <div class="col-7">
                                                                                <span class="small">{{ $row[$h] ?? '-' }}</span>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($visibleTenant) + 2 }}" class="text-center text-muted">No tenant data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form id="bulk-action-form" class="form">
                    @csrf
                    <input type="hidden" name="batch_id" value="">
                    <input type="hidden" name="selected_ids" value="">
                    <input type="hidden" name="select_all_pages" value="0">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-danger me-4 d-none" id="tenant-cancel-import-btn">
                            <span class="indicator-label">Cancel & Delete</span>
                            <span class="indicator-progress">Processing...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <button type="button" class="btn btn-success d-none" id="tenant-confirm-import-btn">
                            <span class="indicator-label">Confirm & Import</span>
                            <span class="indicator-progress">Processing...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- Payment Table with Invoice Validation --}}
        @if(isset($paymentRows) && count($paymentRows) > 0)
        <div class="card mb-8">
            <div class="card-header">
                <div class="card-title d-flex flex-row align-items-center">
                    <h3 class="mb-0 me-4">Payment Data Preview</h3>
                    {{-- Payment Validation Summary --}}
                    @if(isset($paymentValidationSummary))
                    <div class="mt-2">
                        <div class="d-flex flex-wrap gap-3">
                            <span class="badge badge-light-success fs-7">
                                <i class="fas fa-check-circle me-1"></i>
                                {{ $paymentValidationSummary['valid_invoices'] }} Valid
                            </span>
                            @if($paymentValidationSummary['invalid_invoices'] > 0)
                            <span class="badge badge-light-danger fs-7">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                {{ $paymentValidationSummary['invalid_invoices'] }} Invalid
                            </span>
                            @endif
                            @if($paymentValidationSummary['missing_invoices'] > 0)
                            <span class="badge badge-light-warning fs-7">
                                <i class="fas fa-question-circle me-1"></i>
                                {{ $paymentValidationSummary['missing_invoices'] }} No Invoice ID
                            </span>
                            @endif
                            @if($paymentValidationSummary['mismatched_invoices'] > 0)
                            <span class="badge badge-light-info fs-7">
                                <i class="fas fa-exclamation me-1"></i>
                                {{ $paymentValidationSummary['mismatched_invoices'] }} Mismatched
                            </span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive mb-4">
                    @php
                        $hiddenPayment = ['id','batch_id','user_id','row_number','updated_at','created_at', 'tenant_email', 'error_message', 'invoice_exists', 'invoice_details', 'invoice_validation_status', 'validation_error'];
                        $preferredPayment = ['invoice_id','tenancy_ref','tenant_profile_ref','payment_date','payment_period','amount_paid','payment_method'];
                        $visiblePayment = [];
                        foreach ($preferredPayment as $p) {
                            if (in_array($p, $paymentHeaders) && !in_array($p, $hiddenPayment)) $visiblePayment[] = $p;
                        }
                        if (empty($visiblePayment)) {
                            foreach ($paymentHeaders as $h) {
                                if (!in_array($h, $hiddenPayment)) { $visiblePayment[] = $h; if (count($visiblePayment) >= 6) break; }
                            }
                        }
                    @endphp
                    
                    <table class="table table-rounded table-row-bordered border gy-7 gs-7" id="payment-mapping-table">
                        <thead class="fs-7">
                            <tr class="fw-bold fs-6 text-gray-800 border-bottom-2 border-gray-200">
                                <th>
                                    <input type="checkbox" id="payment-bulk-select-all" aria-label="Select all payments">
                                </th>
                                @foreach($visiblePayment as $col)
                                    <th>{{ ucwords(str_replace('_',' ',$col)) }}</th>
                                @endforeach
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold">
                            @forelse($paymentRows as $row)
                                @php
                                    $validationStatus = $row['invoice_validation_status'] ?? 'missing';
                                    $rowClass = '';
                                    switch($validationStatus) {
                                        case 'valid':
                                            $rowClass = 'table-success';
                                            break;
                                        case 'invalid':
                                            $rowClass = 'table-danger';
                                            break;
                                        case 'mismatch':
                                            $rowClass = 'table-warning';
                                            break;
                                        case 'missing':
                                            $rowClass = 'table-light';
                                            break;
                                    }
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td>
                                        <input type="checkbox" class="row-select" 
                                               data-id="{{ $row['id'] ?? '' }}" 
                                               data-batch-id="{{ $row['batch_id'] ?? '' }}"
                                               @if($validationStatus === 'invalid' || $validationStatus === 'mismatch') disabled title="Cannot select invalid payments" @endif />
                                    </td>
                                    @foreach($visiblePayment as $col)
                                        <td>
                                            @if($col === 'invoice_id' && !empty($row[$col]))
                                                <div class="d-flex align-items-center">
                                                    <span>{{ $row[$col] }}</span>
                                                    @if($validationStatus === 'valid')
                                                        <i class="fas fa-check-circle text-success ms-2" title="Invoice exists"></i>
                                                    @elseif($validationStatus === 'invalid')
                                                        <i class="fas fa-exclamation-triangle text-danger ms-2" title="Invoice not found"></i>
                                                    @elseif($validationStatus === 'mismatch')
                                                        <i class="fas fa-exclamation text-warning ms-2" title="Invoice belongs to different tenancy"></i>
                                                    @endif
                                                </div>
                                            @else
                                                {{ ($row[$col] === '' || $row[$col] === null) ? '-' : $row[$col] }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td>
                                        @if($validationStatus === 'valid')
                                            <span class="badge badge-success">
                                                <i class="fas fa-check me-1"></i>Valid
                                            </span>
                                        @elseif($validationStatus === 'invalid')
                                            <span class="badge badge-danger">
                                                <i class="fas fa-times me-1"></i>Not Found
                                            </span>
                                        @elseif($validationStatus === 'mismatch')
                                            <span class="badge badge-warning">
                                                <i class="fas fa-exclamation me-1"></i>Mismatch
                                            </span>
                                        @elseif($validationStatus === 'missing')
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-question me-1"></i>No Invoice ID
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#payment-details-{{ $loop->index }}">View</button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="payment-details-{{ $loop->index }}">
                                    <td colspan="{{ count($visiblePayment) + 3 }}">
                                        <div class="card bg-light border-0">
                                            <div class="card-body p-4">
                                                <div class="row">
                                                    {{-- Payment Data --}}
                                                    <div class="col-lg-8 mb-4">
                                                        <div class="d-flex align-items-center mb-3">
                                                            <h6 class="mb-0 fw-bold">All Imported Payment Data</h6>
                                                            <span class="badge bg-primary ms-2">Imported</span>
                                                        </div>
                                                        @php
                                                            $importedPaymentData = collect($paymentHeaders)->filter(function($h) use ($hiddenPayment) {
                                                                return !in_array($h, $hiddenPayment);
                                                            })->chunk(ceil(count($paymentHeaders) / 2));
                                                        @endphp
                                                        <div class="row">
                                                            @foreach($importedPaymentData as $chunk)
                                                                <div class="col-12 mb-3">
                                                                    @foreach($chunk as $h)
                                                                        <div class="row mb-2">
                                                                            <div class="col-5">
                                                                                <span class="text-muted small fw-medium">{{ ucwords(str_replace('_',' ',$h)) }}</span>
                                                                            </div>
                                                                            <div class="col-7">
                                                                                <span class="small">{{ $row[$h] ?? '-' }}</span>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    
                                                    {{-- Invoice Validation Details --}}
                                                    <div class="col-lg-4">
                                                        <div class="d-flex align-items-center mb-3">
                                                            <h6 class="mb-0 fw-bold">Invoice Validation</h6>
                                                            @if($validationStatus === 'valid')
                                                                <span class="badge bg-success ms-2">Valid</span>
                                                            @elseif($validationStatus === 'invalid')
                                                                <span class="badge bg-danger ms-2">Invalid</span>
                                                            @elseif($validationStatus === 'mismatch')
                                                                <span class="badge bg-warning ms-2">Mismatch</span>
                                                            @else
                                                                <span class="badge bg-secondary ms-2">No Invoice</span>
                                                            @endif
                                                        </div>
                                                        
                                                        @if($validationStatus === 'valid' && isset($row['invoice_details']))
                                                            @php $invoice = $row['invoice_details']; @endphp
                                                            <div class="border rounded p-3 bg-white">
                                                                <div class="mb-2">
                                                                    <small class="text-muted">Invoice Number:</small><br>
                                                                    <strong>{{ $invoice['invoice_number'] }}</strong>
                                                                </div>
                                                                @if(!empty($invoice['combined_invoice_number']))
                                                                <div class="mb-2">
                                                                    <small class="text-muted">Combined Invoice:</small><br>
                                                                    <strong>{{ $invoice['combined_invoice_number'] }}</strong>
                                                                </div>
                                                                @endif
                                                                <div class="mb-2">
                                                                    <small class="text-muted">Amount:</small><br>
                                                                    <strong class="text-success">₦{{ number_format($invoice['amount'], 2) }}</strong>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <small class="text-muted">Tenancy:</small><br>
                                                                    <strong>{{ $invoice['tenancy_ref'] }}</strong>
                                                                </div>
                                                                @if(!empty($invoice['due_date']))
                                                                <div class="mb-2">
                                                                    <small class="text-muted">Due Date:</small><br>
                                                                    <strong>{{ \Carbon\Carbon::parse($invoice['due_date'])->format('M d, Y') }}</strong>
                                                                </div>
                                                                @endif
                                                            </div>
                                                        @elseif(isset($row['validation_error']))
                                                            <div class="alert alert-danger">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                                {{ $row['validation_error'] }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($visiblePayment) + 3 }}" class="text-center text-muted">No payment data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Warning message for invalid payments --}}
                @if(isset($paymentValidationSummary) && ($paymentValidationSummary['invalid_invoices'] > 0 || $paymentValidationSummary['mismatched_invoices'] > 0))
                <div class="alert alert-warning d-flex align-items-center mb-4">
                    <i class="fas fa-exclamation-triangle fs-3 me-3"></i>
                    <div>
                        <h6 class="mb-1">Invoice Validation Issues Found</h6>
                        <p class="mb-0">Some payments reference invoices that don't exist or belong to different tenancies. These payments cannot be imported and have been disabled from selection. Please review your data and ensure all invoice IDs are correct.</p>
                    </div>
                </div>
                @endif

                <form id="payment-bulk-action-form" class="form">
                    @csrf
                    <input type="hidden" name="batch_id" value="">
                    <input type="hidden" name="selected_ids" value="">
                    <input type="hidden" name="select_all_pages" value="0">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-danger me-4 d-none" id="payment-cancel-import-btn">
                            <span class="indicator-label">Cancel & Delete</span>
                            <span class="indicator-progress">Processing...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <button type="button" class="btn btn-success d-none" id="payment-confirm-import-btn">
                            <span class="indicator-label">Confirm & Import</span>
                            <span class="indicator-progress">Processing...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- Empty State --}}
        @if((!isset($tenantRows) || count($tenantRows) === 0) && (!isset($paymentRows) || count($paymentRows) === 0) && (!isset($noticeRows) || count($noticeRows) === 0))
        <div class="card">
            <div class="card-body py-20 text-center">
                <div class="mb-6">
                    <i class="ki-duotone ki-document fs-5x text-muted">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                </div>
                <h4 class="fw-bold text-dark mb-2">Nothing to Review</h4>
                <p class="text-muted fs-6 mb-6">There is no imported data pending confirmation. Upload a file to get started.</p>
                <a href="{{ url()->previous() }}" class="btn btn-light-primary me-3">
                    <i class="ki-duotone ki-arrow-left fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Go Back
                </a>
                <a href="/tenant_management" class="btn btn-primary">
                    Go to Tenants
                </a>
            </div>
        </div>
        @endif

        {{-- Notice Table --}}
        @if(isset($noticeRows) && count($noticeRows) > 0)
        <div class="card mb-8">
            <div class="card-header">
                <h3 class="card-title">Notice Data Preview</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive mb-4">
                    @php
                        $hiddenNotice = ['id','batch_id','user_id','row_number','updated_at','created_at', 'tenant_email', 'error_message'];
                        $preferredNotice = [
                            'tenancy_ref','tenant_profile_ref','notice_type','notice_date','effective_date','notice_subject','priority_level'
                        ];
                        $visibleNotice = [];
                        foreach ($preferredNotice as $p) {
                            if (in_array($p, $noticeHeaders) && !in_array($p, $hiddenNotice)) $visibleNotice[] = $p;
                        }
                        if (empty($visibleNotice)) {
                            foreach ($noticeHeaders as $h) {
                                if (!in_array($h, $hiddenNotice)) { $visibleNotice[] = $h; if (count($visibleNotice) >= 6) break; }
                            }
                        }
                    @endphp
                    
                    <table class="table table-rounded table-row-bordered border gy-7 gs-7" id="notice-mapping-table">
                        <thead class="fs-7">
                            <tr class="fw-bold fs-6 text-gray-800 border-bottom-2 border-gray-200">
                                <th>
                                    <input type="checkbox" id="notice-bulk-select-all" aria-label="Select all notices">
                                </th>
                                @foreach($visibleNotice as $col)
                                    <th>{{ ucwords(str_replace('_',' ',$col)) }}</th>
                                @endforeach
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold">
                            @forelse($noticeRows as $row)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="row-select" data-id="{{ $row['id'] ?? '' }}" data-batch-id="{{ $row['batch_id'] ?? '' }}" />
                                    </td>
                                    @foreach($visibleNotice as $col)
                                        <td>{{ ($row[$col] === '' || $row[$col] === null) ? '-' : $row[$col] }}</td>
                                    @endforeach
                                    <td>
                                        <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#notice-details-{{ $loop->index }}">View</button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="notice-details-{{ $loop->index }}">
                                    <td colspan="{{ count($visibleNotice) + 2 }}">
                                        <div class="card bg-light border-0">
                                            <div class="card-body p-4">
                                                <div class="row">
                                                    <div class="col-lg-12 mb-4">
                                                        <div class="d-flex align-items-center mb-3">
                                                            <h6 class="mb-0 fw-bold">All Imported Notice Data</h6>
                                                            <span class="badge bg-primary ms-2">Imported</span>
                                                        </div>
                                                        @php
                                                            $importedNoticeData = collect($noticeHeaders)->filter(function($h) use ($hiddenNotice) {
                                                                return !in_array($h, $hiddenNotice);
                                                            })->chunk(ceil(count($noticeHeaders) / 2));
                                                        @endphp
                                                        <div class="row">
                                                            @foreach($importedNoticeData as $chunk)
                                                                <div class="col-12 mb-3">
                                                                    @foreach($chunk as $h)
                                                                        <div class="row mb-2">
                                                                            <div class="col-5">
                                                                                <span class="text-muted small fw-medium">{{ ucwords(str_replace('_',' ',$h)) }}</span>
                                                                            </div>
                                                                            <div class="col-7">
                                                                                <span class="small">{{ $row[$h] ?? '-' }}</span>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($visibleNotice) + 2 }}" class="text-center text-muted">No notice data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form id="notice-bulk-action-form" class="form">
                    @csrf
                    <input type="hidden" name="batch_id" value="">
                    <input type="hidden" name="selected_ids" value="">
                    <input type="hidden" name="select_all_pages" value="0">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-danger me-4 d-none" id="notice-cancel-import-btn">
                            <span class="indicator-label">Cancel & Delete</span>
                            <span class="indicator-progress">Processing...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <button type="button" class="btn btn-success d-none" id="notice-confirm-import-btn">
                            <span class="indicator-label">Confirm & Import</span>
                            <span class="indicator-progress">Processing...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection
