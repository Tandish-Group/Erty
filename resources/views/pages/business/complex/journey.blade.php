@extends('pages.business.layouts.app')
@section('content')
<div class="content"><div class="container-xxl py-7 property-flow" id="kt_content_container" data-property-flow>
    <a href="{{ route('complex.index') }}">← Property portfolio</a>
    <div class="d-flex flex-wrap justify-content-between gap-4 mt-4 mb-6"><div><h1>Set up {{ $complex->title }}</h1><p class="text-muted mb-0">{{ collect($steps)->where('confirmed', true)->count() }} of {{ count($steps) }} sections reviewed. Progress is saved after each review.</p></div><a class="btn btn-light align-self-start" href="{{ route('complex.show', $complex->slug) }}">Open property workspace</a></div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
    <div class="row g-6">
        <nav class="col-lg-3 property-step-nav" aria-label="Property setup steps"><div class="list-group">
            @foreach($steps as $key => $step)
                <a data-flow-link href="{{ route('complex.setup', [$complex->slug, 'step' => $key]) }}" class="list-group-item list-group-item-action py-4 {{ $activeStep === $key ? 'active' : '' }}" @if($activeStep === $key) aria-current="step" @endif>{{ $loop->iteration }}. {{ $step['title'] }}<small class="d-block mt-1">{{ $step['state'] }}</small></a>
            @endforeach
            <a data-flow-link class="list-group-item list-group-item-action py-4 {{ $activeStep === 'finish' ? 'active' : '' }}" href="{{ route('complex.setup', [$complex->slug, 'step' => 'finish']) }}">Finish & activate setup</a>
        </div><div class="property-progress" aria-hidden="true"><span style="width: {{ round(collect($steps)->where('confirmed', true)->count() / count($steps) * 100) }}%"></span></div></nav>
        <div class="col-lg-9"><div class="card"><div class="card-body">
        @if($activeStep === 'finish')
            <h2>Final property review</h2><p class="text-muted">Every applicable section needs a current review. Optional sections need a review or a recorded deferral.</p>
            @foreach($steps as $step)<div class="d-flex justify-content-between border-bottom py-4"><span>{{ $step['title'] }}</span><span>{{ $step['state'] }}</span></div>@endforeach
            @if($complex->setup_status === 'ready' && collect($steps)->every(fn ($s) => $s['confirmed']))
                <div class="alert alert-success mt-5">Setup confirmed {{ $complex->setup_confirmed_at?->format('d M Y H:i') }}.</div>
            @else
                <form data-live-form class="mt-6" method="POST" action="{{ route('complex.setup.confirm', $complex->slug) }}" id="finish-setup">@csrf
                    <label class="d-block mb-5"><input required type="checkbox" name="review_acknowledged" value="1"> Confirm these reviewed records are ready for property operations.</label>
                    <div id="confirmation-error" role="alert" class="text-danger mb-3"></div>
                    <button class="btn btn-primary" @disabled(!$canEdit || !collect($steps)->every(fn ($s) => $s['confirmed']))>Confirm property setup</button>
                </form>
            @endif
        @else
            @php($step = $steps[$activeStep])
            <h2>{{ $step['title'] }}</h2><p class="text-muted">{{ $step['help'] }}</p>
            @if($activeStep === 'identity' && $canEdit)
                @include('pages.business.complex.setup-details')
            @elseif($activeStep === 'structure' && $canEdit)
                <form data-live-form method="POST" action="{{ route('complex.workflow.composition', $complex->slug) }}" class="border rounded p-5 mb-5">
                    @csrf<input type="hidden" name="fingerprint" value="{{ $step['fingerprint'] }}">
                    @php($currentKinds = \App\Services\Properties\ComplexInventoryService::kindsOf($complex))
                    <fieldset class="mb-4"><legend class="form-label fs-6">This property holds</legend>
                        <div class="d-flex flex-wrap gap-5">
                        @foreach(\App\Services\Properties\ComplexInventoryService::KIND_LABELS as $value => $label)
                            <label class="form-check"><input class="form-check-input" type="checkbox" name="kinds[]" value="{{ $value }}" @checked(in_array($value, $currentKinds, true))> <span class="form-check-label">{{ $label }}</span></label>
                        @endforeach
                        </div>
                    </fieldset>
                    <p class="text-muted">You can add an inventory type. Types with existing units must be retained.</p>
                    <button class="btn btn-primary">Save composition</button>
                </form>
            @elseif(!in_array($activeStep, ['residential', 'houses'], true) && $canEdit)
            <div class="d-flex flex-wrap gap-3 mb-5"><a href="{{ $step['url'] }}" class="btn btn-light-primary">{{ $activeStep === 'commercial' ? 'Add commercial spaces' : 'Edit this section' }}</a>
                @if($activeStep === 'commercial')<a href="{{ route('complex.show', $complex->slug) }}#commercial_units" class="btn btn-light">View existing spaces</a>@endif
            </div>
            @endif
            @unless($step['valid'])<div class="alert alert-warning">This section needs information. {{ $step['help'] }}</div>@endunless
            @if($step['review'])<p class="text-muted">Last review: {{ $step['review']->user?->first_name }} · {{ $step['review']->created_at->format('d M Y H:i') }} · {{ $step['state'] }} @if($step['note'])<br>Decision note: {{ $step['note'] }}@endif</p>@endif
            @if($activeStep === 'residential' && $canEdit)
                <details class="border rounded p-5 mb-5" @if(empty($step['data'])) open @endif><summary class="fw-bold">Generate an apartment group</summary>
                    <p class="text-muted mt-3">Add one specification per unit layout. This adds new units; it never replaces your existing inventory.</p>
                    <form data-live-form method="POST" action="{{ route('complex.workflow.generate', $complex->slug) }}">@csrf<input type="hidden" name="fingerprint" value="{{ $step['fingerprint'] }}">
                        <div class="row g-3">@foreach(['bedrooms' => 'Bedrooms (0 for studio)', 'bathrooms' => 'Bathrooms', 'sq_ft' => 'Area per unit (sq ft)', 'unit_count' => 'Number of units'] as $key => $label)<div class="col-md-6"><label class="form-label" for="generate-{{ $key }}">{{ $label }}</label><input required type="number" min="{{ $key === 'bedrooms' ? 0 : 1 }}" class="form-control" name="{{ $key }}" id="generate-{{ $key }}" value="{{ old($key) }}"></div>@endforeach</div>
                        <button class="btn btn-primary mt-4">Generate units & continue refinement</button>
                    </form>
                </details>
            @endif
            @if($activeStep === 'houses' && $canEdit)
                <details class="border rounded p-5 mb-5" @if(empty($step['data'])) open @endif><summary class="fw-bold">Add houses of one type</summary>
                    <p class="text-muted mt-3">One entry per house design: a single villa is one house; an estate of identical townhouses is one entry with the number of houses. This adds houses; it never replaces existing ones.</p>
                    <form data-live-form method="POST" action="{{ route('complex.workflow.generate', $complex->slug) }}" data-house-generate>@csrf<input type="hidden" name="fingerprint" value="{{ $step['fingerprint'] }}"><input type="hidden" name="category" value="house">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label" for="house-type">House type</label><select required class="form-select" name="house_type" id="house-type"><option value="">Choose a type</option>@foreach(config('property.house_types') as $type)<option @selected(old('house_type') === $type)>{{ $type }}</option>@endforeach</select></div>
                            <div class="col-md-2"><label class="form-label" for="house-bedrooms">Bedrooms</label><input required type="number" min="0" max="30" class="form-control" name="bedrooms" id="house-bedrooms" value="{{ old('bedrooms') }}"></div>
                            <div class="col-md-2"><label class="form-label" for="house-bathrooms">Bathrooms</label><input required type="number" min="1" max="30" class="form-control" name="bathrooms" id="house-bathrooms" value="{{ old('bathrooms') }}"></div>
                            <div class="col-md-2"><label class="form-label" for="house-storeys">Storeys</label><input type="number" min="1" max="10" class="form-control" name="storeys" id="house-storeys" value="{{ old('storeys') }}" placeholder="Optional"></div>
                            <div class="col-md-2"><label class="form-label" for="house-count">Number of houses</label><input required type="number" min="1" max="500" class="form-control" name="unit_count" id="house-count" value="{{ old('unit_count', 1) }}"></div>
                            <div class="col-md-4"><label class="form-label" for="house-sqft">Built-up area per house (sq ft)</label><input required type="number" min="1" step="any" class="form-control" name="sq_ft" id="house-sqft" value="{{ old('sq_ft') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="house-plot">Plot size <span class="text-muted fw-normal">(optional)</span></label><div class="input-group"><input type="number" min="0.001" step="any" class="form-control" name="plot_size" id="house-plot" value="{{ old('plot_size') }}"><select class="form-select" name="plot_unit" aria-label="Plot size unit" style="max-width:8rem">@foreach(config('property.plot_units') as $value => $label)<option value="{{ $value }}" @selected(old('plot_unit', 'acres') === $value)>{{ $label }}</option>@endforeach</select></div></div>
                            <div class="col-md-4"><label class="form-label" for="house-offer">Offered for</label><select required class="form-select" name="offer" id="house-offer" data-house-offer>@foreach(config('property.offers') as $value => $label)<option value="{{ $value }}" @selected(old('offer', 'rent') === $value)>{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-4" data-sale-price-field @if(!in_array(old('offer', 'rent'), ['sale', 'both'], true)) hidden @endif><label class="form-label" for="house-price">Asking price per house ({{ default_currency() }})</label><input type="number" min="1" step="any" class="form-control" name="sale_price" id="house-price" value="{{ old('sale_price') }}"><div class="form-text">Rent is set on the listing.</div></div>
                            <div class="col-12"><span class="form-label d-block">Features <span class="text-muted fw-normal">(optional)</span></span><div class="d-flex flex-wrap gap-4">@foreach(config('property.house_features') as $value => $label)<label class="form-check"><input class="form-check-input" type="checkbox" name="features[]" value="{{ $value }}" @checked(in_array($value, (array) old('features', []), true))> <span class="form-check-label">{{ $label }}</span></label>@endforeach</div></div>
                        </div>
                        <button class="btn btn-primary mt-4">Add houses & continue</button>
                    </form>
                </details>
                <script>
                    document.addEventListener('change', function (e) {
                        if (!e.target.matches('[data-house-offer]')) return;
                        const field = e.target.closest('form').querySelector('[data-sale-price-field]');
                        const sale = ['sale', 'both'].includes(e.target.value);
                        field.hidden = !sale; field.querySelector('input').required = sale;
                    });
                </script>
            @endif
            @if($activeStep === 'houses')
                <p class="text-muted">{{ count($step['data']) }} {{ \Illuminate\Support\Str::plural('house', count($step['data'])) }} · 25 per page. Review covers every house, including other pages.</p>
                <div class="table-responsive"><table class="table table-row-bordered align-middle"><thead><tr><th>House</th><th>Type & layout</th><th>Plot</th><th>Status</th><th>Details</th></tr></thead><tbody>
                    @forelse($inventory as $unit)
                        <tr><td>{{ $unit['unit_number'] ?: 'Number required' }}@if($unit['plot_number'])<div class="text-muted fs-8">Plot {{ $unit['plot_number'] }}</div>@endif</td>
                            <td>{{ $unit['house_type'] ?: 'House' }}<div class="text-muted fs-8">{{ $unit['bedrooms'] }} bed · {{ $unit['bathrooms'] }} bath · {{ number_format((float) $unit['sq_ft']) }} sq ft</div></td>
                            <td>{{ $unit['plot_size'] ? rtrim(rtrim(number_format((float) $unit['plot_size'], 3), '0'), '.') . ' ' . (config('property.plot_units')[$unit['plot_unit']] ?? '') : '—' }}</td>
                            <td>{{ \App\Services\Properties\ComplexInventoryService::RESIDENTIAL_STATUS_LABELS[$unit['status']] ?? $unit['status'] }}</td>
                            <td><a class="btn btn-sm btn-light-primary" href="{{ route('complex.setup', [$complex->slug, 'step' => 'houses', 'unit' => $unit['id']]) }}">Open house details</a></td></tr>
                    @empty<tr><td colspan="5">No houses recorded yet.</td></tr>@endforelse
                </tbody></table></div>
                {{ $inventory->links('pagination::bootstrap-5') }}
            @elseif(in_array($activeStep, ['residential', 'commercial']))
                <p class="text-muted">{{ count($step['data']) }} units · 25 per page. Review covers the entire inventory, including other pages.</p>
                <div class="table-responsive"><table class="table table-row-bordered align-middle"><thead><tr><th>Unit</th><th>Floor</th><th>Area (sq ft)</th><th>Status</th>@if($activeStep === 'residential')<th>Details</th>@endif</tr></thead><tbody>
                    @forelse($inventory as $unit)
                        <tr><td>{{ $unit['unit_number'] ?: 'Number required' }}</td><td>{{ $unit['floor'] ?? $unit['custom_floors'] ?? $unit['floors'] ?? 'Floor required' }}</td><td>{{ $unit['sq_ft'] ?? $unit['unit_sq_ft'] ?? 'Not recorded' }}</td><td>{{ $unit['status'] }}</td>
                        @if($activeStep === 'residential')
                            <td><a class="btn btn-sm btn-light-primary" href="{{ route('complex.setup', [$complex->slug, 'step' => 'residential', 'unit' => $unit['id']]) }}">Open unit details</a></td>
                        @endif
                        </tr>
                    @empty<tr><td colspan="{{ $activeStep === 'residential' ? 5 : 4 }}">No units recorded yet.</td></tr>@endforelse
                </tbody></table></div>
                {{ $inventory->links('pagination::bootstrap-5') }}
            @else
                @include('pages.business.complex.review-records', ['records' => $step['data']])
                @if($activeStep === 'identity')
                    {{-- Responsibility (shown for the review; not part of the reviewed fingerprint). --}}
                    <dl class="property-records mb-4" data-setup-responsibility>
                        <dt>Responsible agent</dt><dd>{{ $complex->assignedAgent?->full_name ?? 'No agent assigned' }}</dd>
                        <dt>Managed by</dt><dd>{{ $complex->managers->map(fn ($m) => trim($m->first_name.' '.$m->last_name))->implode(', ') ?: 'No team members assigned' }}</dd>
                    </dl>
                @endif
            @endif
            @if($canEdit)<form data-live-form class="border-top pt-5 mt-5" method="POST" action="{{ route('complex.workflow.review', [$complex->slug, $activeStep]) }}">@csrf
                <input type="hidden" name="fingerprint" value="{{ $step['fingerprint'] }}">
                @if($step['optional'] ?? false)<label for="decision" class="form-label">Decision</label><select name="decision" id="decision" class="form-select mb-4"><option value="confirmed">Reviewed — information is correct</option><option value="deferred">Not applicable / deferred — explain below</option></select><label for="note" class="form-label">Reason or review note</label><textarea name="note" id="note" maxlength="1000" class="form-control mb-4">{{ old('note') }}</textarea>@else<input type="hidden" name="decision" value="confirmed">@endif
                <label class="d-block mb-4"><input type="checkbox" name="acknowledged" value="1" required> I reviewed the records shown for this section.</label>
                <button class="btn btn-primary" @disabled(!$step['valid'])>Save review & continue</button>
            </form>@else<p class="text-muted mt-5">An employee with property editing access must complete this review.</p>@endif
        @endif
        </div></div></div>
    </div>
</div></div>
@endsection
