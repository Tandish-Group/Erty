@extends('pages.business.layouts.app')
@section('content')
@php
    use App\Support\PropertyHistory as PH;
    $defs = PH::categories();
    $query = fn (array $change) => array_filter(array_replace(request()->only(['q', 'complex_filter', 'category', 'scope', 'from', 'to']), $change), fn ($v) => $v !== null && $v !== '');
    $months = $records->getCollection()->groupBy(fn ($r) => $r->event_date->format('Y-m'));
@endphp
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center" id="kt_header_container">
        <div class="page-title d-flex flex-column justify-content-center">
            <h1 class="text-dark fw-bolder my-0 fs-3">Property history</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-7 my-1">
                <li class="breadcrumb-item text-muted"><a href="{{ route('complex.index') }}" class="text-muted text-hover-primary">Property portfolio</a></li>
                @if($selectedProperty)<li class="breadcrumb-item text-muted"><a href="{{ route('complex.show', $selectedProperty->slug) }}" class="text-muted text-hover-primary">{{ $selectedProperty->title }}</a></li>@endif
                <li class="breadcrumb-item text-dark">History</li>
            </ul>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl inv-page" id="kt_content_container" data-history-index>

    <header class="inv-head">
        <div>
            <span class="inv-eyebrow">Property history</span>
            <h2>{{ $selectedProperty?->title ?? 'Every property\'s story, in one timeline' }}</h2>
            <p>Ownership changes, renovations, legal matters, utilities, incidents and documents, recorded against a property or one of its units. Saved records are kept as written: to correct one, add a new record.</p>
        </div>
        <div class="inv-actions">
            @if($selectedProperty)<a class="btn btn-light" href="{{ route('complex.show', $selectedProperty->slug) }}"><i class="bi bi-building me-2" aria-hidden="true"></i>Open property</a>@endif
            @if($canCreate)<a class="btn btn-primary" href="{{ route('history.create', $selectedProperty ? ['complex' => $selectedProperty->slug] : []) }}"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add a record</a>@endif
        </div>
    </header>

    <div class="inv-stats">
        <div><span>Records</span><strong>{{ $stats['total'] }}</strong><small>{{ $selectedProperty ? 'for this property' : 'across ' . $stats['properties'] . ' ' . \Illuminate\Support\Str::plural('property', $stats['properties']) }}</small></div>
        <div><span>Latest event</span><strong class="fs-3">{{ $stats['latest'] ? $stats['latest']->format('j M Y') : '—' }}</strong><small>{{ $stats['latest'] ? $stats['latest']->diffForHumans() : 'nothing recorded yet' }}</small></div>
        <div><span>Open legal matters</span><strong class="{{ $stats['open_legal'] ? 'text-danger' : '' }}">{{ $stats['open_legal'] }}</strong><small>active or pending</small></div>
        <div><span>Files kept</span><strong>{{ $stats['files'] }}</strong><small>deeds, photos, certificates and more</small></div>
    </div>

    <nav class="hist-cats" aria-label="Record types">
        <a href="{{ route('history.index', $query(['category' => null, 'page' => null])) }}" class="hist-cat {{ !$filters['category'] ? 'is-active' : '' }}" @if(!$filters['category']) aria-current="true" @endif>All <span>{{ $stats['total'] }}</span></a>
        @foreach($defs as $tag => $def)
            @if(isset($categories[$tag]))
            <a href="{{ route('history.index', $query(['category' => $tag, 'page' => null])) }}" class="hist-cat hist-tone-{{ $def['tone'] }} {{ $filters['category'] === $tag ? 'is-active' : '' }}" @if($filters['category'] === $tag) aria-current="true" @endif><i class="bi {{ $def['icon'] }}" aria-hidden="true"></i>{{ PH::label($tag) }} <span>{{ $counts[$tag] ?? 0 }}</span></a>
            @endif
        @endforeach
    </nav>

    <section class="inv-panel">
        <form method="GET" class="inv-toolbar" role="search" aria-label="Filter property history">
            @if($filters['category'])<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif
            <div class="inv-search"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control form-control-sm" name="q" value="{{ $filters['q'] }}" maxlength="200" placeholder="Search owners, contractors, references…" aria-label="Search history"></div>
            <select class="form-select form-select-sm" name="complex_filter" aria-label="Property">
                <option value="">All properties</option>
                @foreach($complexes as $property)<option value="{{ $property->slug }}" @selected($selectedProperty?->id === $property->id)>{{ $property->title }}</option>@endforeach
            </select>
            <select class="form-select form-select-sm" name="scope" aria-label="Applies to">
                <option value="">Property and units</option>
                <option value="property" @selected($filters['scope'] === 'property')>Whole property only</option>
                <option value="residential" @selected($filters['scope'] === 'residential')>Apartments & houses</option>
                <option value="commercial" @selected($filters['scope'] === 'commercial')>Commercial spaces</option>
            </select>
            <div class="hist-dates"><input type="date" class="form-control form-control-sm" name="from" value="{{ $filters['from'] }}" aria-label="Events from"><span aria-hidden="true">–</span><input type="date" class="form-control form-control-sm" name="to" value="{{ $filters['to'] }}" aria-label="Events to"></div>
            <button class="btn btn-sm btn-primary">Apply</button>
            @if($filtered || $selectedProperty)<a class="btn btn-sm btn-light" href="{{ route('history.index') }}">Reset</a>@endif
            <span class="inv-count">{{ $records->total() }} {{ \Illuminate\Support\Str::plural('record', $records->total()) }}</span>
        </form>

        @if($records->isEmpty())
            <div class="inv-empty">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                <strong>{{ $filtered ? 'No records match' : 'No history recorded yet' }}</strong>
                <span>{{ $filtered ? 'Change the search or filters.' : 'Start with what you know: who owned the property, its title deed, past renovations.' }}</span>
                @if(!$filtered && $canCreate)<a class="btn btn-sm btn-primary mt-4" href="{{ route('history.create', $selectedProperty ? ['complex' => $selectedProperty->slug] : []) }}">Add the first record</a>@endif
            </div>
        @else
            <div class="hist-timeline">
                @foreach($months as $month => $items)
                    <h3 class="hist-month">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</h3>
                    <ol class="hist-list">
                    @foreach($items as $record)
                        @php
                            $def = $defs[$record->category_tag] ?? ['icon' => 'bi-journal', 'tone' => 'slate'];
                            $files = PH::files($record);
                            $summary = PH::summary($record);
                            $where = $record->residentialUnit ? $record->residentialUnit->label() : ($record->commercialUnit ? 'Space ' . $record->commercialUnit->unit_number : null);
                        @endphp
                        <li class="hist-item" data-history-record="{{ $record->id }}">
                            <span class="hist-dot hist-tone-{{ $def['tone'] }}" aria-hidden="true"><i class="bi {{ $def['icon'] }}"></i></span>
                            <a class="hist-card" href="{{ route('history.show', $record->id) }}">
                                <div class="hist-card-head">
                                    <span class="hist-date"><strong>{{ $record->event_date->format('j') }}</strong>{{ $record->event_date->format('M') }}</span>
                                    <div class="hist-card-main">
                                        <span class="hist-kind hist-tone-{{ $def['tone'] }}">{{ PH::label($record->category_tag) }}</span>
                                        <h4>{{ PH::title($record) }}</h4>
                                        <p class="hist-where"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $record->complex?->title }}{{ $where ? ' · ' . $where : ' · whole property' }}</p>
                                    </div>
                                    <i class="bi bi-chevron-right hist-go" aria-hidden="true"></i>
                                </div>
                                @if($summary)<p class="hist-summary">{{ \Illuminate\Support\Str::limit($summary, 220) }}</p>@endif
                                <div class="hist-meta">
                                    @if(count($files))<span><i class="bi bi-paperclip" aria-hidden="true"></i>{{ count($files) }} {{ \Illuminate\Support\Str::plural('file', count($files)) }}</span>@endif
                                    @if($record->notes)<span><i class="bi bi-chat-left-text" aria-hidden="true"></i>Notes</span>@endif
                                    <span>Recorded by {{ $record->creator ? trim($record->creator->first_name . ' ' . $record->creator->last_name) : 'Erty' }} · {{ $record->created_at?->format('j M Y') }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                    </ol>
                @endforeach
            </div>
            @if($records->hasPages())<div class="inv-pages">{{ $records->links('pagination::bootstrap-5') }}</div>@endif
        @endif
    </section>

    <p class="hist-footnote"><i class="bi bi-shield-exclamation" aria-hidden="true"></i>Attachments are stored with shareable links. Don't upload documents that must stay confidential, such as ID copies.</p>
</div>
</div>
@endsection
