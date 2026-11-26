@extends('emails.layouts.plain')

@section('content')
    <p style="margin:0 0 16px;">Here is your {{ config('app.name') }} sign-in code. It expires in {{ \App\Models\Otp::EXPIRY_MINUTES }} minutes.</p>

    <p style="margin:0 0 16px;text-align:center;font-size:28px;font-weight:700;letter-spacing:6px;background:#f5f8fa;border-radius:6px;padding:14px;">{{ $otp }}</p>

    <p style="margin:0;">If you did not try to sign in, ignore this email and change your password.</p>
@endsection
