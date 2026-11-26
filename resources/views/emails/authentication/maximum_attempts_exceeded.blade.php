@extends('emails.layouts.plain')

@section('content')
    <p style="margin:0 0 16px;">Hello {{ $user->first_name ?? '' }},</p>

    <p style="margin:0 0 16px;">Your {{ config('app.name') }} account has been suspended because the sign-in code was entered incorrectly too many times.</p>

    <p style="margin:0 0 16px;">If this was you, ask your account owner or administrator to restore access{{ config('app.support_email') ? ', or email ' . config('app.support_email') : '' }}. If it was not you, someone may know your password: change it once your account is restored.</p>

    <p style="margin:0;">{{ config('app.name') }}</p>
@endsection
