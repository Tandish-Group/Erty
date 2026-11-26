<!DOCTYPE html>
<html lang="en">
    <!--begin::Head-->
    <head>
        <base href="">
        <title>{{ config('app.name', 'Erty') }}</title>
        <meta charset="utf-8" />
        <meta name="description" content="{{ config('app.name') }} property management">
        <meta name="robots" content="noindex, nofollow">
        <meta name="author" content="{{ config('app.name') }}">
        <meta name="viewport" content="width=device-width, initial-scale=1" />
    
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        <link rel="shortcut icon" href="{{ app('asset_versioned')('home.png') }}" type="image/x-icon">
        <link rel="icon" href="{{ app('asset_versioned')('home.png') }}" type="image/x-icon">
        <!--begin::Fonts-->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
        <!--end::Fonts-->
        <!--begin::Page Vendor Stylesheets(used by this page)-->
        <!--end::Page Vendor Stylesheets-->
        <!--begin::Global Stylesheets Bundle(used by all pages)-->
        <link href="{{ app('asset_versioned')('include/dashboard/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />

        <link href="{{ app('asset_versioned')('include/dashboard/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
        <!--end::Global Stylesheets Bundle-->


        <link href="{{ app('asset_versioned')('css/loader.css') }} " rel="stylesheet" type="text/css" />

        {{-- <script src="https://polyfill.io/v3/polyfill.min.js?features=default"></script> --}}

        <link href="{{ app('asset_versioned')('css/uppy.min.css') }}" rel="stylesheet">

        <!--begin::Page Vendor Stylesheets(used by this page)-->
        <link href="{{ app('asset_versioned')('include/dashboard/plugins/custom/datatables/datatables.bundle.css') }} " rel="stylesheet" type="text/css" />
        <!--end::Page Vendor Stylesheets-->


        @if(request()->is('plans_complex/*') || request()->is('complex'))

        <style type="text/css">

            .scroll{
                overflow-x: hidden;
            }
            .empty-cart {
              width: 50vw;
              margin: 0 auto;
              text-align: center;
            }

            /* Masonry Grid Layout */
            .masonry-grid {
                display: block;
                width: 100%;
            }
            
            .masonry-grid-item {
                width: 33.333%; /* 3 columns on desktop */
                padding: 0 10px;
                box-sizing: border-box;
            }
            
            /* Tablet - 2 columns */
            @media (max-width: 991px) {
                .masonry-grid-item {
                    width: 50%;
                }
            }
            
            /* Mobile - 1 column */
            @media (max-width: 767px) {
                .masonry-grid-item {
                    width: 100%;
                }
            }

        </style>

        @endif

        @if(request()->is('complex/*'))

        <link href="{{ app('asset_versioned')('css/complex.css') }}" rel="stylesheet" type="text/css" />

        @endif

        <link href="{{ app('asset_versioned')('css/business_side.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ app('asset_versioned')('css/notifications.css') }}" rel="stylesheet" type="text/css" />

        @if(request()->is('dashboard'))
        <link href="{{ app('asset_versioned')('css/business-dashboard.css') }}" rel="stylesheet">
        @endif

        @if(request()->is('tenant_management', 'tenant_management/onboarding/tenant', 'tenant_management/movement', 'tenant_management/movement/*', 'tenant_management/payment-reconciliation*', 'agents/commissions', 'tenant_management/billing*', 'maintenance*', 'listings', 'listings/*', 'complex/*', 'platform', 'platform/*', 'my-profile', 'my-agent', 'legal', 'business/faq', 'support/tickets*', 'reports', 'reports/*', 'activity'))
        <link href="{{ app('asset_versioned')('css/tenant-workspace.css') }}" rel="stylesheet">
        @endif

        @if(request()->is('complex-expenses*'))
        <link href="{{ app('asset_versioned')('css/property-expenses.css') }}" rel="stylesheet">
        @endif

        @if(request()->is('customer_relationship', 'agents/tasks*', 'agents/lead-tasks/*', 'customer_relationship/lead_management', 'customer_relationship/lead_management/*', 'tenant_management/tenant/*/view', 'tenant_management/tenancy/*/view', 'customer_relationship/lead_management/onboarding/lead', 'agents', 'agents/onboarding'))
        <link href="{{ app('asset_versioned')('css/crm-workspace.css') }}" rel="stylesheet">
        @endif

        @if(request()->is('commercial-units', 'residential-units', 'sales', 'sales/*', 'history', 'history/*'))
        <link href="{{ app('asset_versioned')('css/inventory.css') }}" rel="stylesheet">
        @endif

        @if(request()->is('business_account/team'))
        <link href="{{ app('asset_versioned')('css/team.css') }}" rel="stylesheet">
        @endif

        @if(request()->is('agents/profile/*'))
        <link href="{{ app('asset_versioned')('css/agent-profile.css') }}" rel="stylesheet">
        @endif

                @if(request()->is('complex', 'complex/*'))
        <link href="{{ app('asset_versioned')('css/property-portfolio.css') }}" rel="stylesheet">
        <script defer src="{{ app('asset_versioned')('js/pages/business/property-portfolio.js') }}"></script>
        <script defer src="{{ app('asset_versioned')('js/pages/business/property-facilities.js') }}"></script>
        @endif
        <!-- Leaflet CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <!-- Leaflet JS -->
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <!-- Leaflet Fullscreen CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet.fullscreen/Control.FullScreen.css" />
        <!-- Leaflet Fullscreen JS -->
        <script src="https://unpkg.com/leaflet.fullscreen/Control.FullScreen.js"></script>
        
        
    </head>
    <!--end::Head-->
    <!--begin::Body-->
    <body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed aside-fixed">

        <div class="loader-wrap">
            <div class="preloader"></div>
            <div class="layer layer-one"><span class="overlay"></span></div>
            <div class="layer layer-two"><span class="overlay"></span></div>        
            <div class="layer layer-three"><span class="overlay"></span></div>        
        </div>

        <!--begin::Root-->
        <div class="d-flex flex-column flex-root">
            <!--begin::Page-->
            <div class="page d-flex flex-row flex-column-fluid">
                <!--begin::Aside-->
                @include('pages.business.include.side-menu.aside')
                <!--end::Aside-->
                <!--begin::Wrapper-->
                <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                    @if(session('not_available'))
                    <div class="container-xxl pt-6" data-not-available>
                        <div class="alert alert-warning d-flex align-items-center justify-content-between gap-3 mb-0" role="alert">
                            <span>{{ session('not_available') }}</span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                    @endif
                    @yield('content')

                    <!--begin::Footer-->
                    @include('pages.business.include.footer.footer')
                    <!--end::Footer-->
                </div>
            </div>
        </div>
        
        
        <!--begin::Chat drawer--> <!-- Notification -->
        @include('pages.business.include.notification.index')
        <!--end::Chat drawer-->

        <!--begin::Support-->
        @include('shared.support.guidelines')
        @include('shared.support.documentation')
        <!--end::Support-->

        <!--begin::Scrolltop-->
        @include('pages.business.include.scrolltop.scrolltop')
        <!--end::Scrolltop-->

        <!--begin::scripts-->
        @include('pages.business.layouts.scripts.scripts')
        @stack('scripts')
        @include('pages.business.include.flash')
        <!--end::scripts-->

        {{-- ===== Floating Help Button ===== --}}
        <style>
            @keyframes kt-help-bounce {
                /* === Bounce 1 — full energy === */
                0%    { transform: translateY(0)     scaleX(1.22) scaleY(0.78); } /* squash at ground */
                6%    { transform: translateY(-22px) scaleX(0.9)  scaleY(1.1);  } /* stretch mid-air */
                18%   { transform: translateY(-28px) scaleX(1)    scaleY(1);    } /* apex */
                30%   { transform: translateY(-8px)  scaleX(0.92) scaleY(1.08); } /* falling fast */
                38%   { transform: translateY(0)     scaleX(1.18) scaleY(0.82); } /* squash */

                /* === Bounce 2 — 60% height === */
                44%   { transform: translateY(-17px) scaleX(0.94) scaleY(1.06); }
                54%   { transform: translateY(0)     scaleX(1.12) scaleY(0.88); }

                /* === Bounce 3 — 35% height === */
                59%   { transform: translateY(-10px) scaleX(0.96) scaleY(1.04); }
                66%   { transform: translateY(0)     scaleX(1.07) scaleY(0.93); }

                /* === Bounce 4 — 15% height, nearly dead === */
                70%   { transform: translateY(-4px)  scaleX(1)    scaleY(1);    }
                74%   { transform: translateY(0)     scaleX(1.03) scaleY(0.97); }

                /* === Settle to rest === */
                100%  { transform: translateY(0)     scaleX(1)    scaleY(1);    }
            }
            #kt_help_trigger {
                animation: kt-help-bounce 2.6s ease-out 1.5s 1 both;
                bottom: 75px;
                right: 10px;
                z-index: 1060;
                width: 46px;
                height: 46px;
                transition: opacity .2s, transform .2s;
            }
            #kt_help_trigger:hover {
                animation: none;
            }
        </style>
        <button onclick="toggleHelpPanel()" id="kt_help_trigger"
            class="btn btn-primary btn-icon rounded-circle border-0 position-fixed shadow"
            title="Help & Glossary">
            <span class="svg-icon svg-icon-white svg-icon-2hx">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="currentColor"/>
                    <path d="M11.276 13.654C11.276 13.2713 11.3367 12.9447 11.458 12.674C11.5887 12.394 11.738 12.1653 11.906 11.988C12.0833 11.8107 12.3167 11.61 12.606 11.386C12.942 11.1247 13.1893 10.896 13.348 10.7C13.5067 10.4947 13.586 10.2427 13.586 9.944C13.586 9.636 13.4833 9.356 13.278 9.104C13.082 8.84267 12.69 8.712 12.102 8.712C11.486 8.712 11.066 8.866 10.842 9.174C10.6273 9.482 10.52 9.82267 10.52 10.196L10.534 10.574H8.826C8.78867 10.3967 8.77 10.2333 8.77 10.084C8.77 9.552 8.90067 9.07133 9.162 8.642C9.42333 8.20333 9.81067 7.858 10.324 7.606C10.8467 7.354 11.4813 7.228 12.228 7.228C13.1987 7.228 13.9687 7.44733 14.538 7.886C15.1073 8.31533 15.392 8.92667 15.392 9.72C15.392 10.168 15.322 10.5507 15.182 10.868C15.042 11.1853 14.874 11.442 14.678 11.638C14.482 11.834 14.2253 12.0533 13.908 12.296C13.544 12.576 13.2733 12.8233 13.096 13.038C12.928 13.2527 12.844 13.528 12.844 13.864V14.326H11.276V13.654ZM11.192 15.222H12.928V17H11.192V15.222Z" fill="currentColor"/>
                </svg>
            </span>
        </button>

        {{-- ===== Slide-out Help Panel ===== --}}
        <style>
            #kt_help_panel {
                position: fixed;
                top: 0;
                right: 0;
                height: 100vh;
                width: 340px;
                z-index: 1055;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                background: #fff;
                box-shadow: -6px 0 30px rgba(0,0,0,.12);
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            @media (max-width: 576px) {
                #kt_help_panel {
                    width: 100vw;
                    top: 0;
                    height: 100dvh;
                    border-radius: 0;
                }
            }
        </style>
        <div id="kt_help_panel">

            {{-- Header --}}
            <div class="d-flex align-items-center justify-content-between px-6 py-5 border-bottom">
                <div>
                    <h5 class="fw-bold mb-0">Help &amp; Glossary</h5>
                    <span class="text-muted fs-8">Learn what each section does</span>
                </div>
                <button onclick="closeHelpPanel()" class="btn btn-sm btn-icon btn-light" title="Close">
                    <!--begin::Svg Icon | path: assets/media/icons/duotune/arrows/arr088.svg-->
                    <span class="svg-icon svg-icon-muted svg-icon-2hx"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="7.05025" y="15.5356" width="12" height="2" rx="1" transform="rotate(-45 7.05025 15.5356)" fill="black"/>
                    <rect x="8.46447" y="7.05029" width="12" height="2" rx="1" transform="rotate(45 8.46447 7.05029)" fill="black"/>
                    </svg></span>
                    <!--end::Svg Icon-->
                </button>
            </div>

            {{-- Scrollable Body --}}
            <div class="flex-grow-1 overflow-auto px-6 py-5">

                {{-- Sections Glossary --}}
                <div class="fw-bold text-uppercase text-muted fs-9 mb-4" style="letter-spacing:.06em;">Navigation Sections</div>

                @php
                    $moduleService = app(\App\Services\Access\ModuleService::class);
                    $hasListingsModule = $moduleService->enabled(\App\Services\Access\ModuleService::LISTINGS);
                @endphp

                @php
                    $glossary = array_values(array_filter([
                        ['color'=>'danger',  'name'=>'Home (Dashboard)',   'desc'=>'Your overview: properties, occupancy, rent due and collected, and what needs attention today.'],
                        ['color'=>'primary', 'name'=>'Property portfolio', 'desc'=>'Your buildings and estates (complexes), their unit types, every residential unit and commercial space, and property expenses.'],
                        $hasListingsModule ? ['color'=>'info', 'name'=>'Listings', 'desc'=>'One advert per unit type in a property, for example "2-bedroom" or "4-bedroom". Add rent, deposit and photos, then publish to show renters how many of those units are vacant.'] : null,
                        ['color'=>'warning', 'name'=>'CRM',                'desc'=>'Prospective tenants (leads): qualify them, assign an agent and track follow-ups until they move in.'],
                        ['color'=>'info',    'name'=>'Agents',             'desc'=>'Your agents, the tasks assigned to them and the commission they earn on rent collected.'],
                        ['color'=>'success', 'name'=>'Tenants',            'desc'=>'Tenancies, move-ins and move-outs, rent bills, payments and tenant statements.'],
                        ['color'=>'danger',  'name'=>'Maintenance',        'desc'=>'Repair requests for units and common areas, who is handling them and what they cost.'],
                        ['color'=>'dark',    'name'=>'Legal',              'desc'=>'Lease agreements, contracts and other documents for your properties and tenants.'],
                    ]));
                @endphp

                @foreach($glossary as $item)
                <div class="d-flex align-items-start gap-4 mb-5">
                    <div class="symbol symbol-40px flex-shrink-0">
                        <span class="symbol-label bg-light-{{ $item['color'] }}">
                            <span class="fw-bolder fs-5 text-{{ $item['color'] }}" aria-hidden="true">{{ mb_substr($item['name'], 0, 1) }}</span>
                        </span>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="fw-bold text-dark fs-7">{{ $item['name'] }}</span>
                            @if(!empty($item['badge']))
                                <span class="badge badge-light-primary fs-9 py-1 px-2">{{ $item['badge'] }}</span>
                            @endif
                        </div>
                        <div class="text-muted fs-8">{{ $item['desc'] }}</div>
                    </div>
                </div>
                @endforeach

                <div class="separator separator-dashed my-5"></div>

                {{-- How It Works Summary --}}
                <div class="fw-bold text-uppercase text-muted fs-9 mb-4" style="letter-spacing:.06em;">How It All Fits Together</div>
                @php
                    $steps = array_values(array_filter([
                        ['color'=>'danger',  'label'=>'Set up a property',   'desc'=>'Add the building or estate, its unit types and units, then confirm the setup.'],
                        $hasListingsModule ? ['color'=>'info', 'label'=>'Publish listings', 'desc'=>config('app.name') . ' drafts one listing per unit type. Add rent, deposit and photos, then publish.'] : null,
                        ['color'=>'warning', 'label'=>'Capture leads',       'desc'=>'Record prospects in CRM, qualify them and track follow-ups.'],
                        ['color'=>'success', 'label'=>'Move tenants in',     'desc'=>'Start a tenancy on a vacant unit. Rent is billed in advance.'],
                        ['color'=>'info',    'label'=>'Collect rent',        'desc'=>'Record payments; each one settles the oldest open bills first, and any extra stays as credit.'],
                        ['color'=>'dark',    'label'=>'Look after it',       'desc'=>'Handle maintenance, keep leases in Legal, and pay agents their commission.'],
                    ]));
                    foreach ($steps as $i => $stepItem) { $steps[$i]['n'] = $i + 1; }
                @endphp
                @foreach($steps as $step)
                <div class="d-flex align-items-start gap-3 mb-4">
                    <div class="flex-shrink-0 bg-light-{{ $step['color'] }} text-{{ $step['color'] }} fw-bold"
                         style="width:26px;height:26px;min-width:26px;border-radius:50%;
                                display:flex;align-items:center;justify-content:center;
                                font-size:11px;line-height:1;">{{ $step['n'] }}</div>
                    <div>
                        <div class="fw-semibold text-dark fs-8">{{ $step['label'] }}</div>
                        <div class="text-muted fs-9">{{ $step['desc'] }}</div>
                    </div>
                </div>
                @endforeach

                <div class="separator separator-dashed my-5"></div>

                @yield('help_panel_extras')

                {{-- FAQ card-button --}}
                <a href="{{ route('business.faq') }}" class="btn btn-light d-flex align-items-center gap-3 p-4 rounded mb-3 text-decoration-none">
                    <div class="symbol symbol-40px flex-shrink-0">
                        <div class="symbol-label bg-light-info">
                            <span class="fw-bolder fs-4 text-info" aria-hidden="true">?</span>
                        </div>
                    </div>
                    <div class="flex-grow-1 text-start">
                        <div class="fw-bold text-dark fs-7 lh-1 mb-1">Help &amp; FAQ</div>
                        <div class="text-muted fs-9">Common questions about {{ config('app.name') }}</div>
                    </div>
                    <span class="fs-4 text-muted flex-shrink-0" aria-hidden="true">&rarr;</span>
                </a>

                {{-- Tour card-button --}}
                <button onclick="closeHelpPanel(); startGuidedTour();" class="btn btn-primary d-flex align-items-center gap-3 p-4 rounded w-100 text-start">
                    <div class="symbol symbol-40px flex-shrink-0">
                        <div class="symbol-label bg-white bg-opacity-25">
                            <span class="fw-bolder fs-4 text-white" aria-hidden="true">&#9654;</span>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-white fs-7 lh-1 mb-1">Take a Guided Tour</div>
                        <div class="fs-9 text-white opacity-75">Step-by-step walkthrough of all features</div>
                    </div>
                    <span class="fs-4 text-white flex-shrink-0" aria-hidden="true">&rarr;</span>
                </button>

                <div class="separator separator-dashed my-5"></div>

                {{-- Auto-open opt-out --}}
                <div id="kt_help_auto_note" class="text-center fs-9">
                    <span class="text-muted">This panel opens automatically on the dashboard.</span><br>
                    {{-- <button onclick="disableHelpAutoOpen()" class="btn btn-link btn-sm p-0 fs-9 text-muted">Don't open automatically again</button> --}}
                </div>

            </div>
        </div>

        {{-- Panel backdrop --}}
        <div id="kt_help_backdrop" onclick="closeHelpPanel()"
             style="display:none;position:fixed;inset:0;z-index:1050;background:rgba(0,0,0,.3);"></div>

    </body>
    <!--end::Body-->
</html>
