@extends('pages.business.layouts.app')

@section('content')
@include('pages.platform.partials.header', ['crumbs' => ['Support' => null]])
@php
    $tabs = ['active' => ['Needs attention', $activeCount]] + collect(\App\Models\SupportTicket::ADMIN_STATUS_LABELS)->map(fn ($label, $key) => [$label, $counts[$key] ?? 0])->all() + ['all' => ['All', $counts->sum()]];
    $tabUrl = fn ($key) => route('platform.support.index', array_filter(['status' => $key, 'topic' => $topicFilter, 'priority' => $priority ?: null, 'q' => $search ?: null]));
@endphp
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        @include('pages.platform.partials.nav', [
            'title' => 'Support inbox',
            'intro' => 'Tickets posted by everyone on this installation. Reply, set the status, and the person who posted it is notified.',
        ])

        <div class="tenant-metrics mb-7">
            <div><span>Open</span><strong class="{{ ($counts['open'] ?? 0) ? 'text-warning' : '' }}">{{ $counts['open'] ?? 0 }}</strong><small>Waiting for a first answer or back from the user</small></div>
            <div><span>In progress</span><strong>{{ $counts['in_progress'] ?? 0 }}</strong><small>Being worked on</small></div>
            <div><span>Waiting on user</span><strong>{{ $counts['waiting_on_user'] ?? 0 }}</strong><small>Answered, waiting for a reply</small></div>
            <div><span>Resolved</span><strong>{{ ($counts['resolved'] ?? 0) + ($counts['closed'] ?? 0) }}</strong><small>Resolved or closed</small></div>
        </div>

        <section class="tenant-panel tenant-panel--padded">
            <nav class="support-tabs" aria-label="Ticket status">
                @foreach($tabs as $key => [$label, $count])
                    <a href="{{ $tabUrl($key) }}" class="{{ $status === $key ? 'is-active' : '' }}">{{ $label }} <em>{{ $count }}</em></a>
                @endforeach
            </nav>

            <form method="get" class="platform-filters mb-5" role="search">
                <input type="hidden" name="status" value="{{ $status }}">
                <label for="support_search" class="visually-hidden">Search tickets</label>
                <input id="support_search" type="search" name="q" value="{{ $search }}" class="form-control form-control-solid" placeholder="Reference, text, name or email">
                <label for="support_topic" class="visually-hidden">Topic</label>
                <select id="support_topic" name="topic" class="form-select form-select-solid" onchange="this.form.submit()">
                    <option value="">All topics</option>
                    @foreach($topics as $t)<option value="{{ $t->id }}" @selected($topicFilter === $t->id)>{{ $t->topic }}</option>@endforeach
                </select>
                <label for="support_priority" class="visually-hidden">Priority</label>
                <select id="support_priority" name="priority" class="form-select form-select-solid" onchange="this.form.submit()">
                    <option value="">Any priority</option>
                    @foreach(\App\Models\SupportTicket::PRIORITIES as $key => $label)<option value="{{ $key }}" @selected($priority === $key)>{{ $label }}</option>@endforeach
                </select>
                <button class="btn btn-light-primary">Search</button>
                @if($filtered)<a href="{{ route('platform.support.index', ['status' => $status]) }}" class="btn btn-light">Clear</a>@endif
            </form>

            @if($tickets->isEmpty())
                @if($status === 'active' && !$filtered)
                    <div class="platform-empty"><i class="fas fa-check-circle" aria-hidden="true"></i>Nothing waiting: every ticket has been answered.</div>
                @else
                    <div class="tenant-empty"><i class="fas fa-life-ring" aria-hidden="true"></i><h3>No tickets match</h3><p>Try another status, topic or search.</p></div>
                @endif
            @else
                <div class="tenant-table-scroll" role="region" aria-label="Support tickets" tabindex="0">
                    <table class="tenant-table" style="min-width:0">
                        <thead><tr><th>Ticket</th><th class="d-none d-md-table-cell">Posted by</th><th>Status</th><th class="d-none d-lg-table-cell">Priority</th><th class="d-none d-sm-table-cell">Last activity</th></tr></thead>
                        <tbody>
                        @foreach($tickets as $ticket)
                            <tr>
                                <td>
                                    <a href="{{ route('platform.support.show', $ticket->reference) }}" class="support-subject">{{ $ticket->subject(70) ?: $ticket->topicLabel() }}</a>
                                    <small>{{ $ticket->reference }} · {{ $ticket->topicLabel() }}@if($ticket->assignee) · {{ $ticket->assignee->first_name }}@endif</small>
                                </td>
                                <td class="d-none d-md-table-cell">{{ $ticket->user ? trim($ticket->user->first_name . ' ' . $ticket->user->last_name) : '—' }}<small class="d-block text-muted">{{ $ticket->user?->email }}</small></td>
                                <td><span class="support-status is-{{ $ticket->statusTone() }}">{{ $ticket->statusLabel(true) }}</span></td>
                                <td class="d-none d-lg-table-cell"><span class="support-priority is-{{ $ticket->priority }}">{{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</span></td>
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
