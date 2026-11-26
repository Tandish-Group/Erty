{{-- Standalone layout for the error pages (401–503 and the blocked-address page). Self-contained: inline
     styles only, no theme files, so it renders even when the app or its assets are failing. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ config('app.name', 'Erty') }}</title>
    <link rel="icon" href="{{ asset('home.png') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
               background: #f3f6f8; color: #243b49; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .error-card { width: 100%; max-width: 480px; background: #fff; border: 1px solid #dce5eb; border-radius: 14px; padding: 36px 32px; text-align: center; }
        .error-logo img { height: 40px; }
        .error-code { margin: 28px 0 6px; color: #087d84; font-size: 13px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; }
        h1 { margin: 0 0 12px; font-size: 26px; letter-spacing: -.4px; }
        p { margin: 0 0 26px; color: #5d7280; font-size: 14px; line-height: 1.7; }
        p a { color: #087980; }
        .error-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .error-actions a { display: inline-block; padding: 11px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; }
        .btn-primary { background: #087d84; color: #fff; }
        .btn-primary:hover { background: #066970; }
        .btn-light { background: #eef3f5; color: #354e5d; }
    </style>
</head>
<body>
    <main class="error-card">
        <a class="error-logo" href="{{ url('/') }}"><img src="{{ asset(config('app.logo')) }}" alt="{{ config('app.name') }}"></a>
        <div class="error-code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <div class="error-actions">
            @section('actions')
                <a class="btn-primary" href="{{ url('/') }}">Go to the home page</a>
                <a class="btn-light" href="javascript:history.back()">Go back</a>
            @show
        </div>
    </main>
</body>
</html>
