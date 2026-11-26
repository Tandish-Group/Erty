@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Agent Settings</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    @businessCan('crm_management', 'view')<a href="/customer_relationship" class="text-muted text-hover-primary">CRM</a>@else<span class="text-muted">CRM</span>@endbusinessCan
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="/agents" class="text-muted text-hover-primary">Agent Management</a>
                </li>
                <li class="breadcrumb-item text-dark">Settings</li>
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
        @include('pages.business.agents.partials.nav')
        
        <!--begin::Info Banner-->
        <div class="card bg-light-primary mb-8">
            <div class="card-body d-flex align-items-center py-8">
                <span class="svg-icon svg-icon-3x svg-icon-primary me-5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path opacity="0.3" d="M20.5543 4.37824L12.1798 2.02473C12.0626 1.99176 11.9376 1.99176 11.8203 2.02473L3.44572 4.37824C3.18118 4.45258 3 4.6807 3 4.93945V13.569C3 14.6914 3.48509 15.8404 4.4417 16.984C5.17231 17.8575 6.18314 18.7345 7.446 19.5909C9.56752 21.0295 11.6566 21.912 11.7445 21.9488C11.8258 21.9829 11.9129 22 12.0001 22C12.0872 22 12.1744 21.983 12.2557 21.9488C12.3435 21.912 14.4326 21.0295 16.5541 19.5909C17.8169 18.7345 18.8277 17.8575 19.5584 16.984C20.515 15.8404 21 14.6914 21 13.569V4.93945C21 4.6807 20.8189 4.45258 20.5543 4.37824Z" fill="black"/>
                        <path d="M10.5606 11.3042L9.57283 10.3018C9.28174 10.0065 8.80522 10.0065 8.51412 10.3018C8.22897 10.5912 8.22897 11.0559 8.51412 11.3452L10.4182 13.2773C10.8099 13.6747 11.451 13.6747 11.8427 13.2773L15.4859 9.58051C15.771 9.29117 15.771 8.82648 15.4859 8.53714C15.1948 8.24176 14.7183 8.24176 14.4272 8.53714L11.7002 11.3042C11.3869 11.6221 10.874 11.6221 10.5606 11.3042Z" fill="black"/>
                    </svg>
                </span>
                <div class="d-flex flex-column flex-grow-1">
                    <span class="d-flex flex-row justify-content-between">
                        <h3 class="fw-bold text-gray-800 mb-1">Configure Agent Management Settings</h3>
                        <a href="/agents" class="btn btn-sm btn-light-info" target="_blank" rel="noopener noreferrer">Back to Agents</a>
                    </span>
                    <div class="fw-semibold text-gray-600">Define departments, roles, locations, and specializations that will be available when creating new agents.</div>
                </div>
            </div>
        </div>
        <!--end::Info Banner-->
        
        <div class="row g-6 g-xl-9">
            <!-- Departments Card -->
            <div class="col-xl-6">
                <div class="card card-flush h-100 shadow-sm">
                    <div class="card-header d-flex flex-row justify-content-between border-0 pt-7">
                        <div class="card-title">
                            <div class="d-flex align-items-center">
                                <span class="svg-icon svg-icon-2x svg-icon-primary me-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path opacity="0.3" d="M21 22H14C13.4 22 13 21.6 13 21V3C13 2.4 13.4 2 14 2H21C21.6 2 22 2.4 22 3V21C22 21.6 21.6 22 21 22Z" fill="black"/>
                                        <path d="M10 22H3C2.4 22 2 21.6 2 21V3C2 2.4 2.4 2 3 2H10C10.6 2 11 2.4 11 3V21C11 21.6 10.6 22 10 22Z" fill="black"/>
                                    </svg>
                                </span>
                                <h3 class="fw-bold m-0">Departments</h3>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_add_department">
                                <span class="svg-icon svg-icon-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="currentColor"/>
                                        <rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="currentColor"/>
                                    </svg>
                                </span>
                                Add Department
                            </button>
                        </div>
                    </div>
                    <div class="card-body py-4">
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="kt_departments_table">
                                <thead>
                                    <tr class="fw-bold text-muted bg-light">
                                        <th class="ps-4 min-w-150px rounded-start">Department Name</th>
                                        <th class="min-w-100px">Agents</th>
                                        <th class="min-w-100px text-end rounded-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic content will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Roles/Positions Card -->
            <div class="col-xl-6">
                <div class="card card-flush h-100 shadow-sm">
                    <div class="card-header d-flex flex-row justify-content-between border-0 pt-7">
                        <div class="card-title">
                            <div class="d-flex align-items-center">
                                <span class="svg-icon svg-icon-2x svg-icon-success me-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M6.28548 15.0861C7.34369 13.1814 9.35142 12 11.5304 12H12.4696C14.6486 12 16.6563 13.1814 17.7145 15.0861L19.3493 18.0287C20.0899 19.3618 19.1259 21 17.601 21H6.39903C4.87406 21 3.91012 19.3618 4.65071 18.0287L6.28548 15.0861Z" fill="black"/>
                                        <rect opacity="0.3" x="8" y="3" width="8" height="8" rx="4" fill="black"/>
                                    </svg>
                                </span>
                                <h3 class="fw-bold m-0">Roles/Positions</h3>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#kt_modal_add_role">
                                <span class="svg-icon svg-icon-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="currentColor"/>
                                        <rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="currentColor"/>
                                    </svg>
                                </span>
                                Add Role
                            </button>
                        </div>
                    </div>
                    <div class="card-body py-4">
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="kt_roles_table">
                                <thead>
                                    <tr class="fw-bold text-muted bg-light">
                                        <th class="ps-4 min-w-150px rounded-start">Role Name</th>
                                        <th class="min-w-100px">Level</th>
                                        <th class="min-w-100px text-end rounded-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic content will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Work Locations Card -->
            <div class="col-xl-12">
                <div class="card card-flush h-100 shadow-sm">
                    <div class="card-header d-flex flex-row justify-content-between border-0 pt-7">
                        <div class="card-title">
                            <div class="d-flex align-items-center">
                                <span class="svg-icon svg-icon-2x svg-icon-info me-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path opacity="0.3" d="M18.0624 15.3453L13.1624 20.7453C12.5624 21.4453 11.5624 21.4453 10.9624 20.7453L6.06242 15.3453C4.56242 13.6453 3.76242 11.4453 4.06242 8.94534C4.56242 5.34534 7.46242 2.44534 11.0624 2.04534C15.8624 1.54534 19.9624 5.24534 19.9624 9.94534C20.0624 12.0453 19.2624 13.9453 18.0624 15.3453Z" fill="black"/>
                                        <path d="M12.0624 13.0453C13.7193 13.0453 15.0624 11.7022 15.0624 10.0453C15.0624 8.38849 13.7193 7.04535 12.0624 7.04535C10.4056 7.04535 9.06241 8.38849 9.06241 10.0453C9.06241 11.7022 10.4056 13.0453 12.0624 13.0453Z" fill="black"/>
                                    </svg>
                                </span>
                                <h3 class="fw-bold m-0">Work Locations</h3>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#kt_modal_add_location">
                                <span class="svg-icon svg-icon-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="currentColor"/>
                                        <rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="currentColor"/>
                                    </svg>
                                </span>
                                Add Location
                            </button>
                        </div>
                    </div>
                    <div class="card-body py-4">
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="kt_locations_table">
                                <thead>
                                    <tr class="fw-bold text-muted bg-light">
                                        <th class="ps-4 min-w-150px rounded-start">Location Name</th>
                                        <th class="min-w-200px">Address</th>
                                        <th class="min-w-100px text-end rounded-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic content will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Modal - Add Department -->
