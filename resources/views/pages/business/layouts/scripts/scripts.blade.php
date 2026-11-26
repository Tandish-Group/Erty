<!--begin::Global Javascript Bundle(used by all pages)-->
<script src="{{ app('asset_versioned')('include/dashboard/plugins/global/plugins.bundle.js') }} "></script>
<script src="{{ app('asset_versioned')('include/dashboard/js/scripts.bundle.js') }}"></script>
<script src="{{ app('asset_versioned')('include/dashboard/plugins/custom/ckeditor/ckeditor-classic.bundle.js') }}"></script>
<script src="{{ app('asset_versioned')('include/dashboard/plugins/custom/datatables/datatables.bundle.js') }}"></script>
<script src="{{ app('asset_versioned')('js/uppy.min.js') }}"></script>

<!--end::Global Javascript Bundle-->

<script src="{{ app('asset_versioned')('js/loader.js') }}"></script>

<script type="module">
    import { identiconSvg } from "{{ app('asset_versioned')('js/minidenticons.min.js')}}"
</script>

<script src="{{ app('asset_versioned')('js/pages/business/general.js') }}"></script>
<script src="{{ app('asset_versioned')('js/pages/business/notifications.js') }}"></script>
<script src="{{ app('asset_versioned')('js/pages/business/help_panel.js') }}"></script>

<script>
    // Force reload when page is restored from bfcache — ensures unauthenticated users
    // can't see authenticated pages via mobile back button
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

@if(request()->is('my-profile'))
<script src="{{ app('asset_versioned')('include/dashboard/js/custom/account/settings/signin-methods.js') }}"></script>
@endif

@if(request()->is('business_account*') && !request()->is('business_account/team*'))

<script src="{{ app('asset_versioned')('include/dashboard/js/custom/account/settings/deactivate-account.js') }}"></script>
<script src="{{ app('asset_versioned')('js/pages/business/business_account.js') }}"></script>
<script src="{{ app('asset_versioned')('js/pages/business/storage_management.js') }}"></script>

@endif

@if(request()->is('plans_complex/*'))

<script src="{{ app('asset_versioned')('include/dashboard/plugins/custom/fslightbox/fslightbox.bundle.js') }}"></script>

@endif

    

@if(request()->is('complex'))

<script>
    window.COMPLEX_STATS_URL = '{{ route('complexes.stats') }}';
</script>
<script src="{{ app('asset_versioned')('js/pages/business/complex.js') }}"></script>

@endif

@if(request()->is('commercial-units') || request()->is('commercial-units/create'))

<script src="{{ app('asset_versioned')('include/dashboard/plugins/custom/formrepeater/formrepeater.bundle.js') }}"></script>
<script src="{{ app('asset_versioned')('js/pages/business/commercial_units.js') }}"></script>

@endif

@if(request()->is('commercial-units/*/edit'))

<script src="{{ app('asset_versioned')('js/pages/business/commercial_units_edit.js') }}"></script>

@endif

@if(request()->is('complex/*'))

<script src="{{ app('asset_versioned')('js/pages/business/complex_pages.js') }}"></script>

@endif

@if(request()->is('plans_complex/*'))

<script src="{{ app('asset_versioned')('js/uppy.min.js') }}"></script>

<script src="{{ app('asset_versioned')('js/pages/business/plans_complex.js') }}"></script>

@endif


@if(request()->is('reports', 'reports/*', 'customer_relationship/reports'))

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ app('asset_versioned')('js/pages/business/reports.js') }}"></script>

@endif

@if(request()->is('legal'))

<script src="{{ app('asset_versioned')('js/pages/business/legal.js') }}"></script>

@endif

@if(request()->is('tenant_management/tenant/*'))

    <script src="{{ app('asset_versioned')('js/pages/business/tenancies.js') }}"></script>

@endif

@if(request()->is('tenant_management') || request()->is('tenant_management/tenancy/*') || request()->is('tenant_management/onboarding/tenant') || request()->is('tenant_management/tenants'))

<script>
    window.appConfig = {
        currency: "{{ default_currency() }}",
        currencySymbol: "{{ config('services.currency_symbol') }}"
    };
</script>

