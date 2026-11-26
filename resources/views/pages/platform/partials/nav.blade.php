{{-- Platform section bar: what this page is. $title (HTML, escape data), $intro, optional $actions (HTML). --}}
<style>
    .platform-bar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px 24px; margin-bottom: 24px; }
    .platform-bar h2 { font-size: 1.5rem; font-weight: 700; color: #102a35; margin: 0 0 4px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .platform-bar p { color: #637887; margin: 0; max-width: 760px; }
    .platform-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .platform-list { list-style: none; margin: 0; padding: 0; }
    .platform-list li { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 0; border-bottom: 1px dashed #e0e8ec; }
    .platform-list li:last-child { border-bottom: 0; padding-bottom: 0; }
    .platform-list strong { display: block; color: #102a35; font-size: .95rem; }
    .platform-list small { color: #637887; }
    .platform-switch { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 14px 0; border-bottom: 1px dashed #e0e8ec; }
    .platform-switch:last-child { border-bottom: 0; padding-bottom: 0; }
    .platform-switch .form-check-input { width: 2.75rem; height: 1.5rem; cursor: pointer; margin: 2px 0 0; }
    .platform-switch label { cursor: pointer; }
    .platform-row-link { color: inherit; }
    .platform-row-link:hover strong { color: #009ef7; }
    .platform-filters { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1.3fr) minmax(0, 1fr) auto auto; gap: 10px; align-items: center; }
    [data-platform-people] .tenant-table { min-width: 0; } /* columns drop away on small screens instead */
    .platform-switch .form-check-input:not(:checked) { background-color: #e4e9ee; border-color: #cfd8df; }
    @media (max-width: 767px) { .platform-filters { grid-template-columns: 1fr 1fr; } .platform-filters input[type=search] { grid-column: 1 / -1; } }
    .platform-empty { display: flex; align-items: center; gap: 10px; color: #3f7f5a; background: #f0faf4; border-radius: 10px; padding: 14px 16px; font-weight: 600; font-size: .9rem; }
</style>
@php $openTickets = \App\Models\SupportTicket::open()->count(); @endphp
@if(auth()->user()->isPlatformAdmin()) {{-- support agents only have the inbox --}}
<nav class="support-tabs" aria-label="Platform sections">
    <a href="{{ route('platform.index') }}" class="{{ request()->routeIs('platform.index') ? 'is-active' : '' }}"><i class="fas fa-users" aria-hidden="true"></i>Team &amp; business</a>
    <a href="{{ route('platform.support.index') }}" class="{{ request()->routeIs('platform.support.*') ? 'is-active' : '' }}"><i class="fas fa-life-ring" aria-hidden="true"></i>Support @if($openTickets)<em>{{ $openTickets }} active</em>@endif</a>
</nav>
@endif
<div class="platform-bar">
    <div>
        <h2>{!! $title !!}</h2>
        @if(!empty($intro))<p>{{ $intro }}</p>@endif
    </div>
    @if(!empty($actions))<div class="platform-actions">{!! $actions !!}</div>@endif
</div>
@if($errors->any())
    <div class="alert alert-danger" role="alert">@foreach($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>
@endif