<div class="modal fade" id="kt_modal_add_department" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content rounded">
            <form class="form" id="kt_modal_add_department_form">
                <div class="modal-header pb-0 border-0 justify-content-end">
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3" width="16" height="2" rx="1" transform="rotate(-45 6 17.3)" fill="currentColor"/>
                                <rect x="7.4" y="6" width="16" height="2" rx="1" transform="rotate(45 7.4 6)" fill="currentColor"/>
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body scroll-y px-10 px-lg-15 pt-0 pb-15">
                    <div class="mb-13 text-center">
                        <h1 class="mb-3">Add Department</h1>
                        <div class="text-muted fw-semibold fs-5">Configure a new department for agent organization</div>
                    </div>
                    <div class="fv-row mb-8">
                        <label class="required fs-6 fw-semibold mb-2">Department Name</label>
                        <input type="text" class="form-control form-control-solid" name="name" placeholder="Enter department name" />
                    </div>
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-semibold mb-2">Description</label>
                        <textarea class="form-control form-control-solid" name="description" rows="3" placeholder="Enter department description"></textarea>
                    </div>
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-semibold mb-2">Head of Department</label>
                        <input type="text" class="form-control form-control-solid" name="department_head" placeholder="Enter department head name" />
                    </div>
                    
                    <div class="text-center">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Save Department</span>
                            <span class="indicator-progress">Please wait...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal - Add Role -->
