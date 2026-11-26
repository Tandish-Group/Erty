@extends('pages.business.layouts.app')

@section('content')

<!--begin::Header-->
<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Transfer: {{ $movement->ref }}</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">Home</li>
                <li class="breadcrumb-item text-muted"><a href="/tenant_management" class="text-muted">Tenant Management</a></li>
                <li class="breadcrumb-item text-muted"><a href="/tenant_management/movement" class="text-muted">Unit Transfers</a></li>
                <li class="breadcrumb-item text-dark">{{ $movement->ref }}</li>
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
<!--end::Header-->

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl" id="kt_content_container">

        <div class="row g-5 g-xl-8">
            <div class="col-xl-8">
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3 class="fw-bolder">Transfer Details</h3>
                        </div>
                        <div class="card-toolbar">
                            {!! $movement->status_badge !!}
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <table class="table table-row-dashed fs-6 gy-4">
                            <tbody>
                                <tr><td class="text-muted w-170px">Reference</td><td class="fw-bolder">{{ $movement->ref }}</td></tr>
                                <tr><td class="text-muted">Tenant</td><td class="fw-bolder">{{ optional($movement->tenantProfile)->full_name }}</td></tr>
                                <tr><td class="text-muted">From Unit</td><td class="fw-bolder text-danger">{{ $movement->from_unit_label }}</td></tr>
                                <tr><td class="text-muted">To Unit</td><td class="fw-bolder text-success">{{ $movement->to_unit_label }}</td></tr>
                                <tr><td class="text-muted">Move Type</td><td class="fw-bolder">{{ $movement->move_type === 'end_of_month' ? 'End of Month' : 'Mid Month' }}</td></tr>
                                <tr><td class="text-muted">Transfer Date</td><td class="fw-bolder">{{ $movement->transfer_date->format('d M Y') }}</td></tr>
                                <tr><td class="text-muted">Old Rent</td><td class="fw-bolder">{{ $movement->currency }} {{ number_format($movement->old_rent, 2) }}</td></tr>
                                <tr><td class="text-muted">New Rent</td><td class="fw-bolder">{{ $movement->currency }} {{ number_format($movement->new_rent, 2) }}</td></tr>
                                <tr><td class="text-muted">Deposit Adjustment</td>
                                    <td class="fw-bolder">
                                        @if($movement->deposit_adjustment_type === 'top_up')
                                            <span class="text-danger">+{{ $movement->currency }} {{ number_format($movement->deposit_adjustment, 2) }} (top-up)</span>
                                        @elseif($movement->deposit_adjustment_type === 'refund')
                                            <span class="text-success">-{{ $movement->currency }} {{ number_format($movement->deposit_adjustment, 2) }} (refund)</span>
                                        @else
                                            <span class="text-muted">No change</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($movement->notes)
                                <tr><td class="text-muted">Notes</td><td>{{ $movement->notes }}</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card mb-5">
                    <div class="card-header border-0 pt-6"><div class="card-title"><h3 class="fw-bolder">Actions</h3></div></div>
                    <div class="card-body pt-0 d-flex flex-column gap-3">
                        @if($movement->status === 'pending')
                            <button class="btn btn-success w-100" id="btn_approve_movement" data-ref="{{ $movement->ref }}">
                                <i class="fas fa-check me-2"></i>Approve Transfer
                            </button>
                            <button class="btn btn-danger w-100" id="btn_cancel_movement" data-ref="{{ $movement->ref }}">
                                <i class="fas fa-times me-2"></i>Cancel Transfer
                            </button>
                        @elseif($movement->status === 'approved')
                            <button class="btn btn-danger w-100" id="btn_cancel_movement" data-ref="{{ $movement->ref }}">
                                <i class="fas fa-times me-2"></i>Cancel Transfer
                            </button>
                        @endif
                        <a href="/tenant_management/movement" class="btn btn-light w-100">
                            <i class="fas fa-arrow-left me-2"></i>Back to Transfers
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!--begin::Cancel Modal-->
<div class="modal fade" id="cancelMovementModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header"><h2>Cancel Transfer</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="cancel_movement_ref" value="{{ $movement->ref }}">
                <div class="mb-3">
                    <label class="form-label">Reason (optional)</label>
                    <textarea id="cancel_reason" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Keep</button>
                <button class="btn btn-danger" id="confirm_cancel_btn">Cancel Transfer</button>
            </div>
        </div>
    </div>
</div>
<!--end::Cancel Modal-->

@endsection
