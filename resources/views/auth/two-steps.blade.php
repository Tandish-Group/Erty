@extends('layouts.app')

@section('title', 'Enter your code | ' . config('app.name'))
@section('auth_background', '')

@section('styles')
    <link href="{{ app('asset_versioned')('css/auth-split.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
<div class="auth-split">

    {{-- The code step (shown on the right). js/pages/auth/auth.js drives it. --}}
    <main class="auth-split__form">
        <a href="{{ route('login') }}" class="auth-split__logo" aria-label="{{ config('app.name') }}">
            <img alt="{{ config('app.name') }}" src="{{ asset(config('app.logo')) }}" />
        </a>

        <div class="auth-split__body">
            <div class="auth-split__icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            </div>
            <h1 class="auth-split__title">Check your email</h1>
            <p class="auth-split__lead">
                We sent a 6-digit code to <strong class="text-dark">{{ $maskedEmail }}</strong>.
                It expires in {{ $expiresIn }} minutes.
            </p>

            <form class="form w-100" novalidate="novalidate" id="kt_sing_in_two_steps_form"
                  data-verify-url="{{ url('/validateOTP') }}" data-resend-url="{{ route('resendOTP') }}"
                  data-success-url="{{ url('/dashboard') }}" data-login-url="{{ route('login') }}">
                <fieldset class="mb-3">
                    <legend class="form-label fs-6 fw-bolder mb-3">Security code</legend>
                    <div class="auth-split__code" data-otp-boxes>
                        @foreach(['opt_one', 'opt_two', 'opt_three', 'opt_four', 'opt_five', 'opt_six'] as $i => $name)
                            <input type="text" name="{{ $name }}" maxlength="1" inputmode="numeric" pattern="[0-9]*"
                                   class="form-control form-control-solid otp-input" aria-label="Digit {{ $i + 1 }} of 6"
                                   @if($i === 0) autocomplete="one-time-code" autofocus @else autocomplete="off" @endif />
                        @endforeach
                    </div>
                </fieldset>
                <div id="otp_message" class="auth-split__message" role="alert" aria-live="assertive"></div>

                <button type="submit" id="kt_sing_in_two_steps_submit" class="btn btn-lg btn-primary w-100 mt-5 mb-6">
                    <span class="indicator-label">Verify and sign in</span>
                    <span class="indicator-progress">Checking...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </form>

            <p class="auth-split__resend">
                <span id="otp_countdown">Didn't get it? You can ask for a new code in 60s.</span>
                <button type="button" class="btn btn-link link-primary fw-bolder p-0 align-baseline" id="resend_otp" disabled>
                    <span class="indicator-label">Resend code</span>
                    <span class="indicator-progress">Sending... <span class="spinner-border spinner-border-sm align-middle ms-1"></span></span>
                </button>
            </p>
            <p class="auth-split__note mt-6">
                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M8 1 2 3.5v4C2 11 4.6 14.1 8 15c3.4-.9 6-4 6-7.5v-4L8 1Zm-1 10.2L4.3 8.5l1.1-1.1L7 9l3.6-3.6 1.1 1.1L7 11.2Z"/></svg>
                <span>Check your spam folder if it hasn't arrived. Not you? <a href="{{ route('login') }}" class="link-primary fw-bold">Sign in with a different account</a>.</span>
            </p>
        </div>

        <footer class="auth-split__footer">&copy; {{ date('Y') }} {{ config('app.name') }}</footer>
    </main>

    @include('auth.partials.brand-panel')
</div>
@endsection
