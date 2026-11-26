@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
	<!--begin::Container-->
	<div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
		<!--begin::Page title-->
		<div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
			<!--begin::Heading-->
			<h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Property portfolio</h1>
			<!--end::Heading-->
			<!--begin::Breadcrumb-->
			<ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
				<li class="breadcrumb-item text-muted">Home</li>
				<li class="breadcrumb-item text-muted">Property Listing</li>
				<li class="breadcrumb-item text-dark">Portfolio</li>
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-4 mb-4">
            <div><h2 class="mb-0">Property portfolio</h2></div>
            @businessCan('complex_management', 'create')<a href="{{ route('complex.create') }}" class="btn btn-primary">Create property</a>@endbusinessCan
        </div>
        <section class="portfolio-overview-stats row g-3 mb-6" aria-label="Portfolio overview">
            <div class="col-12 col-sm-4">
                <div class="portfolio-stat-card">
                    <span class="portfolio-stat-label">Properties</span>
                    <strong>{{ number_format($portfolioTotal) }}</strong>
                    <small>In your portfolio</small>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="portfolio-stat-card">
                    <span class="portfolio-stat-label">Confirmed</span>
                    <strong>{{ number_format($portfolioReady) }}</strong>
                    <small>Ready to manage</small>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="portfolio-stat-card">
                    <span class="portfolio-stat-label">Needs review</span>
                    <strong>{{ number_format($portfolioTotal - $portfolioReady) }}</strong>
                    <small>Setup needs attention</small>
                </div>
            </div>
        </section>
        <nav class="d-flex flex-wrap gap-3 mb-6" aria-label="Portfolio tools">
            @businessCan('complex_management', 'import')<button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#kt_modal_upload_complexes">Import properties</button>@endbusinessCan
            @businessCan('complex_management', 'export')<a href="/data-complexes/download" class="btn btn-sm btn-light">Export portfolio</a>@endbusinessCan
            <a href="/commercial-units" class="btn btn-sm btn-light">Commercial inventory</a>
            <a href="{{ route('residential-units.index') }}" class="btn btn-sm btn-light">Residential inventory</a>
            @businessCan('expense_management', 'view')<a href="{{ route('business.expenses.index') }}" class="btn btn-sm btn-light">Property expenses</a>@endbusinessCan
            @businessCan('listing_history', 'view')<a href="/history" class="btn btn-sm btn-light">Property history</a>@endbusinessCan
            @businessCan('service_charge_management', 'view')<a href="/service-charges" class="btn btn-sm btn-light">Service charges</a>@endbusinessCan
        </nav>
        <div class="card" data-portfolio-results>
            <div class="card-body">
                <form data-live-filter method="GET" action="{{ route('complex.index') }}" class="row g-3 align-items-end mb-6" role="search">
                    <div class="col-12 col-lg-4">
                        <label for="portfolio-search" class="form-label">Search portfolio</label>
                        <input id="portfolio-search" name="q" maxlength="200" value="{{ request('q') }}" class="form-control" placeholder="Property, reference or location">
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="portfolio-setup" class="form-label">Setup status</label>
                        <select id="portfolio-setup" name="setup" class="form-select">
                            <option value="">All statuses</option>
                            <option value="review" @selected(request('setup') === 'review')>Needs review</option>
                            <option value="ready" @selected(request('setup') === 'ready')>Confirmed</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="portfolio-composition" class="form-label">Composition</label>
                        <select id="portfolio-composition" name="composition" class="form-select">
                            <option value="">All properties</option>
                            @foreach(['residential' => 'Residential only', 'houses' => 'With houses', 'commercial' => 'Commercial only', 'combined' => 'Mixed-use'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('composition') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($hasAssignments ?? false)
                    <div class="col-6 col-lg-2">
                        <label for="portfolio-managed" class="form-label">Show</label>
                        <select id="portfolio-managed" name="managed" class="form-select">
                            <option value="">All properties</option>
                            <option value="mine" @selected(request('managed') === 'mine')>My properties</option>
                        </select>
                    </div>
                    @endif
                    <div class="col-6 col-lg-2">
                        <label for="portfolio-sort" class="form-label">Sort by</label>
                        <select id="portfolio-sort" name="sort" class="form-select">
                            <option value="updated">Recently updated</option>
                            <option value="name" @selected(request('sort') === 'name')>Property name</option>
                            <option value="oldest" @selected(request('sort') === 'oldest')>Oldest update</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Apply</button>
                        <a data-portfolio-link href="{{ route('complex.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-row-bordered align-middle gy-4">
                        <caption class="text-muted">Properties {{ $complexes->firstItem() ?? 0 }}–{{ $complexes->lastItem() ?? 0 }} of {{ number_format($complexes->total()) }} matching results. Available counts reflect recorded unit status, not confirmed leasing eligibility.</caption>
                        <thead><tr class="text-muted"><th scope="col">Property</th><th scope="col">Composition</th><th scope="col">Inventory</th><th scope="col">Setup</th><th scope="col">Management</th><th scope="col" class="text-end">Next action</th></tr></thead>
                        <tbody>
                        @forelse($complexes as $complex)
                            <tr>
                                <td style="min-width:220px;max-width:360px">
                                    <a class="fw-bold text-dark text-hover-primary" href="{{ route('complex.show', $complex->slug) }}">{{ $complex->title }}</a>
                                    <div class="text-muted fs-8">{{ $complex->ref_code }} · {{ $complex->county_specific }} {{ $complex->county }}</div>
                                </td>
                                <td>{{ collect(\App\Services\Properties\ComplexInventoryService::kindsOf($complex))->map(fn ($k) => \App\Services\Properties\ComplexInventoryService::KIND_LABELS[$k])->implode(' · ') ?: $complex->type }}</td>
                                <td class="text-nowrap">
                                    <div>{{ collect(['apartments' => $complex->residential_count - $complex->house_count, 'houses' => $complex->house_count, 'commercial spaces' => $complex->commercial_count])->filter()->map(fn ($n, $k) => $n . ' ' . ($n === 1 ? \Illuminate\Support\Str::singular($k) : $k))->implode(' · ') ?: 'No units yet' }}</div>
                                    <div class="text-muted fs-8">{{ $complex->residential_available + $complex->commercial_available }} recorded available</div>
                                </td>
                                <td><span class="badge {{ $complex->setup_status === 'ready' ? 'badge-light-success' : 'badge-light-warning' }}">{{ $complex->setup_status === 'ready' ? 'Confirmed' : 'Needs review' }}</span></td>
                                <td class="fs-7" data-portfolio-managers>
                                    <div>@if($complex->isCompanyManaged())Company @else{{ trim(($complex->assignedAgent->first_name ?? '').' '.($complex->assignedAgent->last_name ?? '')) }} <span class="text-muted">· primary</span>@endif</div>
                                    @if($myAgent && !$complex->isCompanyManaged() && (int) $complex->assigned_agent_id === $myAgent->id)
                                        <span class="badge badge-light-primary mt-1" data-my-role="primary">You · primary manager</span>
                                    @elseif($myAgent && ($myUnits[$complex->id] ?? 0))
                                        <span class="badge badge-light-info mt-1" data-my-role="sub_agent">You · sub-agent, {{ $myUnits[$complex->id] }} {{ \Illuminate\Support\Str::plural('unit', $myUnits[$complex->id]) }}</span>
                                    @endif
                                    @if($complex->managers->isNotEmpty())<div class="text-muted fs-8 mt-1">Team: {{ $complex->managers->map(fn ($m) => trim($m->first_name.' '.$m->last_name))->implode(', ') }}</div>@endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ $complex->setup_status === 'ready' ? route('complex.show', $complex->slug) : route('complex.setup', $complex->slug) }}" class="btn btn-sm btn-light-primary">{{ $complex->setup_status === 'ready' ? 'Open workspace' : 'Continue setup' }}</a>
                                </td>
                            </tr>
                        @empty
                            @if(!$portfolioTotal && auth()->user()->limitedToAssignedProperties())
                            <tr><td colspan="6" class="text-center py-10" data-no-assignments><h3>No properties have been assigned to you yet</h3><p class="text-muted">Your role only shows the properties you manage. Ask an administrator to assign them to you.</p></td></tr>
                            @else
                            <tr><td colspan="6" class="text-center py-10"><h3>{{ $portfolioTotal ? 'No matching properties' : 'Start your property portfolio' }}</h3><p class="text-muted">{{ $portfolioTotal ? 'Change your search or reset the filters.' : 'Create a property, prepare its units, then review and confirm setup.' }}</p></td></tr>
                            @endif
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <nav class="d-flex justify-content-between align-items-center mt-5" aria-label="Property pages">
                    @if($complexes->previousPageUrl())<a data-portfolio-link class="btn btn-light" rel="prev" href="{{ $complexes->previousPageUrl() }}">Previous</a>@else<span></span>@endif
                    <span class="text-muted">Page {{ $complexes->currentPage() }} of {{ $complexes->lastPage() }}</span>
                    @if($complexes->nextPageUrl())<a data-portfolio-link class="btn btn-light" rel="next" href="{{ $complexes->nextPageUrl() }}">Next</a>@else<span></span>@endif
                </nav>
            </div>
        </div>
        @include('pages.business.complex.include.modals')
	</div>
	<!--end::Container-->
