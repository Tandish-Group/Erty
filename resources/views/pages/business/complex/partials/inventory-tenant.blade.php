<div class="mb-2">
    @if($tenancy->tenantProfile?->tenant_ref)
        <a href="{{ url('/tenant_management/tenant/'.$tenancy->tenantProfile->tenant_ref.'/view') }}">{{ $tenancy->tenantProfile->display_name ?: 'Open tenant record' }}</a>
    @else
        <span>Active tenancy · tenant profile unavailable</span>
    @endif
    <small class="text-muted d-block">{{ $tenancy->tenancy_ref }} · KES {{ number_format($tenancy->monthly_payment ?? 0, 2) }} / month</small>
</div>
