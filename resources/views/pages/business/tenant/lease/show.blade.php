@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Lease Agreement Details</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    <a href="/tenant_management" class="text-muted">Tenant Management</a>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="/tenant_management/tenancy/{{ $tenantLease->tenantManagement->tenancy_ref }}/view" class="text-muted">{{ $tenantLease->tenantManagement->tenancy_ref }}</a>
                </li>
                <li class="breadcrumb-item text-dark">Lease Details</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="/tenant_management/tenancy/{{ $tenantLease->tenantManagement->tenancy_ref }}/view" class="btn btn-sm btn-info">
                <i class="fas fa-eye"></i> Tenancy Details
            </a>
            @businessCan('tenant_management', 'edit')
            <a href="/tenant_management/lease/edit/{{ Crypt::encrypt($tenantLease->id) }}" class="btn btn-sm btn-primary">
                <i class="fas fa-edit"></i> Edit Lease
            </a>
            @endbusinessCan
        </div>
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        
        {{-- Status Banner --}}
        @if($tenantLease->isExpiringSoon(30))
        <div class="alert alert-warning border-warning d-flex align-items-center mb-5 p-5">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle fs-3x text-warning me-5"></i>
            </div>
            <div class="flex-grow-1">
                <h3 class="alert-heading text-warning fw-bold mb-2">
                    <i class="fas fa-bell me-2"></i>Lease Expiring Soon
                </h3>
                <p class="mb-0 fs-5">This lease will expire in <strong>{{ $tenantLease->daysUntilExpiry() }} days</strong> on <strong>{{ $tenantLease->lease_end_date?->format('F d, Y') }}</strong>.</p>
                @if($tenantLease->auto_renewal)
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Auto-renewal is enabled. The lease will automatically renew unless terminated.
                    </small>
                @endif
            </div>
        </div>
        @endif

        <div class="row g-5">
            
            {{-- Left Column --}}
            <div class="col-lg-8">
                
                {{-- Tenant Information --}}
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Tenant Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-5">
                            <div class="symbol symbol-circle symbol-45px overflow-hidden me-4">
                                <div class="symbol-label fs-3 bg-light-primary text-primary">
                                    {{ strtoupper(substr($tenantLease->tenantManagement->tenantProfile->display_name, 0, 1)) }}
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <a href="/tenant_management/tenancy/{{ $tenantLease->tenantManagement->tenancy_ref }}/view" class="text-gray-800 text-hover-primary fs-4 fw-bold">
                                    {{ $tenantLease->tenantManagement->tenantProfile->display_name }}
                                </a>
                                <span class="badge badge-light-{{ $tenantLease->tenantManagement->tenantProfile->tenant_type === 'Business' ? 'success' : 'primary' }} ms-2 fs-8">
                                    {{ $tenantLease->tenantManagement->tenantProfile->tenant_type }}
                                </span>
                                <div class="text-gray-500 fs-7 mt-1">
                                    {{ $tenantLease->tenantManagement->tenancy_ref }}
                                </div>
                            </div>
                        </div>
                        <div class="separator separator-dashed mb-5"></div>
                        <table class="table table-row-bordered table-row-dashed gy-4 mb-0">
                            <tbody class="fs-6">
                                <tr>
                                    <td class="text-gray-500 fw-semibold w-175px">Email</td>
                                    <td class="text-gray-800">{{ $tenantLease->tenantManagement->tenantProfile->primary_email }}</td>
                                </tr>
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Phone</td>
                                    <td class="text-gray-800">
                                        <a href="tel:{{ $tenantLease->tenantManagement->tenantProfile->primary_phone }}" class="text-gray-800 text-hover-primary">
                                            {{ $tenantLease->tenantManagement->tenantProfile->primary_phone }}
                                        </a>
                                    </td>
                                </tr>
                                @if($tenantLease->tenantManagement->complex)
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Property</td>
                                    <td class="text-gray-800">
                                        <a href="/complex/{{ $tenantLease->tenantManagement->complex->slug }}" class="text-gray-800 text-hover-primary">
                                            {{ $tenantLease->tenantManagement->complex->title }}
                                            <i class="fas fa-external-link-alt fs-8 ms-1 text-gray-400"></i>
                                        </a>
                                        @if($tenantLease->tenantManagement->unit_number)
                                            <span class="text-gray-500 ms-1">· Unit {{ $tenantLease->tenantManagement->unit_number }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($tenantLease->tenantManagement->complex->location_description)
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Location</td>
                                    <td class="text-gray-600">{{ $tenantLease->tenantManagement->complex->location_description }}</td>
                                </tr>
                                @endif
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Lease Details --}}
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Lease Details</h3>
                        <div class="card-toolbar">
                            <span class="badge badge-{{ $tenantLease->status === 'active' ? 'light-success' : ($tenantLease->status === 'pending' ? 'light-warning' : 'light-dark') }} fs-7">
                                {{ ucfirst($tenantLease->status) }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-row-bordered table-row-dashed gy-4 mb-0">
                            <tbody class="fs-6">
                                <tr>
                                    <td class="text-gray-500 fw-semibold w-175px">Lease Type</td>
                                    <td class="text-gray-800 fw-semibold">{{ ucwords(str_replace('-', ' ', $tenantLease->lease_type)) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Start Date</td>
                                    <td class="text-gray-800">{{ $tenantLease->lease_start_date->format('M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-gray-500 fw-semibold">End Date</td>
                                    <td class="text-gray-800">{{ $tenantLease->lease_end_date?->format('M d, Y') ?? 'Open-ended' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Duration</td>
                                    <td class="text-gray-800">{{ $tenantLease->lease_duration_months }} months</td>
                                </tr>
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Auto-Renewal</td>
                                    <td>
                                        @if($tenantLease->auto_renewal)
                                            <span class="badge badge-light-success fs-7">Enabled</span>
                                            @if($tenantLease->renewal_notice_days)
                                                <span class="text-gray-500 ms-2 fs-7">{{ $tenantLease->renewal_notice_days }}-day notice</span>
                                            @endif
                                        @else
                                            <span class="badge badge-light-dark fs-7">Disabled</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($tenantLease->auto_renewal && $tenantLease->renewal_rent_increase_percentage)
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Rent Increase</td>
                                    <td class="text-gray-800">
                                        {{ $tenantLease->renewal_rent_increase_percentage }}% per renewal
                                        <span class="text-gray-500 fs-7 d-block mt-1">Next: {{ $tenantLease->currency }} {{ number_format($tenantLease->rent_amount * (1 + $tenantLease->renewal_rent_increase_percentage / 100), 2) }}</span>
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Financial Terms --}}
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Financial Terms</h3>
                    </div>
                    <div class="card-body">
                        {{-- Key Figures --}}
                        <div class="row g-5 mb-6">
                            <div class="col-md-4">
                                <div class="text-gray-500 fw-semibold fs-7 mb-1">Rent Amount</div>
                                <div class="text-gray-800 fw-bolder fs-2">{{ $tenantLease->currency }} {{ number_format($tenantLease->rent_amount, 2) }}</div>
                                <div class="text-gray-400 fs-8">Per {{ $tenantLease->payment_frequency }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-gray-500 fw-semibold fs-7 mb-1">Security Deposit</div>
                                <div class="text-gray-800 fw-bolder fs-2">{{ $tenantLease->currency }} {{ number_format($tenantLease->security_deposit, 2) }}</div>
                                <div class="text-gray-400 fs-8">One-time payment</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-gray-500 fw-semibold fs-7 mb-1">Total Lease Value</div>
                                <div class="text-gray-800 fw-bolder fs-2">{{ $tenantLease->currency }} {{ number_format($tenantLease->getTotalLeaseValue(), 2) }}</div>
                                <div class="text-gray-400 fs-8">{{ number_format($tenantLease->rent_amount, 2) }} × {{ $tenantLease->lease_duration_months }} months</div>
                            </div>
                        </div>
                        <div class="separator separator-dashed mb-6"></div>
                        {{-- Payment Details --}}
                        <table class="table table-row-bordered table-row-dashed gy-4 mb-0">
                            <tbody class="fs-6">
                                <tr>
                                    <td class="text-gray-500 fw-semibold w-175px">Payment Frequency</td>
                                    <td class="text-gray-800">{{ ucwords($tenantLease->payment_frequency) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Payment Day</td>
                                    <td class="text-gray-800">Day {{ $tenantLease->payment_day_of_month }} of each month</td>
                                </tr>
                                @if($tenantLease->late_fee_amount)
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Late Fee</td>
                                    <td class="text-gray-800">{{ $tenantLease->currency }} {{ number_format($tenantLease->late_fee_amount, 2) }}</td>
                                </tr>
                                @endif
                                @if($tenantLease->grace_period_days)
                                <tr>
                                    <td class="text-gray-500 fw-semibold">Grace Period</td>
                                    <td class="text-gray-800">{{ $tenantLease->grace_period_days }} days</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Utilities & Restrictions --}}
                @if(!empty($tenantLease->utilities_included) || !empty($tenantLease->restrictions))
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Utilities & Restrictions</h3>
                    </div>
                    <div class="card-body">
                        @if(!empty($tenantLease->utilities_included))
                        <div class="{{ !empty($tenantLease->restrictions) ? 'mb-6' : '' }}">
                            <div class="text-gray-500 fw-semibold fs-7 mb-3">Utilities Included</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($tenantLease->utilities_included as $utility)
                                    <span class="badge badge-light fs-7 px-3 py-2">
                                        <i class="fas fa-{{ $utility === 'water' ? 'tint' : ($utility === 'electricity' ? 'bolt' : ($utility === 'gas' ? 'fire' : ($utility === 'internet' ? 'wifi' : 'tv'))) }} me-1 text-gray-500"></i>
                                        {{ ucwords(str_replace('_', ' ', $utility)) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @if(!empty($tenantLease->restrictions))
                        <div>
                            <div class="text-gray-500 fw-semibold fs-7 mb-3">Restrictions</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($tenantLease->restrictions as $restriction)
                                    <span class="badge badge-light fs-7 px-3 py-2">
                                        <i class="fas fa-{{ str_contains($restriction, 'pet') ? 'paw' : (str_contains($restriction, 'smoking') ? 'smoking-ban' : (str_contains($restriction, 'subletting') ? 'user-slash' : 'briefcase')) }} me-1 text-gray-500"></i>
                                        {{ ucwords(str_replace('_', ' ', $restriction)) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Additional Terms --}}
                @if($tenantLease->special_terms || $tenantLease->terms_and_conditions)
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Additional Terms</h3>
                    </div>
                    <div class="card-body">
                        @if($tenantLease->special_terms)
                        <div class="{{ $tenantLease->terms_and_conditions ? 'mb-6' : '' }}">
                            <div class="text-gray-500 fw-semibold fs-7 mb-2">Special Terms</div>
                            <p class="text-gray-700 fs-6 mb-0">{{ $tenantLease->special_terms }}</p>
                        </div>
                        @endif
                        @if($tenantLease->terms_and_conditions)
                        <div>
                            <div class="text-gray-500 fw-semibold fs-7 mb-2">Terms and Conditions</div>
                            <p class="text-gray-700 fs-6 mb-0">{{ $tenantLease->terms_and_conditions }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

            </div>

            {{-- Right Column --}}
            <div class="col-lg-4">
                
                {{-- Lease Overview --}}
                <div class="card mb-5">
                    <div class="card-body">
                        {{-- Days Remaining --}}
                        <div class="text-center pb-5">
                            @if($tenantLease->daysUntilExpiry() !== null)
                                <div class="text-gray-800 fs-3x fw-bolder">{{ $tenantLease->daysUntilExpiry() }}</div>
                                <div class="text-gray-500 fw-semibold fs-7">Days Remaining</div>
                                <div class="text-gray-400 fs-8 mt-1">Until {{ $tenantLease->lease_end_date->format('M d, Y') }}</div>
                            @else
                                <div class="text-gray-800 fs-3x fw-bolder">&infin;</div>
                                <div class="text-gray-500 fw-semibold fs-7">Open-Ended Lease</div>
                                <div class="text-gray-400 fs-8 mt-1">No fixed end date</div>
                            @endif
                        </div>
                        <div class="separator separator-dashed mb-5"></div>
                        {{-- Key Dates --}}
                        <div class="d-flex justify-content-between mb-4">
                            <span class="text-gray-500 fw-semibold fs-7">Created</span>
                            <span class="text-gray-800 fw-semibold fs-7">{{ $tenantLease->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-gray-500 fw-semibold fs-7">Last Updated</span>
                            <span class="text-gray-800 fw-semibold fs-7">{{ $tenantLease->updated_at->format('M d, Y') }}</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

@endsection
