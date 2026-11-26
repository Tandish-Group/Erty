@extends('emails.layouts.plain')

@section('content')
    @php
        $poster = $ticket->user;
        $posterName = $poster ? trim($poster->first_name . ' ' . $poster->last_name) : 'Someone';
    @endphp
    @if($kind === 'opened')
        <p style="margin:0 0 12px;"><strong>{{ $posterName }}</strong>@if($poster?->email) ({{ $poster->email }})@endif posted a support ticket.</p>
        <p style="margin:0 0 6px;color:#7e8299;font-size:13px;">{{ $ticket->reference }} · {{ $ticket->topicLabel() }}</p>
        <div style="margin:0 0 20px;padding:14px 16px;background:#f5f8fa;border-radius:6px;">{!! \App\Support\SafeHtml::clean($ticket->description) !!}</div>
    @else
        <p style="margin:0 0 12px;">Hello {{ $poster?->first_name ?: 'there' }},</p>
        @if($message?->body)
            <p style="margin:0 0 12px;">There is a reply on your support ticket <strong>{{ $ticket->reference }}</strong> ({{ $ticket->topicLabel() }}):</p>
            <div style="margin:0 0 16px;padding:14px 16px;background:#f5f8fa;border-radius:6px;">{!! \App\Support\SafeHtml::clean($message->body) !!}</div>
        @endif
        <p style="margin:0 0 20px;">Status: <strong>{{ $ticket->statusLabel() }}</strong>.@if($ticket->status === 'waiting_on_user') Please reply so we can carry on.@elseif($ticket->status === 'resolved') If it is not sorted out, reply and the ticket opens again.@endif</p>
    @endif
    <p style="margin:0;"><a href="{{ $url }}" style="display:inline-block;background:#087d84;color:#ffffff;text-decoration:none;font-weight:bold;padding:11px 20px;border-radius:6px;">Open the ticket</a></p>
@endsection
