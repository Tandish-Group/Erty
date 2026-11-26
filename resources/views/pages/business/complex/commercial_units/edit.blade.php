@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
	<!--begin::Container-->
	<div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
		<!--begin::Page title-->
		<div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
			<!--begin::Heading-->
			<h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Edit Commercial Unit</h1>
			<!--end::Heading-->
			<!--begin::Breadcrumb-->
			<ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
				<li class="breadcrumb-item text-muted">
					<a href="{{ route('dashboard.index') }}" class="text-muted">Home</a>
				</li>
				<li class="breadcrumb-item text-muted">Complex</li>
				<li class="breadcrumb-item text-muted">
					<a href="{{ route('commercial-units.index') }}" class="text-muted">Commercial Units</a>
				</li>
				<li class="breadcrumb-item text-dark">Edit Unit</li>
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
	<!--begin::Container-->
	<div class="container-xxl" id="kt_content_container">
		<!--begin::Card-->
		<div class="card">
			<!--begin::Card header-->
			<div class="card-header">
				<h3 class="card-title">Edit Commercial Unit Details</h3>
				<div class="card-toolbar">
					<a href="{{ route('commercial-units.index') }}" class="btn btn-light-primary">
						<i class="fas fa-arrow-left"></i>Back to List
					</a>
				</div>
			</div>
			<!--end::Card header-->

			<!--begin::Card body-->
			<div class="card-body">
				{{-- Success Message --}}
				@if(session('success'))
					<div class="alert alert-success alert-dismissible fade show" role="alert">
						<strong>Success!</strong> {{ session('success') }}
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
					</div>
				@endif

				{{-- Error Message --}}
				@if(session('error'))
					<div class="alert alert-danger alert-dismissible fade show" role="alert">
						<strong>Error!</strong> {{ session('error') }}
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
					</div>
				@endif

				{{-- Validation Errors --}}
				@if($errors->any())
					<div class="alert alert-danger alert-dismissible fade show" role="alert">
						<strong>Validation Errors:</strong>
						<ul class="mb-0">
							@foreach($errors->all() as $error)
								<li>{{ $error }}</li>
							@endforeach
						</ul>
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
					</div>
				@endif

