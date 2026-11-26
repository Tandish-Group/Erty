{{-- Edit tenancy terms (unit changes go through Move/transfer). $tenant: the TenantManagement row of the tenancy page. --}}
@php
    $isCommercial = (bool) $tenant->commercial_unit_id;
    $currentAgent = $tenant->assigned_agent_id ? \App\Models\Agent::find($tenant->assigned_agent_id) : null;
    $endDate = $tenant->lease_end_date ? \Carbon\Carbon::parse($tenant->lease_end_date)->format('Y-m-d') : '';
@endphp
<div class="modal fade" id="kt_modal_edit_tenancy" tabindex="-1" aria-labelledby="edit-tenancy-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form data-edit-tenancy-form data-url="{{ route('tenant_management.update_tenancy', $tenant->id) }}" novalidate>
            <div class="modal-header"><div><span class="text-muted fs-7">{{ $tenant->tenancy_ref }} · {{ $tenant->complex_name }}{{ $tenant->unit_number ? ' · '.$tenant->unit_number : '' }}</span><h2 class="fs-4 mb-0" id="edit-tenancy-title">Edit tenancy</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="text-muted fs-7">To move this tenant to another unit or property, use <strong>Move / transfer</strong>. Rent changes apply to bills generated from now on.</p>
                <div class="row g-4">
                    <div class="col-md-4"><label class="form-label required" for="et-rent">Monthly rent ({{ $tenant->currency ?: default_currency() }})</label><input class="form-control" id="et-rent" name="monthly_payment" type="number" min="0" step="0.01" value="{{ $tenant->monthly_payment }}" required></div>
                    <div class="col-md-4"><label class="form-label" for="et-deposit">Security deposit</label><input class="form-control" id="et-deposit" name="security_deposit" type="number" min="0" step="0.01" value="{{ $tenant->security_deposit }}"></div>
                    <div class="col-md-4"><label class="form-label required" for="et-interval">Payment interval</label>
                        <select class="form-select" id="et-interval" name="payment_frequency">@foreach(['Monthly', 'Quarterly', 'Annually'] as $f)<option value="{{ $f }}" @selected(($tenant->payment_frequency ?: 'Monthly') === $f)>{{ $f }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label required" for="et-agreement">Agreement</label>
                        <select class="form-select" id="et-agreement" name="agreement_type">@foreach(['rental' => 'Rental', 'lease' => 'Lease'] as $v => $l)<option value="{{ $v }}" @selected(strtolower((string) $tenant->agreement_type) === $v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" for="et-end">End date</label><input class="form-control" id="et-end" name="lease_end_date" type="date" value="{{ $endDate }}"></div>
                    <div class="col-md-4"><label class="form-label" for="et-grace">Grace period (days)</label><input class="form-control" id="et-grace" name="grace_period_days" type="number" min="0" max="365" value="{{ $tenant->grace_period_days ?? 0 }}"></div>
                    <div class="col-md-4"><label class="form-label" for="et-late">Late fee</label><input class="form-control" id="et-late" name="late_fee_amount" type="number" min="0" step="0.01" value="{{ $tenant->late_fee_amount }}"></div>
                    @if($isCommercial)
                        <div class="col-md-4"><label class="form-label required" for="et-area">Occupied area (sq ft)</label><input class="form-control" id="et-area" name="tenancy_area" type="number" min="1" step="0.01" value="{{ $tenant->occupied_sq_area }}" required><div class="form-text">Checked against the space's free area and subdivision limit.</div></div>
                    @else
                        <div class="col-md-4"><label class="form-label" for="et-occupants">Occupants</label><input class="form-control" id="et-occupants" name="number_of_occupants" type="number" min="1" max="100" value="{{ $tenant->number_of_occupants ?? 1 }}"></div>
                        <div class="col-md-4 d-flex align-items-end"><label class="form-check form-switch form-check-custom mb-3"><input class="form-check-input" type="checkbox" name="has_pets" value="1" @checked($tenant->has_pets) data-et-pets><span class="form-check-label">Has pets</span></label></div>
                        <div class="col-12" data-et-pet-details @if(!$tenant->has_pets) hidden @endif><label class="form-label" for="et-pet-details">Pet details</label><input class="form-control" id="et-pet-details" name="pet_details" maxlength="2000" value="{{ $tenant->pet_details }}"></div>
                    @endif
                    <div class="col-md-8"><label class="form-label" for="et-agent">Agent credited</label>
                        @include('pages.business.agents.partials.picker', ['name' => 'assigned_agent_id', 'id' => 'et-agent', 'selected' => $currentAgent, 'placeholder' => 'No agent'])
                        <div class="form-text">The agent earns commission on this tenancy's collections and gets an email when it is assigned to them.</div></div>
                    <div class="col-12"><label class="form-label" for="et-notes">Notes</label><textarea class="form-control" id="et-notes" name="additional_notes" rows="2" maxlength="5000">{{ $tenant->additional_notes }}</textarea></div>
                </div>
                <div class="alert alert-danger d-none mt-4 mb-0" role="alert" data-form-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-submit>Save changes</button></div>
        </form>
    </div></div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-edit-tenancy-form]');
        if (!form) return;
        form.querySelector('[data-et-pets]')?.addEventListener('change', e => { form.querySelector('[data-et-pet-details]').hidden = !e.target.checked; });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const submit = form.querySelector('[data-submit]');
            const errorBox = form.querySelector('[data-form-error]');
            errorBox.classList.add('d-none');
            submit.disabled = true;
            const body = new FormData(form);
            // Named differently in the form so the onboarding script's area checks don't touch it.
            if (body.has('tenancy_area')) { body.set('occupied_sq_area', body.get('tenancy_area')); body.delete('tenancy_area'); }
            if (!form.querySelector('[data-et-pets]')?.checked) body.set('has_pets', '0');
            try {
                const response = await fetch(form.dataset.url, { method: 'POST', credentials: 'same-origin', body,
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                const result = await response.json().catch(() => ({}));
                if (!response.ok || result.status >= 400) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The tenancy could not be saved.');
                try { sessionStorage.setItem('erty_flash', result.message); } catch (e) {}
                location.reload();
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('d-none');
                submit.disabled = false;
            }
        });
    });
</script>
