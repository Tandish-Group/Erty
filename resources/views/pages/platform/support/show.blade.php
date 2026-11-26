@extends('pages.business.layouts.app')

@section('content')
@include('pages.platform.partials.header', ['crumbs' => ['Support' => route('platform.support.index'), $ticket->reference => null]])
@php
    $poster = $ticket->user;
    $posterName = $poster ? (trim($poster->first_name . ' ' . $poster->last_name) ?: $poster->email) : 'A deleted user';
    $accept = implode(',', array_map(fn ($e) => '.' . $e, $fileRules['extensions']));
    $filesLeft = max(0, $fileRules['per_ticket'] - $ticket->attachments->count());
@endphp
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        @include('pages.platform.partials.nav', [
            'title' => e($ticket->reference) . ' <span class="support-status is-' . e($ticket->statusTone()) . '">' . e($ticket->statusLabel(true)) . '</span>',
            'intro' => $ticket->topicLabel() . ' · posted by ' . $posterName . ' on ' . $ticket->created_at?->format('j M Y, H:i'),
            'actions' => '<a href="' . e(route('platform.support.index')) . '" class="btn btn-light"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>All tickets</a>',
        ])

        <div class="support-layout">
            <div class="d-grid gap-5">
                @include('pages.business.support.partials.thread', ['forAdmin' => true, 'fileUrl' => fn ($f) => route('platform.support.file', [$ticket->reference, $f->id])])

                <form method="post" action="{{ route('platform.support.reply', $ticket->reference) }}" enctype="multipart/form-data" class="tenant-panel tenant-panel--padded support-reply">
                    @csrf
                    <label for="reply_body" class="form-label fw-semibold">Reply to {{ $poster?->first_name ?: 'the poster' }}</label>
                    <textarea id="reply_body" name="body" class="form-control" maxlength="20000" placeholder="Your answer. {{ $poster?->first_name ?: 'They' }} will get a notification and an email.">{{ old('body') }}</textarea>
                    <div class="row g-3 align-items-end mt-1">
                        <div class="col-sm-6">
                            <label for="reply_status" class="form-label small text-muted mb-1">Then set the status to</label>
                            <select id="reply_status" name="status" class="form-select form-select-sm">
                                @foreach(\App\Models\SupportTicket::ADMIN_STATUS_LABELS as $key => $label)
                                    <option value="{{ $key }}" @selected(old('status', 'waiting_on_user') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            @if($filesLeft)
                                <label for="reply_files" class="form-label small text-muted mb-1">Files (up to {{ $filesLeft }} more)</label>
                                <input id="reply_files" type="file" name="files[]" class="form-control form-control-sm" multiple accept="{{ $accept }}">
                            @else
                                <span class="small text-muted">This ticket has the most files it can take.</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button class="btn btn-primary"><i class="fas fa-paper-plane me-1" aria-hidden="true"></i>Send reply</button>
                    </div>
                </form>
            </div>

            <aside class="d-grid gap-5">
                <form method="post" action="{{ route('platform.support.update', $ticket->reference) }}" class="tenant-panel tenant-panel--padded support-side">
                    @csrf
                    <h3>Handle this ticket</h3>
                    <div class="mb-3">
                        <label for="t_status" class="form-label small text-muted mb-1">Status</label>
                        <select id="t_status" name="status" class="form-select form-select-sm">
                            @foreach(\App\Models\SupportTicket::ADMIN_STATUS_LABELS as $key => $label)<option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="t_priority" class="form-label small text-muted mb-1">Priority</label>
                        <select id="t_priority" name="priority" class="form-select form-select-sm">
                            @foreach(\App\Models\SupportTicket::PRIORITIES as $key => $label)<option value="{{ $key }}" @selected($ticket->priority === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="t_assignee" class="form-label small text-muted mb-1">Handled by</label>
                        <select id="t_assignee" name="assigned_to" class="form-select form-select-sm">
                            <option value="">Nobody yet</option>
                            @foreach($admins as $admin)<option value="{{ $admin->id }}" @selected((int) $ticket->assigned_to === (int) $admin->id)>{{ trim($admin->first_name . ' ' . $admin->last_name) ?: $admin->email }}{{ (int) $admin->id === (int) auth()->id() ? ' (you)' : '' }}</option>@endforeach
                        </select>
                    </div>
                    <button class="btn btn-light-primary w-100">Save</button>
                    <p class="small text-muted mt-3 mb-0">Changing the status tells {{ $poster?->first_name ?: 'the poster' }}.</p>
                </form>

                <div class="tenant-panel tenant-panel--padded support-side">
                    <h3>Posted by</h3>
                    <dl>
                        <dt>Name</dt><dd>{{ $posterName }}</dd>
                        @if($poster?->email)<dt>Email</dt><dd><a href="mailto:{{ $poster->email }}">{{ $poster->email }}</a></dd>@endif
                        <dt>Role</dt><dd>{{ $poster?->isBusinessOwner() ? 'Account owner' : ($poster?->businessRole?->name ?? '—') }}</dd>
                        <dt>Files</dt><dd>{{ $ticket->attachments->count() }} of {{ $fileRules['per_ticket'] }}</dd>
                    </dl>
                    @if($otherTickets->isNotEmpty())
                        <h3 class="mt-6">Their other tickets</h3>
                        <ul class="list-unstyled mb-0 small">
                            @foreach($otherTickets as $other)
                                <li class="mb-2"><a href="{{ route('platform.support.show', $other->reference) }}">{{ $other->reference }}</a> · {{ $other->statusLabel(true) }}<span class="d-block text-muted">{{ $other->subject(50) }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection
