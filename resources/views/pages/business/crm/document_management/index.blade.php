@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Document centre'])
@php
    $typeIcons = ['pdf' => 'fa-file-pdf', 'image' => 'fa-file-image', 'word' => 'fa-file-word', 'sheet' => 'fa-file-excel', 'other' => 'fa-file'];
    $tab = fn (string $source) => route('document-center.index', array_filter(array_merge(request()->except('page', 'source'), ['source' => $source])));
    $narrowed = $filters['property'] || $filters['type'] || $filters['q'] !== '';
@endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace docs-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">@if(can_open('/customer_relationship'))<a href="/customer_relationship">CRM</a>@else CRM @endif · Documents</span>
                <h1>Document centre</h1>
                <p>Every file Erty keeps for the business in one place: tenancy paperwork, payment receipts, expense evidence, property records, the legal library and agent CVs. You see the kinds of file your role can open in their own section{{ app(\App\Services\Access\PropertyAccess::class)->restricted() ? ', for the properties assigned to you' : '' }}.</p>
            </div>
        </header>

        @if(!$sources)
            <section class="tenant-panel">
                <div class="tenant-empty"><i class="fas fa-folder-open" aria-hidden="true"></i><h3>No documents for your role</h3>
                    <p>Your role does not include any of the sections that keep files (tenants, payments, expenses, property history, legal documents or agents). Ask the account owner if you need them.</p></div>
            </section>
        @else
            <nav class="docs-tabs mb-5" aria-label="Kinds of document">
                <a href="{{ $tab('') }}" class="docs-tab {{ $filters['source'] === '' ? 'is-active' : '' }}" @if($filters['source'] === '') aria-current="page" @endif>
                    <i class="fas fa-layer-group" aria-hidden="true"></i><span>All documents</span><em>{{ $total }}</em>
                </a>
                @foreach($sources as $key => $source)
                    <a href="{{ $tab($key) }}" class="docs-tab {{ $filters['source'] === $key ? 'is-active' : '' }}" title="{{ $source['about'] }}" @if($filters['source'] === $key) aria-current="page" @endif>
                        <i class="fas {{ $source['icon'] }}" aria-hidden="true"></i><span>{{ $source['label'] }}</span><em>{{ $counts[$key] ?? 0 }}</em>
                    </a>
                @endforeach
            </nav>

            <form method="get" class="tenant-panel tenant-panel--padded report-filters mb-6" role="search">
                @if($filters['source'])<input type="hidden" name="source" value="{{ $filters['source'] }}">@endif
                <div class="docs-search"><label for="dc_q" class="form-label">Search</label>
                    <input id="dc_q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="File, tenant, agent, reference…"></div>
                <div><label for="dc_property" class="form-label">Property</label>
                    <select id="dc_property" name="property" class="form-select form-select-sm">
                        <option value="">All properties</option>
                        @foreach($properties as $id => $title)<option value="{{ $id }}" @selected($filters['property'] === (int) $id)>{{ $title }}</option>@endforeach
                    </select></div>
                <div><label for="dc_type" class="form-label">File type</label>
                    <select id="dc_type" name="type" class="form-select form-select-sm">
                        <option value="">Any type</option>
                        @foreach($types as $key => $label)<option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>@endforeach
                    </select></div>
                <div><label for="dc_sort" class="form-label">Order</label>
                    <select id="dc_sort" name="sort" class="form-select form-select-sm">
                        @foreach($sorts as $key => $label)<option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>@endforeach
                    </select></div>
                <div class="report-filter-actions"><button class="btn btn-sm btn-primary">Show</button>@if($narrowed || $filters['sort'] !== 'newest')<a href="{{ route('document-center.index', array_filter(['source' => $filters['source']])) }}" class="btn btn-sm btn-light">Reset</a>@endif</div>
            </form>

            @if($filters['property'])
                <p class="docs-note"><i class="fas fa-info-circle" aria-hidden="true"></i>Legal library files and agent CVs belong to the business rather than a property, so a property filter leaves them out.</p>
            @endif
            @if($missing)
                <p class="docs-note docs-note--warn"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i>{{ $missing }} {{ \Illuminate\Support\Str::plural('file', $missing) }} {{ $missing === 1 ? 'is' : 'are' }} recorded but missing from storage. {{ $missing === 1 ? 'It is' : 'They are' }} marked below; upload {{ $missing === 1 ? 'it' : 'them' }} again where {{ $missing === 1 ? 'it was' : 'they were' }} added.</p>
            @endif

            <section class="tenant-panel tenant-panel--padded">
                @if($documents->isEmpty())
                    <div class="tenant-empty"><i class="fas fa-folder-open" aria-hidden="true"></i>
                        @if($narrowed)
                            <h3>No documents match</h3><p>Try another search, property or file type.</p>
                        @else
                            <h3>No documents yet</h3><p>Files appear here as they are added: when a tenancy is set up, a receipt is attached to a payment, evidence is added to an expense, a property history record is saved, a document is uploaded to Legal, or an agent's CV is added.</p>
                        @endif
                    </div>
                @else
                    <div class="tenant-table-scroll" role="region" aria-label="Documents" tabindex="0">
                        <table class="tenant-table docs-table">
                            <thead><tr><th>Document</th><th>Belongs to</th><th class="d-none d-md-table-cell">Property</th><th class="d-none d-lg-table-cell">Added</th><th class="d-none d-lg-table-cell text-end">Size</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                            <tbody>
                            @foreach($documents as $doc)
                                <tr>
                                    <td>
                                        <div class="docs-file">
                                            <i class="fas {{ $typeIcons[$doc['type']] ?? 'fa-file' }} docs-file-icon docs-file-icon--{{ $doc['type'] }}" aria-hidden="true"></i>
                                            <div>
                                                <span class="docs-file-name">{{ $doc['name'] }}</span>
                                                <small>{{ $sources[$doc['source']]['label'] }} · {{ $doc['kind'] }}</small>
                                                @unless($doc['exists'])<span class="crm-chip crm-chip--warn">Missing from storage</span>@endunless
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($doc['related_url'])<a href="{{ $doc['related_url'] }}">{{ $doc['related'] }}</a>@else{{ $doc['related'] }}@endif
                                        @if($doc['reference'])<small class="d-block text-muted">{{ $doc['reference'] }}</small>@endif
                                    </td>
                                    <td class="d-none d-md-table-cell">{{ $doc['property'] ?? '—' }}</td>
                                    <td class="d-none d-lg-table-cell text-nowrap">@if($doc['date'])<span title="{{ $doc['date']->format('j M Y H:i') }}">{{ $doc['date']->format('j M Y') }}</span>@else — @endif</td>
                                    <td class="d-none d-lg-table-cell text-end text-nowrap">{{ $doc['size'] ? file_size($doc['size']) : '—' }}</td>
                                    <td class="text-end">
                                        @if($doc['url'])
                                            <a href="{{ $doc['url'] }}" class="btn btn-sm btn-light" aria-label="Download {{ $doc['name'] }}"><i class="fas fa-download" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">Download</span></a>
                                        @elseif($doc['exists'])
                                            <span class="text-muted small" title="Your role can see this file but not download it">View only</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-5">{{ $documents->links() }}</div>
                @endif
            </section>
        @endif
    </div>
</div>
@endsection