<div class="modal fade" id="kt_modal_add_role" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content rounded">
            <form class="form" id="kt_modal_add_role_form">
                <div class="modal-header pb-0 border-0 justify-content-end">
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3" width="16" height="2" rx="1" transform="rotate(-45 6 17.3)" fill="currentColor"/>
                                <rect x="7.4" y="6" width="16" height="2" rx="1" transform="rotate(45 7.4 6)" fill="currentColor"/>
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body scroll-y px-10 px-lg-15 pt-0 pb-15">
                    <div class="mb-13 text-center">
                        <h1 class="mb-3">Add Role/Position</h1>
                        <div class="text-muted fw-semibold fs-5">Define a new role or position for agents</div>
                    </div>
                    
                    <div class="fv-row mb-8">
                        <label class="required fs-6 fw-semibold mb-2">Role Name</label>
                        <input type="text" class="form-control form-control-solid" name="name" placeholder="Enter role name" />
                    </div>
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-semibold mb-2">Description</label>
                        <textarea class="form-control form-control-solid" name="description" rows="3" placeholder="Enter role description"></textarea>
                    </div>
                    <div class="fv-row mb-8">
                        <label class="required fs-6 fw-semibold mb-2">Level</label>
                        <select class="form-select form-select-solid" name="level" data-control="select2" data-placeholder="Select level">
                            <option></option>
                            <option value="entry">Entry Level</option>
                            <option value="junior">Junior</option>
                            <option value="mid">Mid Level</option>
                            <option value="senior">Senior</option>
                            <option value="lead">Team Lead</option>
                            <option value="manager">Manager</option>
                            <option value="director">Director</option>
                            <option value="executive">Executive</option>
                        </select>
                    </div>
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-semibold mb-2">Department</label>
                        <select class="form-select form-select-solid" name="department_id" data-control="select2" data-placeholder="Select department">
                            <option></option>
                            @foreach($agentDepartments as $department)
                                <option value="{{$department->id}}">{{$department->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="text-center">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <span class="indicator-label">Save Role</span>
                            <span class="indicator-progress">Please wait...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal - Add Work Location -->
<div class="modal fade" id="kt_modal_add_location" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content rounded">
            <form class="form" id="kt_modal_add_location_form">
                <div class="modal-header pb-0 border-0 justify-content-end">
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3" width="16" height="2" rx="1" transform="rotate(-45 6 17.3)" fill="currentColor"/>
                                <rect x="7.4" y="6" width="16" height="2" rx="1" transform="rotate(45 7.4 6)" fill="currentColor"/>
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body scroll-y px-10 px-lg-15 pt-0 pb-15">
                    <div class="mb-13 text-center">
                        <h1 class="mb-3">Add Work Location</h1>
                        <div class="text-muted fw-semibold fs-5">Register a new office or work location</div>
                    </div>
                    
                    <div class="fv-row mb-8">
                        <label class="required fs-6 fw-semibold mb-2">Location Name</label>
                        <input type="text" class="form-control form-control-solid" name="name" placeholder="e.g., Main Office, Branch 1" />
                    </div>
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-semibold mb-2">Address</label>
                        <textarea class="form-control form-control-solid" name="address" rows="2" placeholder="Enter full address"></textarea>
                    </div>
                    <div class="row mb-8">
                        <div class="col-md-6 fv-row">
                            <label class="fs-6 fw-semibold mb-2">City</label>
                            <input type="text" class="form-control form-control-solid" name="city" placeholder="Enter city" />
                        </div>
                        <div class="col-md-6 fv-row">
                            <label class="fs-6 fw-semibold mb-2">Country</label>
                            <input type="text" class="form-control form-control-solid" name="country" placeholder="Enter country" />
                        </div>
                    </div>
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-semibold mb-2">Type</label>
                        <select class="form-select form-select-solid" name="location_type" data-control="select2" data-placeholder="Select type">
                            <option></option>
                            <option value="office">Office</option>
                            <option value="branch">Branch</option>
                            <option value="remote">Remote</option>
                            <option value="hybrid">Hybrid</option>
                        </select>
                    </div>
                    
                    <div class="text-center">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <span class="indicator-label">Save Location</span>
                            <span class="indicator-progress">Please wait...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


@endsection