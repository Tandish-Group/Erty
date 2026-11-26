<div class="modal fade" id="space-details-{{ $unit->id }}" tabindex="-1" aria-labelledby="space-title-{{ $unit->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header align-items-start px-7 py-6">
                <div>
                    <div class="text-uppercase text-muted fw-semibold fs-8 mb-2">Commercial space</div>
                    <h2 class="fs-2 mb-2" id="space-title-{{ $unit->id }}">{{ $unit->unit_number ?: 'Unnumbered space' }}</h2>
                    <a href="{{ route('complex.show', $unit->complex->slug) }}#commercial_units">{{ $unit->complex->title }}</a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close space details"></button>
            </div>
            <div class="modal-body p-7">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-6">
                    <span class="text-muted">Floor <strong class="text-dark ms-2">{{ $unit->effective_floor ?: 'Not recorded' }}</strong></span>
                    <span class="badge badge-light-primary">{{ $unit->status }} · recorded status</span>
                </div>
                <div class="row g-4 mb-7">
                    <div class="col-sm-6"><div class="bg-light rounded p-5 h-100"><div class="text-muted mb-2">Total floor area</div><div class="fs-2 fw-bold">{{ number_format($unit->unit_sq_ft, 2) }} <small class="fs-6 fw-normal">sq ft</small></div></div></div>
                    <div class="col-sm-6"><div class="bg-light rounded p-5 h-100"><div class="text-muted mb-2">Monthly asking rent</div><div class="fs-2 fw-bold">{{ $unit->formatted_monthly_rent }}</div></div></div>
                </div>
                <h3 class="fs-5 mb-4">Space & leasing limits</h3>
                <dl class="row mb-6">
                    <dt class="col-7 fw-normal text-muted py-2">Minimum leasable area</dt><dd class="col-5 text-end py-2 mb-0">{{ number_format($unit->min_sq_ft, 2) }} sq ft</dd>
                    <dt class="col-7 fw-normal text-muted py-2">Maximum subdivisions</dt><dd class="col-5 text-end py-2 mb-0">{{ $unit->max_subdivisions }}</dd>
                    <dt class="col-7 fw-normal text-muted py-2">Area not allocated to active tenancies</dt><dd class="col-5 text-end py-2 mb-0">{{ number_format(max(0, $unit->unit_sq_ft - $unit->activeTenants->sum(fn ($tenant) => $tenant->occupied_sq_area ?? $tenant->sq_area ?? 0)), 2) }} sq ft</dd>
                </dl>
                <div class="border-top pt-5 mb-6"><h3 class="fs-5 mb-3">Description</h3><p class="text-muted mb-0" style="white-space: pre-line; overflow-wrap: anywhere">{{ $unit->description ?: 'No description has been added for this space.' }}</p></div>
                <div class="border-top pt-5 mb-6"><h3 class="fs-5 mb-4">Active tenancies <span class="badge badge-light ms-2">{{ $unit->activeTenants->count() }}</span></h3>
                    @forelse($unit->activeTenants as $tenancy)
                        <div class="border rounded p-4 mb-3">@include('pages.business.complex.partials.inventory-tenant', ['tenancy' => $tenancy])</div>
                    @empty<p class="text-muted mb-0">No active tenancy is linked to this space.</p>@endforelse
                </div>
                <p class="bg-light-warning rounded p-4 mb-0 fs-7">Recorded availability and unallocated area do not establish leasing eligibility. Check property setup and reservations before onboarding a tenant.</p>
            </div>
            <div class="modal-footer justify-content-between gap-3 px-7">
                <a href="{{ route('complex.setup', [$unit->complex->slug, 'step' => 'commercial']) }}" class="btn btn-light">Review property setup</a>
                <div class="d-flex gap-3"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>@if($canEdit)<a class="btn btn-primary" href="{{ route('commercial-units.edit', encrypt($unit->id)) }}">Edit space</a>@endif</div>
            </div>
        </div>
    </div>
</div>