<script src="{{ app('asset_versioned')('js/uppy.min.js') }}"></script>
<script src="{{ app('asset_versioned')('include/dashboard/plugins/custom/formrepeater/formrepeater.bundle.js') }}"></script>
<script src="{{ app('asset_versioned')('js/pages/business/tenant.js') }}"></script>
@if(request()->is('tenant_management', 'tenant_management/onboarding/tenant'))
<script src="{{ app('asset_versioned')('js/pages/business/tenant-workspace.js') }}"></script>
@endif

@endif

@if(request()->is('tenant_management/bulk-upload/*'))

<script src="{{ app('asset_versioned')('js/pages/business/tenant_bulk_upload.js') }}"></script>

@endif


@if(request()->is('tenant_management/movement') || request()->is('tenant_management/movement/*'))

<script src="{{ app('asset_versioned')('js/pages/business/tenant_transfer.js') }}"></script>

@endif

@if(request()->is('service-charges'))

<script src="{{ app('asset_versioned')('js/pages/business/service_charges.js') }}"></script>

@endif

{{-- Agents → Commissions is server-rendered (ledger, agreements, periods); no page script. --}}

@if(request()->is('complex-expenses*'))

    <script>
        window.EXPENSE_URLS = {
            data:    '{{ url("complex-expenses/data") }}',
            store:   '{{ url("complex-expenses") }}',
            index:   '{{ route("business.expenses.index") }}',
            units:   '{{ url("complex-expenses/units") }}',
            update:  '{{ url("complex-expenses") }}',   // + /{id}  via PUT
            destroy: '{{ url("complex-expenses") }}',   // + /{id}  via DELETE
        };
        window.CSRF_TOKEN = '{{ csrf_token() }}';
        window.PRESELECT_COMPLEX       = {{ isset($preselectComplexId) && $preselectComplexId ? (int) $preselectComplexId : 'null' }};
    </script>

    <script src="{{ asset('js/pages/business/expenses.js') }}"></script>

    @if(request()->is('complex-expenses/create'))
    <script src="{{ app('asset_versioned')('js/pages/business/expense-create.js') }}"></script>
    @endif

@endif

@if(request()->is('tenant_management/payment-reconciliation*'))

    <script>
        window.RECONCILIATION_URLS = {
            unmatched_payments: '{{ url("tenant_management/payment-reconciliation/unmatched-payments") }}',
            staged_receipts:    '{{ url("tenant_management/payment-reconciliation/staged-receipts") }}',
            upload:             '{{ url("tenant_management/payment-reconciliation/upload") }}',
            match:              '{{ url("tenant_management/payment-reconciliation/match") }}',
            unmatch:            '{{ url("tenant_management/payment-reconciliation/unmatch/__ID__") }}',
            delete_receipt:     '{{ url("tenant_management/payment-reconciliation/receipts/__ID__") }}',
            recent_matches:     '{{ url("tenant_management/payment-reconciliation/recent-matches") }}',
        };
        window.CSRF_TOKEN = '{{ csrf_token() }}';
    </script>

    <script src="{{ app('asset_versioned')('js/pages/business/payment-reconciliation.js') }}"></script>

@endif


@if(request()->is('customer_relationship/lead_management') || request()->is('customer_relationship/lead_management/onboarding/lead'))

    <script src="{{ app('asset_versioned')('js/pages/business/lead_management.js') }}"></script>

@endif

@if(request()->is('agents') || request()->is('agents/onboarding'))

    <script src="{{ app('asset_versioned')('js/pages/business/agent_management.js') }}"></script>

@endif

@if(request()->is('agents/profile/*'))

    <script src="{{ app('asset_versioned')('js/pages/business/agent_management_profile.js') }}"></script>

@endif

@if(request()->is('agents/settings'))

    <script src="{{ app('asset_versioned')('js/pages/business/agent_management_settings.js') }}"></script>

@endif

@if(request()->is('agents/tasks'))

    <script src="{{ app('asset_versioned')('js/pages/business/tasks_management.js') }}"></script>

@endif

@if(request()->is('agents/lead-tasks/*'))

    <script src="{{ app('asset_versioned')('js/pages/business/lead_tasks.js') }}"></script>

@endif

@if(request()->is('customer_relationship/lead_management/lead/*'))

    <script src="{{ app('asset_versioned')('js/pages/business/lead_profile.js') }}"></script>

@endif


@if(request()->is('business/faq'))

    <script src="{{ app('asset_versioned')('js/pages/business/faq.js') }}"></script>

@endif
