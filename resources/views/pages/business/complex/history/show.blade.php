@extends('pages.business.layouts.app')
@section('content')
@php
    use App\Support\PropertyHistory as PH;
    $def = PH::category($record->category_tag) ?? ['icon' => 'bi-journal', 'tone' => 'slate', 'label' => 'Record'];
    $date = PH::eventDate($record);
    $parts = PH::facts($record);
    $files = PH::files($record);
    $unit = $record->residentialUnit ? $record->residentialUnit->label() : ($record->commercialUnit ? 'Space ' . $record->commercialUnit->unit_number : null);
    $scope = $record->complex_residential_unit_id ? 'residential' : ($record->commercial_unit_id ? 'commercial' : 'property');
    $size = fn ($b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
    $fileIcon = fn ($t) => str_contains($t, 'pdf') ? 'bi-file-earmark-pdf' : (str_starts_with($t, 'image') ? 'bi-file-earmark-image' : (str_starts_with($t, 'video') ? 'bi-file-earmark-play' : 'bi-file-earmark'));
    $saleRef = $record->data['sale_reference'] ?? null;
@endphp
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center" id="kt_header_container">
        <div class="page-title d-flex flex-column justify-content-center">
            <h1 class="text-dark fw-bolder my-0 fs-3">History record</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-7 my-1">
                <li class="breadcrumb-item text-muted"><a href="{{ route('history.index') }}" class="text-muted text-hover-primary">Property history</a></li>
                <li class="breadcrumb-item text-muted"><a href="{{ route('history.index', ['complex_filter' => $record->complex->slug]) }}" class="text-muted text-hover-primary">{{ $record->complex->title }}</a></li>
                <li class="breadcrumb-item text-dark">{{ PH::label($record->category_tag) }}</li>
            </ul>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl inv-page" id="kt_content_container" data-history-record="{{ $record->id }}">
    <header class="inv-head">
        <div class="d-flex gap-4 align-items-start">
            <span class="hist-badge hist-tone-{{ $def['tone'] }}" aria-hidden="true"><i class="bi {{ $def['icon'] }}"></i></span>
            <div>
                <span class="inv-eyebrow">{{ PH::label($record->category_tag) }}{{ $date ? ' · ' . $date->format('j M Y') : '' }}</span>
                <h2>{{ PH::title($record) }}</h2>
                <p><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><a class="inv-link" href="{{ route('complex.show', $record->complex->slug) }}">{{ $record->complex->title }}</a>{{ $unit ? ' · ' . $unit : ' · whole property' }}</p>
            </div>
        </div>
        <div class="inv-actions">
            <a class="btn btn-light" href="{{ route('history.index', ['complex_filter' => $record->complex->slug]) }}"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Property timeline</a>
            @if($canCreate)<a class="btn btn-primary" href="{{ route('history.create', array_filter(['complex' => $record->complex->slug, 'category' => $record->category_tag, 'scope' => $scope, 'unit' => $record->complex_residential_unit_id ?: $record->commercial_unit_id])) }}"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add a follow-up record</a>@endif
        </div>
    </header>

    <div class="row g-6">
        <div class="col-xl-8">
            <section class="inv-panel sale-section">
                <h3>What was recorded</h3>
                @if($parts['facts'])
                <dl class="hist-facts">
                    @foreach($parts['facts'] as [$label, $value])
                        <div><dt>{{ $label }}</dt><dd>@if($label === 'Sale' && $saleRef && app(\App\Services\Access\ModuleService::class)->enabled('sales') && auth()->user()->hasBusinessPermission('sales_management', 'view'))<a href="{{ route('sales.show', $saleRef) }}">{{ $value }}</a>@else{{ $value }}@endif</dd></div>
                    @endforeach
                </dl>
                @endif
                @foreach($parts['texts'] as [$label, $value])
                    <div class="hist-text"><h4>{{ $label }}</h4><p>{!! nl2br(e($value)) !!}</p></div>
                @endforeach
                @if($record->notes)
                    <div class="hist-text"><h4>Notes</h4><p>{!! nl2br(e($record->notes)) !!}</p></div>
                @endif
                @if(!$parts['facts'] && !$parts['texts'] && !$record->notes)<p class="text-muted mb-0">No details were recorded.</p>@endif
            </section>

            <section class="inv-panel sale-section">
                <h3>Files <span class="text-muted fw-normal fs-7">{{ count($files) }}</span></h3>
                @if($files)
                <ul class="hist-files">
                    @foreach($files as $file)
                        <li><i class="bi {{ $fileIcon($file['type']) }}" aria-hidden="true"></i><a href="{{ $file['url'] }}" target="_blank" rel="noopener">{{ $file['name'] }}</a><span>{{ $size($file['size']) }}</span></li>
                    @endforeach
                </ul>
                @else
                    <p class="text-muted fs-7 mb-0">No files attached. Add a follow-up record to attach documents later.</p>
                @endif
            </section>
        </div>

        <aside class="col-xl-4">
            <section class="inv-panel sale-section">
                <h3>About this record</h3>
                <dl class="sale-facts">
                    <div><dt>Type</dt><dd>{{ PH::label($record->category_tag) }}</dd></div>
                    <div><dt>Happened</dt><dd>{{ $date ? $date->format('j M Y') : 'Date not recorded' }}</dd></div>
                    <div><dt>Applies to</dt><dd>{{ $unit ?? 'The whole property' }}</dd></div>
                    <div><dt>Recorded by</dt><dd>{{ $record->creator ? trim($record->creator->first_name . ' ' . $record->creator->last_name) : 'Erty' }}</dd></div>
                    <div><dt>Recorded on</dt><dd>{{ $record->created_at?->format('j M Y, H:i') }}</dd></div>
                </dl>
                <p class="hist-kept"><i class="bi bi-lock" aria-hidden="true"></i>Records are kept as written. If something here is wrong, add a follow-up record that explains the correction.</p>
            </section>
            @if($related->isNotEmpty())
            <section class="inv-panel sale-section">
                <h3>Also at {{ $record->complex->title }}</h3>
                <ul class="hist-related">
                    @foreach($related as $other)
                        @php $o = PH::category($other->category_tag) ?? ['icon' => 'bi-journal', 'tone' => 'slate']; $od = PH::eventDate($other); @endphp
                        <li><span class="hist-dot is-small hist-tone-{{ $o['tone'] }}" aria-hidden="true"><i class="bi {{ $o['icon'] }}"></i></span><a href="{{ route('history.show', $other->id) }}">{{ PH::title($other) }}</a><small>{{ $od?->format('j M Y') }}</small></li>
                    @endforeach
                </ul>
            </section>
            @endif
        </aside>
    </div>
</div>
</div>
@endsection
