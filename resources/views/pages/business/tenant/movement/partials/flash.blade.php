{{-- Messages after an action on the transfer pages. --}}
@if(session('success'))<div class="alert alert-success d-flex align-items-start gap-2" role="status"><i class="fas fa-check-circle mt-1" aria-hidden="true"></i><div>{{ session('success') }}</div></div>@endif
@if(session('error'))<div class="alert alert-danger d-flex align-items-start gap-2" role="alert"><i class="fas fa-exclamation-circle mt-1" aria-hidden="true"></i><div>{{ session('error') }}</div></div>@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Please check the transfer:</strong>
        <ul class="mb-0 mt-1">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif
