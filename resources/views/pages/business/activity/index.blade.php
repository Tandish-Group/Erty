@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Activity log'])
@php
    use App\Models\ActivityLog;
    $nameOf = fn ($u) => $u ? (trim($u->first_name . ' ' . $u->last_name) ?: $u->email) : '—';
    $types = ['' => 'Everything', 'changes' => 'Changes and downloads', 'views' => 'Pages opened', 'sign_ins' => 'Sign-ins', 'refused' => 'Refused and failed'];
@endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">Business &amp; team</span>
                <h1>Activity log</h1>
                <p>Who opened, changed or downloaded what, and when, including sign-ins and anything refused. Form contents are never recorded. Entries are kept for {{ $keepDays }} days.</p>
            </div>
            @if($canExport)
                <div class="tenant-heading-actions">
                    <a class="btn btn-light" href="{{ route('activity.export', request()->query()) }}"><i class="fas fa-file-csv me-1" aria-hidden="true"></i>Download (CSV)</a>
                </div>
            @endif
        </header>

        <form method="get" class="tenant-panel tenant-panel--padded report-filters mb-6">
            <div><label for="al_user" class="form-label">Person</label>
                <select id="al_user" name="user" class="form-select form-select-sm">
                    <option value="">Everyone</option>
                    @foreach($people as $p)<option value="{{ $p->id }}" @selected($filters['user'] === $p->id)>{{ $nameOf($p) }}</option>@endforeach
                </select></div>
            <div><label for="al_type" class="form-label">Show</label>
                <select id="al_type" name="type" class="form-select form-select-sm">
                    @foreach($types as $key => $label)<option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>@endforeach
                </select></div>
            <div><label for="al_area" class="form-label">Area</label>
                <select id="al_area" name="area" class="form-select form-select-sm">
                    <option value="">All areas</option>
                    @foreach($areas as $key => $label)<option value="{{ $key }}" @selected($filters['area'] === $key)>{{ $label }}</option>@endforeach
                </select></div>
            <div><label for="al_from" class="form-label">From</label><input id="al_from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm"></div>
            <div><label for="al_to" class="form-label">To</label><input id="al_to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm"></div>
            <div><label for="al_q" class="form-label">Page, record or IP</label><input id="al_q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="e.g. TMN7PQ91VY"></div>
            <div class="report-filter-actions"><button class="btn btn-sm btn-primary">Show</button>@if(request()->query())<a href="{{ route('activity.index') }}" class="btn btn-sm btn-light">Reset</a>@endif</div>
        </form>

        <section class="tenant-panel tenant-panel--padded">
            @if($entries->isEmpty())
                <div class="tenant-empty"><i class="fas fa-history" aria-hidden="true"></i><h3>No activity</h3><p>Nothing matches these filters in this period.</p></div>
            @else
                <div class="tenant-table-scroll" role="region" aria-label="Activity" tabindex="0">
                    <table class="tenant-table activity-table">
                        <thead><tr><th>When</th><th>Person</th><th>What</th><th>Area</th><th class="d-none d-md-table-cell">Page</th><th class="d-none d-lg-table-cell">Record</th><th class="d-none d-xl-table-cell">From</th></tr></thead>
                        <tbody>
                        @foreach($entries as $e)
                            <tr>
                                <td class="text-nowrap"><span title="{{ $e->created_at?->format('j M Y H:i:s') }}">{{ $e->created_at?->format('j M, H:i') }}</span><small class="d-block text-muted">{{ $e->created_at?->diffForHumans() }}</small></td>
                                <td>{{ $nameOf($e->user) }}@if($e->user)<small class="d-block text-muted">{{ $e->user->is_sub_account ? ($e->user->businessRole->name ?? 'No role') : 'Owner' }}</small>@endif</td>
                                <td><span class="badge badge-light-{{ ActivityLog::TONES[$e->event] ?? 'secondary' }}">{{ $e->eventLabel() }}</span>@if($e->status && $e->status >= 400)<small class="d-block text-muted">{{ $e->status }}</small>@endif</td>
                                <td>{{ $areas[$e->area] ?? ($e->area ?: '—') }}@if($e->action)<small class="d-block text-muted">{{ str_replace('_', ' ', $e->action) }}</small>@endif</td>
                                <td class="d-none d-md-table-cell activity-path"><code>{{ $e->method }}</code> {{ \Illuminate\Support\Str::limit($e->path, 70) }}</td>
                                <td class="d-none d-lg-table-cell">{{ $e->subject ?: '—' }}</td>
                                <td class="d-none d-xl-table-cell"><span title="{{ $e->user_agent }}">{{ $e->ip }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $entries->links() }}</div>
            @endif
        </section>
    </div>
</div>
@endsection
