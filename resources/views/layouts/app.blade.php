<!DOCTYPE html>
<html lang="en">
    <!--begin::Head-->
    <head>

        <title>@yield('title', config('app.name') . ' - Sign in')</title>
        <meta charset="utf-8" />
        <meta name="description" content="@yield('description', config('app.name') . ' property management: properties, tenants, rent and maintenance in one place.')">
        <meta property="og:title" content="@yield('title', config('app.name'))">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset(config('app.mail_logo')) }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <link rel="canonical" href="{{ url()->current() }}">
        {{-- A private business app: its sign-in pages are not for search engines. --}}
        <meta name="robots" content="@yield('robots', 'noindex, nofollow')">
        <meta name="author" content="{{ config('app.name') }}">
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/png" href="{{ app('asset_versioned')('home.png') }}">
        <link rel="apple-touch-icon" href="{{ app('asset_versioned')('home.png') }}">
        <!--begin::Fonts-->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
        <!--end::Fonts-->
        <!--begin::Global Stylesheets Bundle(used by all pages)-->
        <link href="{{ app('asset_versioned')('include/dashboard/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ app('asset_versioned')('include/dashboard/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
        <!--end::Global Stylesheets Bundle-->
        <link href="{{ app('asset_versioned')('css/loader.css') }} " rel="stylesheet" type="text/css" />


        <link href="{{ app('asset_versioned')('css/scrollbar.css') }}" rel="stylesheet">

        @yield('styles')

    </head>
    <!--end::Head-->
    <!--begin::Body-->
    <body id="kt_body" class="bg-body">

        <div class="loader-wrap">
            <div class="preloader"></div>
            <div class="layer layer-one"><span class="overlay"></span></div>
            <div class="layer layer-two"><span class="overlay"></span></div>        
            <div class="layer layer-three"><span class="overlay"></span></div>        
        </div>
        <!--begin::Main-->
        <div class="d-flex flex-column flex-root" id="app">
            <!--begin::Authentication - Sign-in -->
            {{-- Pages may replace the illustrated background (the sign-in page uses its own split layout). --}}
            <div class="d-flex flex-column flex-column-fluid bgi-position-y-bottom position-x-center bgi-no-repeat bgi-size-contain bgi-attachment-fixed @yield('auth_background', 'bg-dashboard')">
                @yield('content')
            </div>
            <!--end::Authentication - Sign-in-->
        </div>
        <!--end::Main-->
        <!--begin::scripts-->
        @include('layouts.scripts.scripts')
        <!--end::scripts-->

    </body>
    <!--end::Body-->
</html>
