{{-- Team: who this agent reports to, who reports to them, and their commission on units they delegate as a primary manager
     (saved as their "primary manager on delegated units" commission agreement). --}}
<div class="card border-0 shadow-sm mb-6" data-agent-hierarchy data-url="{{ route('agent_management.hierarchy', $agent->profile_route_token) }}">
    <div class="card-header border-0 pt-6"><h2 class="card-title fs-4 fw-bold">Team &amp; delegation</h2></div>
    <div class="card-body pt-2">
        @if($canManageLogin)
        <form data-hierarchy-form>
            <label class="form-label fs-7" for="hierarchy-lead">Reports to</label>
            <select class="form-select mb-3" id="hierarchy-lead" name="reports_to_agent_id">
                <option value="">Nobody</option>
                @foreach($leadOptions as $option)<option value="{{ $option->id }}" @selected((int) $agent->reports_to_agent_id === (int) $option->id)>{{ $option->full_name }}</option>@endforeach
            </select>
            <label class="form-label fs-7" for="hierarchy-override">Commission on delegated units (%)</label>
            <input class="form-control mb-1" type="number" min="0" max="100" step="0.01" id="hierarchy-override" name="override_rate" value="{{ rtrim(rtrim(number_format((float) $agent->override_rate, 2, '.', ''), '0'), '.') ?: 0 }}">
            <div class="form-text mb-3">On properties where {{ $agent->first_name }} is primary manager, {{ $agent->first_name }} earns this % of rent collected on units delegated to sub-agents. Changes apply to rent collected from today; earlier commission keeps its rate. Finer terms are under Agents → Commissions → Agreements.</div>
            <div class="alert alert-danger d-none" role="alert" data-form-error></div>
            <button type="submit" class="btn btn-sm btn-primary">Save</button>
        </form>
        @else
            <dl class="fs-7 mb-0"><dt class="text-muted fw-semibold">Reports to</dt><dd>{{ $agent->reportsTo?->full_name ?? 'Nobody' }}</dd><dt class="text-muted fw-semibold">Commission on delegated units</dt><dd class="mb-0">{{ (float) $agent->override_rate }}%</dd></dl>
        @endif
        <h3 class="fs-7 fw-bold mt-5 mb-2">Agents who report to {{ $agent->first_name }}</h3>
        @forelse($agent->subAgents as $sub)
            <div class="fs-7 mb-1"><a href="{{ route('agent_management.profile', $sub->profile_route_token) }}">{{ $sub->full_name }}</a>@if(!$sub->user_id)<span class="text-muted"> (no sign-in)</span>@endif</div>
        @empty
            <p class="text-muted fs-7 mb-0">No one reports to {{ $agent->first_name }} yet.</p>
        @endforelse
    </div>
</div>
@if($canManageLogin)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const card = document.querySelector('[data-agent-hierarchy]');
        const form = card?.querySelector('[data-hierarchy-form]');
        if (!form) return;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const errorBox = form.querySelector('[data-form-error]');
            errorBox.classList.add('d-none');
            const response = await fetch(card.dataset.url, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ reports_to_agent_id: form.reports_to_agent_id.value ? Number(form.reports_to_agent_id.value) : null, override_rate: form.override_rate.value || 0 }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) { errorBox.textContent = Object.values(result.errors || {}).flat().join(' ') || result.message || 'Could not save.'; errorBox.classList.remove('d-none'); return; }
            try { sessionStorage.setItem('erty_flash', result.message); } catch (e) {}
            location.reload();
        });
    });
</script>
@endif
