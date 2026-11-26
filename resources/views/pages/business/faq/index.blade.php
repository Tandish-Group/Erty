@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Help & FAQ'])

@php $questionCount = collect($sections)->sum(fn ($s) => count($s['items'])); @endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace faq-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading faq-heading">
            <div>
                <span class="tenant-eyebrow">Help</span>
                <h1>How can we help?</h1>
                <p>Answers to common questions about running your properties, tenants, rent and team in {{ config('app.name') }}.</p>
            </div>
        </header>

        <div class="faq-search" role="search">
            <i class="fas fa-search" aria-hidden="true"></i>
            <label class="visually-hidden" for="faq-search">Search the help</label>
            <input type="search" id="faq-search" class="form-control" placeholder="Search {{ $questionCount }} answers, for example &quot;late fee&quot; or &quot;notice&quot;" autocomplete="off">
            <span class="faq-search-count" data-faq-count aria-live="polite"></span>
        </div>

        <div class="faq-layout">
            <nav class="tenant-panel tenant-panel--padded faq-topics" aria-label="Topics">
                <div class="faq-topics-heading">Topics</div>
                <ul class="faq-topic-list">
                    @foreach($sections as $section)
                        <li>
                            <a href="#{{ $section['key'] }}" class="faq-topic" data-faq-topic="{{ $section['key'] }}">
                                <i class="fas {{ $section['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $section['title'] }}</span>
                                <em data-faq-topic-count>{{ count($section['items']) }}</em>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="faq-content">
                @foreach($sections as $section)
                    <section class="tenant-panel tenant-panel--padded faq-section" id="{{ $section['key'] }}" data-faq-section aria-labelledby="{{ $section['key'] }}-title">
                        <h2 id="{{ $section['key'] }}-title"><i class="fas {{ $section['icon'] }}" aria-hidden="true"></i>{{ $section['title'] }}</h2>
                        <div class="faq-items">
                            @foreach($section['items'] as $item)
                                <details class="faq-item" id="{{ $item['id'] }}" data-faq-item>
                                    <summary>
                                        <span class="faq-question">{{ $item['question'] }}</span>
                                        <i class="fas fa-chevron-down faq-chevron" aria-hidden="true"></i>
                                    </summary>
                                    <div class="faq-answer">
                                        {!! $item['answer'] !!}
                                        <a href="#{{ $item['id'] }}" class="faq-permalink" data-faq-copy title="Copy a link to this answer"><i class="fas fa-link" aria-hidden="true"></i> Link to this answer</a>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div class="tenant-panel tenant-panel--padded faq-empty" data-faq-empty hidden>
                    <div class="tenant-empty">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <h3>No answers match</h3>
                        <p>Try a shorter or different word, or ask for help below.</p>
                    </div>
                </div>

                <aside class="faq-help">
                    <div>
                        <h2>Still need help?</h2>
                        <p>
                            Send a support ticket and say which page you were on and what you expected to happen.
                            @if($supportEmail)
                                You can also email <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
                            @else
                                For access to more of {{ config('app.name') }}, ask your account owner.
                            @endif
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('support.tickets.index') }}" class="btn btn-light">My tickets</a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#support"><i class="fas fa-life-ring me-1" aria-hidden="true"></i>Contact support</button>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
@endsection
