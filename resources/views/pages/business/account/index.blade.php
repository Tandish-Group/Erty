@extends('pages.business.layouts.app')

@section('content')

<!--begin::Header-->
<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
	<!--begin::Container-->
	<div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
		<!--begin::Page title-->
		<div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
			<!--begin::Heading-->
			<h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Business settings</h1>
			<!--end::Heading-->
			<!--begin::Breadcrumb-->
			<ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
				<li class="breadcrumb-item text-muted">
					<a href="#" class="text-muted">Home</a>
				</li>
				<li class="breadcrumb-item text-dark">Business settings</li>
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
		<!--begin::Basic info-->
		<div class="card mb-5 mb-xl-10">
		    <div class="card-header card-header-stretch justify-content-between">
		        <div class="card-title m-0">
					<h3 class="fw-bolder m-0">Business details</h3>
				</div>
		        <div class="card-toolbar">
		            <ul class="nav nav-tabs nav-line-tabs nav-stretch fs-6 border-0">
		                <li class="nav-item">
		                    <a class="nav-link active" data-bs-toggle="tab" href="#kt_tab_pane_7">Overview</a>
		                </li>
                		@if($canEditBusiness)
							<li class="nav-item">
								<a class="nav-link" data-bs-toggle="tab" href="#kt_tab_pane_8">Edit</a>
							</li>
						@endif
		            </ul>
		        </div>
		    </div>
		    <div class="card-body">
		        <div class="tab-content" id="myTabContent">
		            <div class="tab-pane fade show active" id="kt_tab_pane_7" role="tabpanel">
		               <div class="card-body p-9">
							<p class="text-muted fs-7 mb-7">These settings apply to the whole business and everyone on the team. Your own name, photo, phone and sign-in are on <a href="{{ route('business.profile.show') }}">My profile</a>.</p>
							<!--begin::Input group-->
							<div class="row mb-7">
								<!--begin::Label-->
								<label class="col-lg-4 fw-bold text-muted">Business</label>
								<!--end::Label-->
								<!--begin::Col-->
								<div class="col-lg-8 fv-row">
									<span class="fw-bold text-gray-800 fs-6">{{$user->business_name}}</span> <br>
								</div>
								<!--end::Col-->
							</div>
							<!--end::Input group-->
							<!--begin::Input group-->
							<div class="row mb-7">
								<label class="col-lg-4 fw-bold text-muted">Business email</label>
								<div class="col-lg-8"><span class="fw-bolder fs-6 text-gray-800">{{ $user->business_email ?: 'Not recorded' }}</span></div>
							</div>
							<!--end::Input group-->
							<!--begin::Input group-->
							@if($user->account_type == 'agency')
							<div class="row mb-7">
								<!--begin::Label-->
								<label class="col-lg-4 fw-bold text-muted">Business Site</label>
								<!--end::Label-->
								<!--begin::Col-->
								<div class="col-lg-8">
									<a href="#" class="fw-bold fs-6 text-gray-800 text-hover-primary">{{$user->business_site}}</a>
								</div>
								<!--end::Col-->
							</div>
							@endif
							<!--end::Input group-->
							<!--begin::Input group-->
							<div class="row mb-7">
								<!--begin::Label-->
								<label class="col-lg-4 fw-bold text-muted">Account Type</label>
								<!--end::Label-->
								<!--begin::Col-->
								<div class="col-lg-8">
									<span class="fw-bolder fs-6 text-gray-800">{{ucfirst($user->account_type)}} Account</span>
								</div>
								<!--end::Col-->
							</div>
							<!--end::Input group-->
							<!--begin::Input group-->
							<div class="row mb-7">
								<!--begin::Label-->
								<label class="col-lg-4 fw-bold text-muted">Business Description</label>
								<!--end::Label-->
								<!--begin::Col-->
								<div class="col-lg-8">
									<span class="fw-bolder fs-6 text-gray-800">{{$user->business_description}}</span>
								</div>
								<!--end::Col-->
							</div>
							<!--end::Input group-->
						</div>
		            </div>
					@if($canEditBusiness)
		            <div class="tab-pane fade" id="kt_tab_pane_8" role="tabpanel">
		                @include('pages.business.account.settings.business')
		            </div>
					@endif

		        </div>
		    </div>
		</div>
		
		<!--end::Basic info-->
		<!--begin::Payment Methods-->
		@businessOwner
		@include('pages.business.account.settings.payment_methods')
		@endbusinessOwner
		<!--end::Payment Methods-->
		<!--begin::Sub-Account-->
		@if(auth()->user()->hasBusinessPermission('subaccount_management', 'view') || auth()->user()->hasBusinessPermission('role_management', 'view'))
		<div class="card mb-5 mb-xl-10">
			<div class="card-body p-9 d-flex flex-wrap justify-content-between align-items-center gap-4">
				<div>
					<h3 class="fw-bolder mb-1">Team &amp; roles</h3>
					<div class="text-muted fs-7">Invite staff, choose their role and control which sections each person can open.</div>
				</div>
				<a href="{{ route('business.team.index') }}" class="btn btn-light-primary">Manage team</a>
			</div>
		</div>
		@endif
		<!--end::Sub-Account-->
		<!--begin::Storage Management-->
		@include('pages.business.account.settings.storage')
		<!--end::Storage Management-->
		<!--begin::Modules-->
		@include('pages.business.account.settings.modules')
		<!--end::Modules-->
		<!--begin::Deactivate Account-->
		@businessOwner
		@include('pages.business.account.settings.delete')
		@endbusinessOwner
		<!--end::Deactivate Account-->
	</div>
	<!--end::Container-->
</div>
<!--end::Content-->


@endsection