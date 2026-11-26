{{-- Optional Erty modules the business owner manages for their own account. --}}
@php
    $moduleService = app(\App\Services\Access\ModuleService::class);
    $selfManagedModules = collect(\App\Services\Access\ModuleService::MODULES)
        ->filter(fn ($definition, $key) => $definition['business_managed'] && $moduleService->installationEnabled($key));
@endphp
@if(!auth()->user()->is_sub_account && $selfManagedModules->isNotEmpty())
<div class="card mb-5 mb-xl-10">
    <div class="card-header border-0">
        <div class="card-title m-0">
            <h3 class="fw-bolder m-0">Modules</h3>
        </div>
    </div>
    <div class="card-body border-top p-9">
        <p class="text-muted fs-7 mb-6">Switch optional parts of {{ config('app.name') }} on or off for your business. Your records are kept when a module is switched off.</p>
        @foreach($selfManagedModules as $key => $definition)
            <div class="d-flex flex-stack mb-4">
                <div class="me-5">
                    <label class="fs-6 fw-bold form-label mb-1" for="module_toggle_{{ $key }}">{{ $definition['label'] }}</label>
                    <div class="fs-7 fw-semibold text-muted">{{ $definition['description'] }}</div>
                    @if(!empty($definition['off_hint']))
                        <div class="fs-8 text-muted mt-1">{{ $definition['off_hint'] }}</div>
                    @endif
                </div>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" role="switch" id="module_toggle_{{ $key }}"
                           data-business-module="{{ $key }}" @checked($moduleService->enabled($key))>
                </div>
            </div>
        @endforeach
    </div>
</div>
<script>
    document.querySelectorAll('[data-business-module]').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            var input = this;
            var enabled = input.checked;
            input.disabled = true;
            fetch(@json(route('business.modules.update')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
                },
                body: JSON.stringify({ module: input.dataset.businessModule, enabled: enabled })
            }).then(function (response) {
                return response.json().then(function (body) { return { ok: response.ok, body: body }; });
            }).then(function (result) {
                if (!result.ok) throw new Error(result.body.message || 'The module could not be updated.');
                // Navigation and dashboards depend on modules: reload so they reflect the change.
                window.location.reload();
            }).catch(function (error) {
                input.checked = !enabled;
                input.disabled = false;
                if (window.Swal) { Swal.fire({ text: error.message, icon: 'error', confirmButtonText: 'OK' }); }
                else { alert(error.message); }
            });
        });
    });
</script>
@endif
