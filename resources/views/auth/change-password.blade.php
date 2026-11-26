@extends('layouts.app')

@section('title', 'Choose your password | ' . config('app.name'))
@section('auth_background', '')

@section('styles')
    <link href="{{ app('asset_versioned')('css/auth-split.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
<div class="auth-split">
    <main class="auth-split__form">
        <span class="auth-split__logo"><img alt="{{ config('app.name') }}" src="{{ asset(config('app.logo')) }}" /></span>

        <div class="auth-split__body">
            <div class="auth-split__icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            </div>
            <h1 class="auth-split__title">Choose your password</h1>
            <p class="auth-split__lead">You signed in with a temporary password. Set your own to continue to {{ config('app.name') }}.</p>

            <form method="POST" action="{{ route('password.change.update') }}" class="form w-100" data-change-password>
                @csrf
                <div class="fv-row mb-6">
                    <label class="form-label fs-6 fw-bolder" for="password">New password</label>
                    <div class="auth-split__password">
                        <input class="form-control form-control-lg form-control-solid @error('password') is-invalid @enderror" type="password" name="password" id="password" autocomplete="new-password" required minlength="12" aria-describedby="password_rules" autofocus />
                        <button type="button" class="auth-split__toggle" data-password-toggle aria-controls="password" aria-pressed="false">Show</button>
                    </div>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text" id="password_rules">At least 12 characters, with upper- and lower-case letters and a number.</div>
                </div>
                <div class="fv-row mb-8">
                    <label class="form-label fs-6 fw-bolder" for="password_confirmation">Repeat new password</label>
                    <input class="form-control form-control-lg form-control-solid" type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" required />
                </div>
                <button type="submit" class="btn btn-lg btn-primary w-100 mb-6">Save and continue</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="auth-split__note">
                @csrf
                <span>Not you? <button type="submit" class="btn btn-link link-primary fw-bold p-0 align-baseline">Sign out</button></span>
            </form>
        </div>

        <footer class="auth-split__footer">&copy; {{ date('Y') }} {{ config('app.name') }}</footer>
    </main>

    @include('auth.partials.brand-panel')
</div>
<script>
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
