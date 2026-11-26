@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
	<!--begin::Container-->
	<div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
		<!--begin::Page title-->
		<div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
			<!--begin::Heading-->
			<h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">View Floor Plans</h1>
			<!--end::Heading-->
			<!--begin::Breadcrumb-->
			<ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
				<li class="breadcrumb-item text-muted">Home</li>
				<li class="breadcrumb-item text-muted">Property Complex</li>
				<li class="breadcrumb-item text-dark">View Floor Plans</li>
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
        <div class="card shadow-sm">
		    <div class="card-body">
		    	@if (session('error'))
		            <div class="alert fade show alert-danger">
		                <!--begin::Wrapper-->
		                <div class="d-flex flex-column">
		                    <!--begin::Content-->
		                    <span>{{ session('error') }}</span>
		                    <!--end::Content-->
		                </div>
		                <!--end::Wrapper-->
		            </div>
		        @endif
		        @if (session('success'))
		            <div class="alert fade show alert-success">
		                <!--begin::Wrapper-->
		                <div class="d-flex flex-column">
		                    <!--begin::Content-->
		                    <span>{{ session('success') }}</span>
		                    <!--end::Content-->
		                </div>
		                <!--end::Wrapper-->
		            </div>
		        @endif
		    	<div class="row">
		        	<div class="col-xl-12">
						<div class="d-flex flex-wrap justify-content-between">
							<a href="{{ URL::previous() }}" class="mb-4">
								<button class="btn btn-sm btn-light" style="text-align: right;">
									<span class="svg-icon svg-icon-2 rotate-180" style="margin-right: 0 !important;">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
											<rect opacity="0.5" x="6" y="11" width="13" height="2" rx="1" fill="black"></rect>
											<path d="M8.56569 11.4343L12.75 7.25C13.1642 6.83579 13.1642 6.16421 12.75 5.75C12.3358 5.33579 11.6642 5.33579 11.25 5.75L5.70711 11.2929C5.31658 11.6834 5.31658 12.3166 5.70711 12.7071L11.25 18.25C11.6642 18.6642 12.3358 18.6642 12.75 18.25C13.1642 17.8358 13.1642 17.1642 12.75 16.75L8.56569 12.5657C8.25327 12.2533 8.25327 11.7467 8.56569 11.4343Z" fill="black"></path>
										</svg>
									</span>
								</button>
							</a>
							<div class="d-flex flex-row">
                                <a href="#" class="mb-4">
									<button class="btn btn-sm btn-light-info" style="text-align: right; margin-right: 2em;" data-bs-toggle="modal" data-bs-target="#kt_modal_upload">
										<span class="svg-icon svg-icon-2 rotate-180" style="margin-right: 0 !important;">
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path opacity="0.3" d="M10 4H21C21.6 4 22 4.4 22 5V7H10V4Z" fill="black"/>
                                                <path d="M10.4 3.60001L12 6H21C21.6 6 22 6.4 22 7V19C22 19.6 21.6 20 21 20H3C2.4 20 2 19.6 2 19V4C2 3.4 2.4 3 3 3H9.2C9.7 3 10.2 3.20001 10.4 3.60001ZM16 12H13V9C13 8.4 12.6 8 12 8C11.4 8 11 8.4 11 9V12H8C7.4 12 7 12.4 7 13C7 13.6 7.4 14 8 14H11V17C11 17.6 11.4 18 12 18C12.6 18 13 17.6 13 17V14H16C16.6 14 17 13.6 17 13C17 12.4 16.6 12 16 12Z" fill="black"/>
                                                <path opacity="0.3" d="M11 14H8C7.4 14 7 13.6 7 13C7 12.4 7.4 12 8 12H11V14ZM16 12H13V14H16C16.6 14 17 13.6 17 13C17 12.4 16.6 12 16 12Z" fill="black"/>
                                            </svg>
										</span>
									</button>
								</a>
								<a href="#" class="mb-4" id="deleteButton">
									<button class="btn btn-sm btn-light-danger" style="text-align: right; margin-right: 2em;" data-bs-toggle="modal" data-bs-target="#delete-selected">
										<span class="svg-icon svg-icon-2 rotate-180" style="margin-right: 0 !important;">
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
												<path d="M5 9C5 8.44772 5.44772 8 6 8H18C18.5523 8 19 8.44772 19 9V18C19 19.6569 17.6569 21 16 21H8C6.34315 21 5 19.6569 5 18V9Z" fill="black"/>
												<path opacity="0.5" d="M5 5C5 4.44772 5.44772 4 6 4H18C18.5523 4 19 4.44772 19 5V5C19 5.55228 18.5523 6 18 6H6C5.44772 6 5 5.55228 5 5V5Z" fill="black"/>
												<path opacity="0.5" d="M9 4C9 3.44772 9.44772 3 10 3H14C14.5523 3 15 3.44772 15 4V4H9V4Z" fill="black"/>
											</svg>
										</span>
									</button>
								</a>
								<a href="#" class="mb-4">
									<button class="btn btn-sm btn-light" style="text-align: right;" id="selectButton">
										<span class="svg-icon svg-icon-2 rotate-180" style="margin-right: 0 !important;">
											<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
												<rect opacity="0.3" x="2" y="2" width="20" height="20" rx="5" fill="black"/>
												<path d="M10.4343 12.4343L8.75 10.75C8.33579 10.3358 7.66421 10.3358 7.25 10.75C6.83579 11.1642 6.83579 11.8358 7.25 12.25L10.2929 15.2929C10.6834 15.6834 11.3166 15.6834 11.7071 15.2929L17.25 9.75C17.6642 9.33579 17.6642 8.66421 17.25 8.25C16.8358 7.83579 16.1642 7.83579 15.75 8.25L11.5657 12.4343C11.2533 12.7467 10.7467 12.7467 10.4343 12.4343Z" fill="black"/>
											</svg>
										</span>
									</button>
								</a>
							</div>
						</div>
		        		<!--begin::Section-->

                        <!--begin::Modal-->
                        <div class="modal fade" tabindex="-1" id="kt_modal_upload" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered mw-650px">
                                <div class="modal-content">
                                    <div class="modal-body" style="padding: 0 !important;">
                                        <div id="drag-drop-area" class="{{$Complex->id}}"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Modal-->

						<div class="mb-17 mt-2">
							<div class="row g-10">
                                @if(count($ComplexFloorPlans) > 0)
                                    @foreach($ComplexFloorPlans as $key=>$image)

                                        <!--begin::Col-->
                                        <div class="col-md-4">
                                            <!--begin::Hot sales post-->
                                            <input type="checkbox" value="{{$image->plan}}" style="display: block; margin: 1em auto; cursor: pointer;">
                                            <div class="card-xl-stretch me-md-6">
                                                <!--begin::Overlay-->
                                                <a class="d-block overlay" data-fslightbox="lightbox-hot-sales" href="{{ asset('storage/'.$planDirectory.'/'.$image->plan) }}">
                                                    <!--begin::Image-->
                                                    <div class="overlay-wrapper bgi-no-repeat bgi-position-center bgi-size-cover card-rounded min-h-175px" style="background-image:url('{{ asset('storage/'.$planDirectory.'/thumbnail/'.$image->plan) }}')"></div>
                                                    <!--end::Image-->
                                                    <!--begin::Action-->
                                                    <div class="overlay-layer card-rounded bg-dark bg-opacity-25">
                                                        <i class="bi bi-eye-fill fs-2x text-white"></i>
                                                    </div>
                                                    <!--end::Action-->
                                                </a>
                                                <!--end::Overlay-->
                                            </div>
                                            <div class="d-flex flex-row mt-7" style="justify-content: center">
                                                <span>
                                                    <a href="#" class="text-hover-primary" style="margin-left: 1em" data-bs-toggle="modal" data-bs-target="#delete-{{$key+1}}">
                                                        <i class="las la-trash-alt fs-2x text-danger" style="cursor: pointer; font-size: 1.5em !important;"></i>
                                                    </a>
                                                </span>
                                            </div>
                                            <!--end::Hot sales post-->
                                        </div>

                                        <!--begin::Modal-->

                                        <div class="modal fade" tabindex="-1" id="delete-{{$key+1}}">
                                            <div class="modal-dialog modal-sm">
                                                <div class="modal-content">
                                                    <form method="POST" action="{{ route('plans_complex.destroy', $image->id) }}">
                                                    @csrf
                                                    @method('DELETE')

                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Delete Image</h5>
                                                            
                                                            <!--begin::Close-->
                                                            <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="Close">
                                                                <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
                                                                <span class="svg-icon svg-icon-1">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                                        <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                                                        <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                                                                    </svg>
                                                                </span>
                                                                <!--end::Svg Icon-->
                                                            </div>
                                                            <!--end::Close-->
                                                        </div>

                                                        <div class="modal-body">
                                                            <h5 class="breadcrumb-item text-dark">Are you sure you want to delete this image?</h5> 

                                                            <div class="card-xl-stretch me-md-6 mt-7">
                                                                <!--begin::Overlay-->
                                                                <a class="d-block overlay" href="#">
                                                                    <!--begin::Image-->
                                                                    <div class="overlay-wrapper bgi-no-repeat bgi-position-center bgi-size-cover card-rounded min-h-100px" style="background-image:url('{{ asset('storage/'.$planDirectory.'/thumbnail/'.$image->plan) }}')"></div>
                                                                    <!--end::Image-->
                                                                </a>
                                                                <!--end::Overlay-->
                                                            </div>
                                                            
                                                        </div>

                                                        <div class="modal-footer" style="justify-content: center !important;">
                                                            <button type="submit" class="btn btn-danger me-10 delete_image" style="width: 100%">
                                                                <span class="indicator-label">
                                                                    Delete
                                                                </span>
                                                                <span class="indicator-progress">
                                                                    Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                                                </span>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

