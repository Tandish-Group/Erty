@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Support tickets'])

<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">Support</span>
                <h1>My support tickets</h1>
                <p>Questions and problems you have sent to the team that runs {{ config('app.name') }}, and their answers.</p>
            </div>
            <div class="tenant-heading-actions">
                <a href="{{ route('business.faq') }}" class="btn btn-light"><i class="fas fa-question-circle me-1" aria-hidden="true"></i>Help &amp; FAQ</a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#support"><i class="fas fa-plus me-1" aria-hidden="true"></i>New ticket</button>
            </div>
        </header>


        <section class="tenant-panel tenant-panel--padded">
            @if($tickets->isEmpty())
                <div class="tenant-empty">
                    <i class="fas fa-life-ring" aria-hidden="true"></i>
                    <h3>No tickets yet</h3>
                    <p>If something isn't working or you have a question, the <a href="{{ route('business.faq') }}">Help &amp; FAQ</a> may already answer it. Otherwise, post a ticket.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#support">Post a ticket</button>
                </div>
            @else
                <div class="tenant-table-scroll" role="region" aria-label="Your support tickets" tabindex="0">
                    <table class="tenant-table" style="min-width:0">
                        <thead><tr><th>Ticket</th><th class="d-none d-md-table-cell">Topic</th><th>Status</th><th class="d-none d-sm-table-cell">Last activity</th></tr></thead>
                        <tbody>
                        @foreach($tickets as $ticket)
                            <tr>
                                <td>
                                    <a href="{{ route('support.tickets.show', $ticket->reference) }}" class="support-subject">{{ $ticket->subject(70) ?: $ticket->topicLabel() }}</a>
                                    <small>{{ $ticket->reference }} · posted {{ $ticket->created_at?->format('j M Y') }}@if($ticket->staff_replies_count) · {{ $ticket->staff_replies_count }} {{ \Illuminate\Support\Str::plural('reply', $ticket->staff_replies_count) }}@endif</small>
                                </td>
                                <td class="d-none d-md-table-cell">{{ $ticket->topicLabel() }}</td>
                                <td><span class="support-status is-{{ $ticket->statusTone() }}">{{ $ticket->statusLabel() }}</span></td>
                                <td class="d-none d-sm-table-cell text-nowrap">{{ $ticket->last_activity_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $tickets->links() }}</div>
            @endif
        </section>
    </div>
</div>
@endsection
