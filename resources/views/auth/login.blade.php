@extends('layouts.app')

@section('title', 'Sign in | ' . config('app.name'))
@section('auth_background', '')

@section('styles')
    <link href="{{ app('asset_versioned')('css/auth-split.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
<div class="auth-split">

    {{-- Sign-in form (shown on the right). The ids and field names are used by the sign-in validation script. --}}
    <main class="auth-split__form">
        <a href="/" class="auth-split__logo" aria-label="{{ config('app.name') }}">
            <img alt="{{ config('app.name') }}" src="{{ asset(config('app.logo')) }}" />
        </a>

        <div class="auth-split__body">
            <h1 class="auth-split__title">Sign in</h1>
            <p class="auth-split__lead">Welcome back. Enter your details to continue to {{ config('app.name') }}.</p>

            <form class="form w-100" id="kt_sign_in_form" method="POST" action="{{ route('login') }}" novalidate="novalidate">
                @csrf
                <div class="fv-row mb-8">
                    <label class="form-label fs-6 fw-bolder" for="email">Email</label>
                    <input class="form-control form-control-lg form-control-solid" type="email" name="email" id="email" autocomplete="username" value="{{ old('email') }}" autofocus />
                </div>

                <div class="fv-row mb-8">
                    <div class="d-flex flex-stack mb-2">
                        <label class="form-label fw-bolder fs-6 mb-0" for="password">Password</label>
                        <a href="{{ route('password.request') }}" class="link-primary fs-6 fw-bolder">Forgot password?</a>
                    </div>
                    <div class="auth-split__password">
                        <input class="form-control form-control-lg form-control-solid" type="password" name="password" id="password" autocomplete="current-password" />
                        <button type="button" class="auth-split__toggle" data-password-toggle aria-controls="password" aria-pressed="false">Show</button>
                    </div>
                </div>

                <button type="submit" id="kt_sign_in_submit" class="btn btn-lg btn-primary w-100 mb-6">
                    <span class="indicator-label">Continue</span>
                    <span class="indicator-progress">Please wait...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>

                <p class="auth-split__note">
                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M8 1 2 3.5v4C2 11 4.6 14.1 8 15c3.4-.9 6-4 6-7.5v-4L8 1Zm-1 10.2L4.3 8.5l1.1-1.1L7 9l3.6-3.6 1.1 1.1L7 11.2Z"/></svg>
                    <span>For your security, we'll email you a 6-digit code to finish signing in.</span>
                </p>
            </form>
        </div>

        <footer class="auth-split__footer">&copy; {{ date('Y') }} {{ config('app.name') }}</footer>
    </main>

    @include('auth.partials.brand-panel')
</div>

<script>
    // Show or hide the password.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-password-toggle]');
        if (!btn) return;
        var input = document.getElementById(btn.getAttribute('aria-controls'));
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        input.focus();
    });
</script>
@endsection
