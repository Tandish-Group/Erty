@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Support tickets'])
@php $accept = implode(',', array_map(fn ($e) => '.' . $e, $fileRules['extensions'])); $filesLeft = max(0, $fileRules['per_ticket'] - $ticket->attachments->count()); @endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow"><a href="{{ route('support.tickets.index') }}">My support tickets</a> · {{ $ticket->reference }}</span>
                <h1>{{ $ticket->topicLabel() }}</h1>
                <p><span class="support-status is-{{ $ticket->statusTone() }}">{{ $ticket->statusLabel() }}</span>
                    <span class="ms-2">Posted {{ $ticket->created_at?->format('j M Y') }} · last activity {{ $ticket->last_activity_at?->diffForHumans() }}</span></p>
            </div>
        </header>

        @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif

        <div class="support-layout">
            <div class="d-grid gap-5">
                @include('pages.business.support.partials.thread', ['forAdmin' => false, 'fileUrl' => fn ($f) => route('support.tickets.file', [$ticket->reference, $f->id])])

                @if($ticket->isClosed())
                    <div class="tenant-panel tenant-panel--padded text-muted">This ticket is closed. If you still need help, <a href="#" data-bs-toggle="modal" data-bs-target="#support">post a new ticket</a>.</div>
                @else
                    <form method="post" action="{{ route('support.tickets.reply', $ticket->reference) }}" enctype="multipart/form-data" class="tenant-panel tenant-panel--padded support-reply">
                        @csrf
                        <label for="reply_body" class="form-label fw-semibold">{{ $ticket->status === 'resolved' ? 'Not sorted out? Reply and the ticket opens again' : 'Add a reply' }}</label>
                        <textarea id="reply_body" name="body" class="form-control" maxlength="20000" placeholder="Add more detail or answer the support team's question">{{ old('body') }}</textarea>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                            @if($filesLeft)
                                <div>
                                    <label for="reply_files" class="form-label small text-muted mb-1">Files (up to {{ $filesLeft }} more, {{ round($fileRules['max_kb'] / 1024) }} MB each)</label>
                                    <input id="reply_files" type="file" name="files[]" class="form-control form-control-sm" multiple accept="{{ $accept }}">
                                </div>
                            @else
                                <span class="small text-muted">This ticket has the most files it can take.</span>
                            @endif
                            <button class="btn btn-primary"><i class="fas fa-paper-plane me-1" aria-hidden="true"></i>Send reply</button>
                        </div>
                    </form>
                @endif
            </div>

            <aside class="tenant-panel tenant-panel--padded support-side">
                <h3>About this ticket</h3>
                <dl>
                    <dt>Reference</dt><dd>{{ $ticket->reference }}</dd>
                    <dt>Topic</dt><dd>{{ $ticket->topicLabel() }}</dd>
                    <dt>Status</dt><dd>{{ $ticket->statusLabel() }}</dd>
                    @if($ticket->resolved_at)<dt>Resolved</dt><dd>{{ $ticket->resolved_at->format('j M Y') }}</dd>@endif
                    <dt>Files</dt><dd>{{ $ticket->attachments->count() }} of {{ $fileRules['per_ticket'] }}</dd>
                </dl>
                <p class="text-muted small mt-5 mb-0">You get a notification and an email when the support team replies.</p>
            </aside>
        </div>
    </div>
</div>
@endsection