</div>
<!--end::Content-->

{{-- ===== Complex Management Overview Modal ===== --}}
<div class="modal fade" id="complexOverviewModal" tabindex="-1" aria-labelledby="complexOverviewModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">

            {{-- Header --}}
            <div class="modal-header" style="background:#f9fafb;border-top:1px solid #eef0f8;">
                <div class="d-flex align-items-center gap-3">
                    <div class="symbol symbol-45px flex-shrink-0">
                        <span class="symbol-label bg-light-primary">
                            <i class="ki-duotone ki-office-bag fs-2 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                        </span>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-1">
                            <h5 class="modal-title fw-bolder mb-0" id="complexOverviewModalLabel">Complex Management</h5>
                            <span class="badge badge-light-primary fs-9 py-1 px-3">Feature Guide</span>
                        </div>
                        <p class="mb-0 text-muted fs-8">Manage your complexes, units, amenities, expenses, and commercial spaces — all from one place.</p>
                    </div>
                </div>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"></rect>
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"></rect>
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Body --}}
            <div class="modal-body px-8 py-7">

                {{-- Core --}}
                <div class="fw-bold text-uppercase text-muted fs-9 mb-4" style="letter-spacing:.06em;">Core</div>
                <div class="row g-4">

                    {{-- Manage Complexes --}}
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-4 p-4 rounded h-100" style="border:1px dashed #e1e3ea;">
                            <div class="symbol symbol-45px flex-shrink-0">
                                <span class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-office-bag fs-2 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                </span>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold text-dark fs-7">Manage Complexes</span>
                                    <span class="badge badge-light-primary fs-9 py-1 px-2">Core Feature</span>
                                </div>
                                <div class="text-muted fs-8 mb-3">View all complexes you own. See unit counts, occupancy rates, amenities, and expenses at a glance. Each complex has its own detail page with full management tools.</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="#" class="btn btn-sm btn-light-primary fw-semibold">Current Page</a>
                                    @businessCan('complex_management', 'create')<a href="/complex/create" class="btn btn-sm btn-primary fw-semibold">
                                        <i class="ki-duotone ki-plus fs-7 me-1"><span class="path1"></span></i>Create Complex
                                    </a>@endbusinessCan
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Residential Units --}}
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-4 p-4 rounded h-100" style="border:1px dashed #e1e3ea;">
                            <div class="symbol symbol-45px flex-shrink-0">
                                <span class="symbol-label bg-light-success">
                                    <i class="ki-duotone ki-home-3 fs-2 text-success"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold text-dark fs-7">Residential Units</span>
                                    <span class="badge badge-light-success fs-9 py-1 px-2">Unit Management</span>
                                </div>
                                <div class="text-muted fs-8 mb-3">Add and manage residential units within each complex. Track occupancy status, unit type, floor, and availability. Units are managed from each complex's detail page.</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="#" class="btn btn-sm btn-light-success fw-semibold">Open a Complex</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Commercial Units --}}
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-4 p-4 rounded h-100" style="border:1px dashed #e1e3ea;">
                            <div class="symbol symbol-45px flex-shrink-0">
                                <span class="symbol-label bg-light-info">
                                    <i class="ki-duotone ki-briefcase fs-2 text-info"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold text-dark fs-7">Commercial Units</span>
                                    <span class="badge badge-light-info fs-9 py-1 px-2">Space Management</span>
                                </div>
                                <div class="text-muted fs-8 mb-3">Define and manage commercial spaces within your complexes. Each unit can be subdivided across multiple tenancies based on actual area occupied. Occupancy is tracked by effective square footage.</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="/commercial-units" class="btn btn-sm btn-light-info fw-semibold">Commercial Units</a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="separator separator-dashed my-6"></div>

                {{-- Operations --}}
                <div class="fw-bold text-uppercase text-muted fs-9 mb-4" style="letter-spacing:.06em;">Operations</div>
                <div class="row g-4">

                    {{-- Amenities --}}
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-4 p-4 rounded h-100" style="border:1px dashed #e1e3ea;">
                            <div class="symbol symbol-45px flex-shrink-0">
                                <span class="symbol-label bg-light-warning">
                                    <i class="ki-duotone ki-star fs-2 text-warning"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold text-dark fs-7">Amenities</span>
                                    <span class="badge badge-light-warning fs-9 py-1 px-2">Shared Facilities</span>
                                </div>
                                <div class="text-muted fs-8 mb-3">Add shared facilities to each complex such as parking, gyms, pools, or generators. Service charges can be linked to amenities and billed directly to tenants through the billing system.</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="#" class="btn btn-sm btn-light-warning fw-semibold">Open a Complex</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Manage Expenses --}}
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-4 p-4 rounded h-100" style="border:1px dashed #e1e3ea;">
                            <div class="symbol symbol-45px flex-shrink-0">
                                <span class="symbol-label bg-light-danger">
                                    <i class="ki-duotone ki-wallet fs-2 text-danger"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold text-dark fs-7">Manage Expenses</span>
                                    <span class="badge badge-light-danger fs-9 py-1 px-2">Cost Tracking</span>
                                </div>
                                <div class="text-muted fs-8 mb-3">Track complex-level expenses such as maintenance, repairs, and running costs. Expenses are tied to each complex and can be filtered, categorised, and exported for reporting.</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    @businessCan('expense_management', 'view')<a href="{{ route('business.expenses.index') }}" class="btn btn-sm btn-light-danger fw-semibold">Manage Expenses</a>@endbusinessCan
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="separator separator-dashed my-6"></div>

                {{-- Advanced --}}
                <div class="fw-bold text-uppercase text-muted fs-9 mb-4" style="letter-spacing:.06em;">Advanced</div>
                <div class="row g-4">

                    {{-- Bulk Upload --}}
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-4 p-4 rounded h-100" style="border:1px dashed #e1e3ea;">
                            <div class="symbol symbol-45px flex-shrink-0">
                                <span class="symbol-label bg-light-dark">
                                    <i class="ki-duotone ki-cloud-upload fs-2 text-dark"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold text-dark fs-7">Bulk Upload</span>
                                    <span class="badge badge-light-dark fs-9 py-1 px-2">Import Data</span>
                                </div>
                                <div class="text-muted fs-8 mb-3">Import complexes and units in bulk using Excel templates. Select the upload type, download the sample template, fill in your data, and upload. The fastest way to import existing data at scale.</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    @businessCan('complex_management', 'import')<button type="button" class="btn btn-sm btn-light-dark fw-semibold" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#kt_modal_upload_complexes">Open Upload</button>@endbusinessCan
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Export & Templates --}}
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-4 p-4 rounded h-100" style="border:1px dashed #e1e3ea;">
                            <div class="symbol symbol-45px flex-shrink-0">
                                <span class="symbol-label bg-light-secondary">
                                    <i class="ki-duotone ki-cloud-download fs-2 text-gray-600"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold text-dark fs-7">Export &amp; Templates</span>
                                    <span class="badge badge-light-secondary fs-9 py-1 px-2">Data Export</span>
                                </div>
                                <div class="text-muted fs-8 mb-3">Export all your complex data to Excel for reporting or backup. Download sample templates to use as a reference when preparing bulk import files for complexes or units.</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    @businessCan('complex_management', 'export')<a href="/data-complexes/download" id="download-complexes-guide" class="btn btn-sm btn-light-primary fw-semibold">
                                        <i class="ki-duotone ki-cloud-download fs-7 me-1"><span class="path1"></span><span class="path2"></span></i>Export Complexes
                                    </a>@endbusinessCan
                                    @businessCan('complex_management', 'import')
                                    <a href="/download/complex-sample" class="btn btn-sm btn-light-secondary fw-semibold">Complex Template</a>
                                    <a href="/download/unit-sample" class="btn btn-sm btn-light-secondary fw-semibold">Units Template</a>
                                    @endbusinessCan
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            {{-- Footer --}}
            <div class="modal-footer px-7 py-5 d-flex justify-content-between align-items-center" style="background:#f9fafb;border-top:1px solid #eef0f8;">
                <div class="form-check form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" id="dontShowComplexCheck">
                    <label class="form-check-label text-gray-500 fs-7 fw-semibold" for="dontShowComplexCheck">
                        Don't show this again
                    </label>
                </div>
                <button type="button" class="btn btn-primary fw-bold px-7" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-arrow-right fs-5 me-2"><span class="path1"></span><span class="path2"></span></i>Got it, let's go
                </button>
            </div>

        </div>
    </div>
</div>
{{-- ===== End Overview Modal ===== --}}

@endsection

@section('help_panel_extras')

	<button onclick="openComplexFeatureGuide()" class="btn btn-light d-flex align-items-center gap-3 p-4 rounded mb-3 w-100 text-start">
        <div class="symbol symbol-40px flex-shrink-0">
            <div class="symbol-label bg-light-primary">
                <i class="ki-duotone ki-question-2 fs-3 text-primary"><span class="path1"></span><span class="path2"></span></i>
            </div>
        </div>
        <div class="flex-grow-1 text-start">
            <div class="fw-bold text-dark fs-7 lh-1 mb-1">Open Complex Feature Guide</div>
            <div class="text-muted fs-9">View all features available in Complex Management</div>
        </div>
        <i class="ki-duotone ki-arrow-right fs-4 text-muted flex-shrink-0"><span class="path1"></span><span class="path2"></span></i>
    </button>

@endsection