<!--end:: Modal-->

                                        <!--end::Col-->

                                    @endforeach
                                @else
                                    <div class="text-center px-5">
                                        <p class="mt-14">No Floor Plans Currently</p>
                                    </div>
                                @endif
                                {{-- One bulk-delete dialog for the whole gallery (it used to be repeated per image). --}}
                                        <div class="modal fade" tabindex="-1" id="delete-selected">
                                            <div class="modal-dialog modal-sm">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Delete Selected Images</h5>
                                                        
                                                        <!--begin::Close-->
                                                        <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="Close">
                                                            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
                                                            <span class="svg-icon svg-icon-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                                                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                                                                </svg>
                                                            </span>
                                                            <!--end::Svg Icon-->
                                                        </div>
                                                        <!--end::Close-->
                                                    </div>

                                                    <div class="modal-body">
                                                        <h5 class="breadcrumb-item text-dark">Are you sure you want to delete these images?</h5> 
                                                    </div>

                                                    <div class="modal-footer" style="justify-content: center !important;">
                                                        <button type="button" class="btn btn-danger me-10" id="delete_selected_images" style="width: 100%">
                                                            <span class="indicator-label">
                                                                Delete
                                                            </span>
                                                            <span class="indicator-progress">
                                                                Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

							</div>
							<!--end::Row-->
						</div>
						<!--end::Section-->
		        	</div>
		        </div>
		    </div>
		</div>
        
	</div>
	<!--end::Container-->
</div>
<!--end::Content-->

@endsection