@extends('pages.business.layouts.app')
@section('content')
@php
    use App\Support\PropertyHistory as PH;
    $defs = collect(PH::categories())->filter(fn ($d, $tag) => isset($categories[$tag]));
    $chosen = $preset['category'] && isset($defs[$preset['category']]) ? $preset['category'] : null;
    $scope = in_array($preset['scope'], ['property', 'residential', 'commercial'], true) ? $preset['scope'] : 'property';
@endphp
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center" id="kt_header_container">
        <div class="page-title d-flex flex-column justify-content-center">
            <h1 class="text-dark fw-bolder my-0 fs-3">Add a history record</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-7 my-1">
                <li class="breadcrumb-item text-muted"><a href="{{ route('history.index') }}" class="text-muted text-hover-primary">Property history</a></li>
                <li class="breadcrumb-item text-dark">New record</li>
            </ul>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl inv-page sale-page" id="kt_content_container">
    <header class="inv-head">
        <div>
            <span class="inv-eyebrow">New record</span>
            <h2>Record something that happened{{ $selected ? ' at ' . $selected->title : '' }}</h2>
            <p>Choose what kind of event it was, where it happened, and fill in what you know. Records can't be edited once saved, so check the details before you save.</p>
        </div>
    </header>

    @if($errors->any())<div class="alert alert-danger" role="alert" data-history-errors><strong>Please check these details</strong>@foreach($errors->all() as $error)<p class="mb-0 mt-1">{{ $error }}</p>@endforeach</div>@endif

    <form method="POST" action="{{ route('history.store') }}" enctype="multipart/form-data" class="row g-6" data-history-form data-units-url="{{ url('/complex/__slug__/units') }}">
        @csrf
        <div class="col-xl-8">
            <section class="inv-panel sale-section">
                <h3><span class="hist-step">1</span>What are you recording?</h3>
                <div class="hist-choices" role="radiogroup" aria-label="Record type">
                    @foreach($defs as $tag => $def)
                        <label class="hist-choice hist-tone-{{ $def['tone'] }}">
                            <input type="radio" name="history_category" value="{{ $tag }}" @checked($chosen === $tag) required>
                            <span class="hist-choice-icon" aria-hidden="true"><i class="bi {{ $def['icon'] }}"></i></span>
                            <span class="hist-choice-text"><strong>{{ PH::label($tag) }}</strong><small>{{ $def['short'] }}</small></span>
                        </label>
                    @endforeach
                </div>
                @error('history_category')<div class="text-danger fs-7 mt-2">{{ $message }}</div>@enderror
            </section>

            <section class="inv-panel sale-section">
                <h3><span class="hist-step">2</span>Where did it happen?</h3>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label required" for="history-complex">Property</label>
                        <select class="form-select @error('complex') is-invalid @enderror" id="history-complex" name="complex" required data-history-complex>
                            <option value="">Choose a property</option>
                            @foreach($complexes as $property)<option value="{{ $property->slug }}" @selected($selected?->id === $property->id)>{{ $property->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <span class="form-label d-block">Applies to</span>
                        <div class="hist-scope" role="radiogroup" aria-label="Applies to">
                            @foreach(['property' => 'Whole property', 'residential' => 'Apartment or house', 'commercial' => 'Commercial space'] as $value => $label)
                                <label><input type="radio" name="scope" value="{{ $value }}" @checked($scope === $value) data-history-scope> <span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-6" data-unit-field="residential" @if($scope !== 'residential') hidden @endif>
                        <label class="form-label required" for="history-residential">Apartment or house</label>
                        <select class="form-select @error('residential_unit_id') is-invalid @enderror" id="history-residential" name="residential_unit_id" data-selected="{{ old('residential_unit_id', $scope === 'residential' ? $preset['unit'] : '') }}"><option value="">Choose the property first</option></select>
                    </div>
                    <div class="col-md-6" data-unit-field="commercial" @if($scope !== 'commercial') hidden @endif>
                        <label class="form-label required" for="history-commercial">Commercial space</label>
                        <select class="form-select @error('commercial_unit_id') is-invalid @enderror" id="history-commercial" name="commercial_unit_id" data-selected="{{ old('commercial_unit_id', $scope === 'commercial' ? $preset['unit'] : '') }}"><option value="">Choose the property first</option></select>
                    </div>
                    <div class="col-12"><p class="text-muted fs-8 mb-0" data-unit-status role="status"></p></div>
                </div>
            </section>

            <section class="inv-panel sale-section" data-details>
                <h3><span class="hist-step">3</span>Details</h3>
                <p class="text-muted fs-7 mb-0" data-details-empty @if($chosen) hidden @endif>Choose what you are recording above to see its fields.</p>
                @foreach($defs as $tag => $def)
                    <fieldset class="hist-fields" data-fields="{{ $tag }}" @if($chosen !== $tag) hidden disabled @endif>
                        <legend class="visually-hidden">{{ PH::label($tag) }} details</legend>
                        @if($categories[$tag]->description)<p class="hist-intro"><i class="bi bi-info-circle" aria-hidden="true"></i>{{ $categories[$tag]->description }}</p>@endif
                        <div class="row g-4">
                            @foreach($def['fields'] as [$input, $key, $label, $type, $options, $required])
                                <div class="{{ $type === 'textarea' ? 'col-12' : 'col-md-6' }}">
                                    <label class="form-label {{ $required ? 'required' : '' }}" for="f-{{ $input }}">{{ $label }}</label>
                                    @if($type === 'select')
                                        <select class="form-select @error($input) is-invalid @enderror" id="f-{{ $input }}" name="{{ $input }}" @required($required) @if($def['date'] === $key) data-event-date @endif>
                                            <option value="">{{ $required ? 'Choose' : 'Not recorded' }}</option>
                                            @foreach($options as $v => $l)<option value="{{ $v }}" @selected(old($input) === $v)>{{ $l }}</option>@endforeach
                                        </select>
                                    @elseif($type === 'textarea')
                                        <textarea class="form-control @error($input) is-invalid @enderror" id="f-{{ $input }}" name="{{ $input }}" rows="3" maxlength="2000" @required($required)>{{ old($input) }}</textarea>
                                    @elseif($type === 'money')
                                        <div class="input-group"><span class="input-group-text">{{ default_currency() }}</span><input class="form-control @error($input) is-invalid @enderror" id="f-{{ $input }}" name="{{ $input }}" type="number" min="0" step="any" value="{{ old($input) }}" @required($required)></div>
                                    @else
                                        <input class="form-control @error($input) is-invalid @enderror" id="f-{{ $input }}" name="{{ $input }}" type="{{ $type === 'date' ? 'date' : 'text' }}" @if($type !== 'date') maxlength="255" @else max="{{ now()->addYears(5)->toDateString() }}" @endif value="{{ old($input) }}" @required($required) @if($def['date'] === $key) data-event-date @endif>
                                    @endif
                                    @error($input)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                            <div class="col-12">
                                <label class="form-label" for="f-{{ $def['files'] }}">Files <span class="text-muted fw-normal">({{ mb_strtolower($def['files_label']) }})</span></label>
                                <input class="form-control" id="f-{{ $def['files'] }}" type="file" name="{{ $def['files'] }}[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.mp4" data-files>
                                <div class="form-text">Up to 10 files, 10 MB each: PDF, images, Word, Excel or MP4. Files are stored with shareable links, so leave out confidential documents.</div>
                            </div>
                        </div>
                    </fieldset>
                @endforeach
                <div class="mt-4">
                    <label class="form-label" for="history-notes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea class="form-control" id="history-notes" name="notes" rows="3" maxlength="10000" placeholder="Anything else worth knowing: who told you, where the originals are kept…">{{ old('notes') }}</textarea>
                </div>
            </section>
        </div>

        <aside class="col-xl-4">
            <section class="inv-panel sale-section sale-sticky" aria-live="polite">
                <h3>Your record</h3>
                <dl class="sale-facts mb-4">
                    <div><dt>Type</dt><dd data-sum="type">Not chosen</dd></div>
                    <div><dt>Property</dt><dd data-sum="property">Not chosen</dd></div>
                    <div><dt>Applies to</dt><dd data-sum="scope">Whole property</dd></div>
                    <div><dt>Happened</dt><dd data-sum="date">—</dd></div>
                    <div><dt>Files</dt><dd data-sum="files">None</dd></div>
                </dl>
                <label class="form-check mb-4"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required @checked(old('confirmed'))> <span class="form-check-label fs-7">I've checked these details. I understand a saved record can't be edited, only followed up by a new one.</span></label>
                @error('confirmed')<div class="text-danger fs-7 mb-3">{{ $message }}</div>@enderror
                <button class="btn btn-primary w-100" data-history-save>Save record</button>
                <a class="btn btn-light w-100 mt-2" href="{{ route('history.index', $selected ? ['complex_filter' => $selected->slug] : []) }}">Cancel</a>
            </section>
        </aside>
    </form>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-history-form]');
    const $ = s => form.querySelector(s), $$ = s => [...form.querySelectorAll(s)];
    const complex = $('[data-history-complex]'), status = $('[data-unit-status]');
    const unitSelects = { residential: $('#history-residential'), commercial: $('#history-commercial') };
    const labels = @json($defs->map(fn ($d, $tag) => PH::label($tag)));
    const scopeLabels = { property: 'Whole property', residential: 'Apartment or house', commercial: 'Commercial space' };
    let units = { residential: [], commercial: [] };

    const category = () => $('[name="history_category"]:checked')?.value;
    const scope = () => $('[name="scope"]:checked')?.value || 'property';

    function showCategory() {
        const tag = category();
        $$('[data-fields]').forEach(f => { const on = f.dataset.fields === tag; f.hidden = !on; f.disabled = !on; });
        $('[data-details-empty]').hidden = !!tag;
        summary();
    }
    function showScope() {
        const s = scope();
        Object.entries(unitSelects).forEach(([kind, select]) => {
            const box = form.querySelector('[data-unit-field="' + kind + '"]');
            box.hidden = s !== kind; select.disabled = s !== kind; select.required = s === kind;
        });
        summary();
    }
    function fill(kind) {
        const select = unitSelects[kind], keep = select.value || select.dataset.selected;
        select.innerHTML = '<option value="">' + (units[kind].length ? 'Choose' : 'None at this property') + '</option>' +
            units[kind].map(u => '<option value="' + u.id + '">' + String(u.label).replace(/[<>&"]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c])) + '</option>').join('');
        if (keep) select.value = keep;
        select.dataset.selected = '';
    }
    async function loadUnits() {
        units = { residential: [], commercial: [] };
        if (!complex.value) { fill('residential'); fill('commercial'); summary(); return; }
        status.textContent = 'Loading units…';
        try {
            const r = await fetch(form.dataset.unitsUrl.replace('__slug__', encodeURIComponent(complex.value)), { headers: { Accept: 'application/json' } });
            const d = await r.json();
            units = { residential: d.residential_units || [], commercial: d.commercial_units || [] };
            status.textContent = units.residential.length + ' apartments/houses and ' + units.commercial.length + ' commercial spaces at this property.';
        } catch (e) { status.textContent = 'Units could not be loaded. Choose "Whole property" or try again.'; }
        fill('residential'); fill('commercial');
        $$('[data-history-scope]').forEach(r => { if (r.value !== 'property') r.disabled = !units[r.value].length; });
        if ($('[name="scope"]:checked')?.disabled) { $('[name="scope"][value="property"]').checked = true; showScope(); }
        summary();
    }
    function summary() {
        const set = (k, v) => form.querySelector('[data-sum="' + k + '"]').textContent = v;
        const tag = category();
        set('type', tag ? labels[tag] : 'Not chosen');
        set('property', complex.value ? complex.selectedOptions[0].textContent : 'Not chosen');
        const s = scope(), unit = unitSelects[s]?.selectedOptions[0];
        set('scope', s === 'property' ? scopeLabels.property : (unit && unit.value ? unit.textContent : scopeLabels[s] + ' (choose one)'));
        const date = tag ? form.querySelector('[data-fields="' + tag + '"] [data-event-date]') : null;
        set('date', date && date.value ? new Date(date.value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
        const files = tag ? form.querySelector('[data-fields="' + tag + '"] [data-files]')?.files.length : 0;
        set('files', files ? files + (files === 1 ? ' file' : ' files') : 'None');
    }
    form.addEventListener('change', e => {
        if (e.target.name === 'history_category') showCategory();
        else if (e.target.name === 'scope') showScope();
        else if (e.target === complex) loadUnits();
        else summary();
    });
    form.addEventListener('input', summary);
    form.addEventListener('submit', () => { const b = $('[data-history-save]'); b.disabled = true; b.textContent = 'Saving…'; });
    showCategory(); showScope(); loadUnits();
});
</script>
@endsection
