@extends('pages.business.layouts.app')
@section('content')
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-icon btn-light d-lg-none" id="kt_aside_toggle" type="button" aria-label="Open navigation">☰</button>
            <span class="fw-bold fs-4">Access restricted</span>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl" id="kt_content_container" style="max-width: 760px">
        <div class="card border-0 shadow-sm mt-10" role="alert" data-access-denied>
            <div class="card-body p-10 text-center">
                <div class="fs-1 mb-4" aria-hidden="true">🔒</div>
                <h1 class="fs-2 mb-3">You don't have access to this</h1>
                <p class="text-muted fs-5 mb-2">{{ $message }}</p>
                <p class="text-muted mb-8">If you need it for your work, ask your account administrator to update your role.</p>
                <div class="d-flex justify-content-center gap-3">
                    @if($landing)<a href="{{ $landing }}" class="btn btn-primary">Go to your workspace</a>@endif
                    <a href="javascript:history.back()" class="btn btn-light">Go back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
