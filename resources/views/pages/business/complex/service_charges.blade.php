@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <!--begin::Container-->
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <!--begin::Heading-->
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Service charges</h1>
            <!--end::Heading-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    <a href="/dashboard" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="/complex" class="text-muted text-hover-primary">Property portfolio</a>
                </li>
                <li class="breadcrumb-item text-dark">Service Charges</li>
            </ul>
            <!--end::Breadcrumb-->
        </div>
        <!--end::Page title=-->
        <!--begin::Wrapper-->
        <div class="d-flex d-lg-none align-items-center ms-n2 me-2">
            <!--begin::Aside mobile toggle-->
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle">
                <!--begin::Svg Icon | path: icons/duotune/abstract/abs015.svg-->
                <span class="svg-icon svg-icon-1 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                    </svg>
                </span>
                <!--end::Svg Icon-->
            </div>
            <!--end::Aside mobile toggle-->
        </div>
        <!--end::Wrapper-->
        <!--begin::Topbar-->
            @include('pages.business.include.topbar.index')
        <!--end::Topbar-->
    </div>
    <!--end::Container-->
</div>
<!--end::Header-->

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl" id="kt_content_container">

        <div class="d-flex flex-wrap align-items-end justify-content-between gap-4 mb-6">
            <div>
                <p class="text-muted mb-1">Recurring charges billed to tenants for a property's shared facilities. They are separate from property operating expenses.</p>
                @if($selected && ($selectedComplex = $complexes->firstWhere('id', $selected)))
                    <a href="{{ route('complex.setup', [$selectedComplex->slug, 'step' => 'charges']) }}" class="fw-semibold">← Back to {{ $selectedComplex->title }} setup</a>
                @endif
            </div>
            <form method="GET" action="{{ route('complex.service-charges') }}" class="d-flex gap-2 align-items-end">
                <div>
                    <label for="charges-filter" class="form-label mb-1">Property</label>
                    <select id="charges-filter" name="complex_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All properties</option>
                        @foreach($complexes as $complex)
                            <option value="{{ $complex->id }}" @selected($selected === $complex->id)>{{ $complex->title }}</option>
                        @endforeach
                    </select>
                </div>
                @if($selected)<a class="btn btn-sm btn-light" href="{{ route('complex.service-charges') }}">Reset</a>@endif
            </form>
        </div>

        <div class="row g-6">
            @if($canEdit)
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm" id="service-charge-editor">
                    <div class="card-body p-6">
                        <h3 class="fs-4 mb-1" data-charge-title>Add a service charge</h3>
                        <p class="text-muted fs-7 mb-5">Choose a property and one of its recorded facilities.</p>
                        @if($complexes->isEmpty())
                            <p class="text-muted mb-0">Create a property first.</p>
                        @else
                        <form id="form_service_charge" novalidate>
                            <input type="hidden" name="charge_id" value="">
                            <div class="mb-4">
                                <label for="service_complex" class="form-label required">Property</label>
                                <select id="service_complex" name="complex_id" class="form-select" required>
                                    <option value="">Choose a property</option>
                                    @foreach($complexes as $complex)
                                        <option value="{{ $complex->id }}" @selected($selected === $complex->id)>{{ $complex->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="complex_amenitiy" class="form-label required">Facility</label>
                                <select id="complex_amenitiy" name="amenity_id" class="form-select" required disabled>
                                    <option value="">Choose a property first</option>
                                </select>
                                <div class="form-text" data-facility-help>Facilities are added on the property's Facilities tab.</div>
                            </div>
                            <div class="mb-4">
                                <label for="payment_interval" class="form-label required">Billing interval</label>
                                <select id="payment_interval" name="payment_interval" class="form-select" required>
                                    @foreach($intervals as $interval)<option value="{{ $interval }}">{{ $interval }}</option>@endforeach
                                </select>
                            </div>
                            <div class="mb-5">
                                <label for="service_charge" class="form-label required">Amount (KES)</label>
                                <input id="service_charge" name="charge" type="number" min="1" step="1" class="form-control" required placeholder="e.g. 2500">
                            </div>
                            <div class="alert alert-danger d-none" role="alert" data-charge-error></div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" id="kt_button_submit_service_charge">Save charge</button>
                                <button type="button" class="btn btn-light d-none" data-charge-cancel>Cancel edit</button>
                            </div>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <div class="{{ $canEdit ? 'col-lg-8' : 'col-12' }}">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-6">
                        @forelse($groupedCharges as $complexTitle => $charges)
                            <div class="{{ $loop->last ? '' : 'mb-8' }}">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h3 class="fs-5 mb-0"><a href="{{ route('complex.show', $charges->first()->complex_slug) }}" class="text-dark text-hover-primary">{{ $complexTitle }}</a></h3>
                                        <span class="text-muted fs-7">{{ $charges->first()->county }}{{ $charges->first()->county_specific ? ', '.$charges->first()->county_specific : '' }}</span>
                                    </div>
                                    <span class="badge badge-light-info">{{ $charges->count() }} {{ \Illuminate\Support\Str::plural('charge', $charges->count()) }}</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-row-bordered align-middle gy-3 mb-0">
                                        <thead><tr class="fw-bold text-muted"><th>Facility</th><th>Interval</th><th class="text-end">Amount</th><th>Updated</th>@if($canEdit)<th class="text-end">Actions</th>@endif</tr></thead>
                                        <tbody>
                                        @foreach($charges as $charge)
                                            <tr>
                                                <td>{{ $facilityLabels[$charge->facility_type] ?? \Illuminate\Support\Str::headline($charge->facility_type) }}</td>
                                                <td>{{ $charge->name }}</td>
                                                <td class="text-end fw-semibold">KES {{ number_format($charge->charge) }}</td>
                                                <td class="text-muted fs-7">{{ $charge->updated_at?->format('j M Y') }}</td>
                                                @if($canEdit)
                                                <td class="text-end text-nowrap">
                                                    <button type="button" class="btn btn-sm btn-light" data-edit-charge="{{ json_encode(['id' => $charge->id, 'complex_id' => $charge->complex_id, 'amenity_id' => $charge->amenity_id, 'payment_interval' => $charge->name, 'charge' => $charge->charge]) }}">Edit</button>
                                                    <button type="button" class="btn btn-sm btn-light-danger delete-service-charge" data-id="{{ $charge->id }}">Delete</button>
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-10">
                                <h3 class="fs-5">No service charges {{ $selected ? 'for this property' : 'yet' }}</h3>
                                <p class="text-muted mb-0">If a property bills tenants for shared facilities, add the charge here. If none apply, record that decision in the property's service charges setup step.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @include('pages.business.complex.include.modals')
    </div>
</div>
<!--end::Content-->
@endsection
