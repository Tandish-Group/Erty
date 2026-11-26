@extends('errors.partials.app')

@section('title', 'Access blocked')
@section('code', 'Access blocked')
@section('heading', 'Your address has been blocked')
@section('message')
    Too many unusual requests came from your network, so access from it is paused.
    @if(config('app.support_email'))
        If you think this is a mistake, email <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a> with your IP address and when it happened.
    @else
        If you think this is a mistake, contact your account administrator with your IP address and when it happened.
    @endif
@endsection
@section('actions')
@endsection