<form class="form" id="kt_commercial_unit_edit_form" method="POST" action="{{ route('commercial-units.update', encrypt($unit->id)) }}">
					@csrf
					@method('PUT')
					
					<!--begin::Complex Info (Read-only)-->
					<div class="fv-row mb-10">
						<label class="form-label">Complex</label>
						<input type="text" class="form-control bg-light" value="{{ $unit->complex->title ?? 'N/A' }}" readonly />
						<input type="hidden" name="complex_id" value="{{ $unit->complex_id }}" />
					</div>
					<!--end::Complex Info-->

					<!--begin::Complex Units Breakdown Display-->
					<div class="col-12 mb-7" id="commercial_units_complex_container" style="display: none;">
						<div class="card rounded border border-secondary">
							<div class="card-header">
								<h3 class="card-title text-info">
									<i class="fas fa-building me-2"></i>Complex Information
								</h3>
							</div>
							<div class="card-body p-5">
								<div id="commercial_units_complex_info" class="row g-3">
									<!-- Complex info will be loaded here dynamically -->
								</div>
							</div>
						</div>
					</div>
					<!--end::Complex Units Breakdown Display-->

					<!--begin::Unit Details-->
					<div class="row mb-5">
						<div class="col-md-6 mb-3">
							<label class="form-label required">Unit Number/ID:</label>
							<input type="text" name="unit_number" class="form-control" placeholder="e.g., Unit 101, Shop A1" value="{{ old('unit_number', $unit->unit_number) }}" required />
						</div>
						
						<div class="col-md-6 mb-3">
							<label class="form-label required">Unit Square Footage:</label>
							<input type="number" name="unit_sq_ft" class="form-control" placeholder="Enter square footage" min="1" step="0.01" value="{{ old('unit_sq_ft', $unit->unit_sq_ft) }}" required />
						</div>
						
						<div class="col-md-6 mb-3">
							<label class="form-label required">Maximum Subdivisions:</label>
							<input type="number" name="max_subdivisions" class="form-control" placeholder="Enter number" min="0" max="20" value="{{ old('max_subdivisions', $unit->max_subdivisions) }}" required />
						</div>
						
						<div class="col-md-6 mb-3">
							<label class="form-label required">Minimum Square Footage:</label>
							<input type="number" name="min_sq_ft" class="form-control" placeholder="Enter minimum sq ft" min="1" step="0.01" value="{{ old('min_sq_ft', $unit->min_sq_ft) }}" required />
						</div>

						<div class="col-md-6 mb-3">
							<label class="form-label">Monthly rent (KES):</label>
							<input type="number" name="monthly_rent" class="form-control" placeholder="Leave blank if not set" min="0" step="0.01" value="{{ old('monthly_rent', $unit->monthly_rent) }}" />
						</div>
						
						<!--begin::Floor Selection-->
						<div class="col-12 mb-3">
							<label class="form-label required">Available Floors:</label>
							<div class="row">
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input unit-floor-checkbox" type="radio" name="floors" value="Basement" id="floor_basement" {{ old('floors', $unit->floors) == 'Basement' ? 'checked' : '' }}>
										<label class="form-check-label" for="floor_basement">Basement</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input unit-floor-checkbox" type="radio" name="floors" value="Ground Floor" id="floor_ground" {{ old('floors', $unit->floors) == 'Ground Floor' ? 'checked' : '' }}>
										<label class="form-check-label" for="floor_ground">Ground Floor</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input unit-floor-checkbox" type="radio" name="floors" value="Mezzanine" id="floor_mezzanine" {{ old('floors', $unit->floors) == 'Mezzanine' ? 'checked' : '' }}>
										<label class="form-check-label" for="floor_mezzanine">Mezzanine</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input unit-floor-checkbox" type="radio" name="floors" value="1st Floor" id="floor_1st" {{ old('floors', $unit->floors) == '1st Floor' ? 'checked' : '' }}>
										<label class="form-check-label" for="floor_1st">1st Floor</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input unit-floor-checkbox" type="radio" name="floors" value="2nd Floor" id="floor_2nd" {{ old('floors', $unit->floors) == '2nd Floor' ? 'checked' : '' }}>
										<label class="form-check-label" for="floor_2nd">2nd Floor</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input unit-floor-checkbox" type="radio" name="floors" value="3rd Floor" id="floor_3rd" {{ old('floors', $unit->floors) == '3rd Floor' ? 'checked' : '' }}>
										<label class="form-check-label" for="floor_3rd">3rd Floor</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input unit-floor-checkbox" type="radio" name="floors" value="4th Floor" id="floor_4th" {{ old('floors', $unit->floors) == '4th Floor' ? 'checked' : '' }}>
										<label class="form-check-label" for="floor_4th">4th Floor</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input unit-floor-checkbox" type="radio" name="floors" value="5th Floor" id="floor_5th" {{ old('floors', $unit->floors) == '5th Floor' ? 'checked' : '' }}>
										<label class="form-check-label" for="floor_5th">5th Floor</label>
									</div>
								</div>
							</div>
							
							<!--begin::Custom floor input-->
							<div class="input-group mt-3">
								<span class="input-group-text">Other Floor:</span>
								<input type="text" name="custom_floors" class="form-control" placeholder="Add custom floors" value="{{ old('custom_floors', $unit->custom_floors) }}" />
							</div>
							<div class="form-text">Type floor name if not listed above</div>
						</div>
						<!--end::Floor Selection-->
						
						<div class="col-md-12 mb-3">
							<label class="form-label required">Unit Status:</label>
							<div class="row">
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input" type="radio" name="status" value="Available" id="status_available" {{ old('status', $unit->status) == 'Available' ? 'checked' : '' }}>
										<label class="form-check-label" for="status_available">Available</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input" type="radio" name="status" value="Occupied" id="status_occupied" {{ old('status', $unit->status) == 'Occupied' ? 'checked' : '' }}>
										<label class="form-check-label" for="status_occupied">Occupied</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input" type="radio" name="status" value="Under Maintenance" id="status_maintenance" {{ old('status', $unit->status) == 'Under Maintenance' ? 'checked' : '' }}>
										<label class="form-check-label" for="status_maintenance">Under Maintenance</label>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input" type="radio" name="status" value="Reserved" id="status_reserved" {{ old('status', $unit->status) == 'Reserved' ? 'checked' : '' }}>
										<label class="form-check-label" for="status_reserved">Reserved</label>
									</div>
								</div>
							</div>
						</div>
						
						<div class="col-md-12 mb-3">
							<label class="form-label">Unit Description:</label>
							<textarea name="description" class="form-control" rows="3" placeholder="Additional details about this unit">{{ old('description', $unit->description) }}</textarea>
						</div>
					</div>
					<!--end::Unit Details-->

					<!--begin::Actions-->
					<div class="d-flex justify-content-end mt-10">
						<a href="{{ route('commercial-units.index') }}" class="btn btn-light me-3">Cancel</a>
						<button type="submit" class="btn btn-primary" id="submit_commercial_unit_edit">
							<span class="indicator-label">
								Update Commercial Unit
							</span>
							<span class="indicator-progress">
								Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
							</span>
						</button>
					</div>
					<!--end::Actions-->
				</form>
			</div>
			<!--end::Card body-->
		</div>
		<!--end::Card-->
	</div>
	<!--end::Container-->
</div>
<!--end::Content-->

@endsection
