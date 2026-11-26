{{--
    A ticket's conversation: the original post, then replies and status changes in order.
    $ticket (with user, messages.user, attachments loaded), $forAdmin (bool), $fileUrl (fn(attachment) => url).
--}}
@php
    $nameOf = fn ($u) => $u ? (trim($u->first_name . ' ' . $u->last_name) ?: $u->email) : 'Someone';
    $filesFor = fn ($messageId) => $ticket->attachments->where('message_id', $messageId);
    $statusName = fn ($s) => ($forAdmin ? \App\Models\SupportTicket::ADMIN_STATUS_LABELS : \App\Models\SupportTicket::STATUSES)[$s] ?? $s;
    $fileList = function ($files) use ($fileUrl) {
        if ($files->isEmpty()) return '';
        $html = '<div class="support-files">';
        foreach ($files as $f) {
            $icon = $f->isImage() ? 'fa-file-image' : (str_contains((string) $f->mime, 'pdf') ? 'fa-file-pdf' : 'fa-file-alt');
            $html .= '<a href="' . e($fileUrl($f)) . '"><i class="fas ' . $icon . '" aria-hidden="true"></i><span>' . e($f->original_name) . '</span><small>' . e(file_size($f->size)) . '</small></a>';
        }
        return $html . '</div>';
    };
@endphp
<div class="support-thread">
    <article class="support-message">
        <div class="support-message-head">
            <span><strong>{{ $forAdmin ? $nameOf($ticket->user) : 'You' }}</strong> posted this ticket</span>
            <time datetime="{{ $ticket->created_at?->toIso8601String() }}">{{ $ticket->created_at?->format('j M Y, H:i') }}</time>
        </div>
        <div class="support-message-body">{!! \App\Support\SafeHtml::clean($ticket->description) !!}</div>
        {!! $fileList($filesFor(null)) !!}
    </article>

    @foreach($ticket->messages as $message)
        @if($message->body !== null || $filesFor($message->id)->isNotEmpty())
            <article class="support-message {{ $message->is_staff ? 'is-staff' : '' }}">
                <div class="support-message-head">
                    <span>
                        <strong>{{ !$forAdmin && (int) $message->user_id === (int) auth()->id() ? 'You' : ($message->is_staff && !$forAdmin ? 'Support' : $nameOf($message->user)) }}</strong>
                        @if($message->is_staff)<span class="badge badge-light-primary ms-1">Support team</span>@endif
                    </span>
                    <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('j M Y, H:i') }}</time>
                </div>
                @if($message->body)<div class="support-message-body">{!! \App\Support\SafeHtml::clean($message->body) !!}</div>@endif
                {!! $fileList($filesFor($message->id)) !!}
            </article>
        @endif
        @if($message->status_to)
            <div class="support-event">
                <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                <span>Status changed from <strong>{{ $statusName($message->status_from) }}</strong> to <strong>{{ $statusName($message->status_to) }}</strong>
                    @if($forAdmin) by {{ $nameOf($message->user) }}@endif · {{ $message->created_at?->format('j M, H:i') }}</span>
            </div>
        @endif
    @endforeach
</div>
