{{-- How this lead becomes a tenant: each step with who did it and when. --}}
@php
    $who = fn ($user) => $user ? trim($user->first_name.' '.$user->last_name) : null;
    $when = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('j M Y, H:i') : null;
    $taskTotal = $lead->tasks->count();
    $taskDone = $lead->tasks->filter(fn ($t) => in_array($t->status->value ?? $t->status, ['completed', 'skipped'], true))->count();
    $phaseLabel = $lead->workflow_phase ? (\App\Enums\LeadWorkflowPhaseEnum::tryFrom($lead->workflow_phase)?->label() ?? ucfirst($lead->workflow_phase)) : null;
    $steps = [
        ['Captured', true, 'Added by '.($who($lead->creator) ?? 'someone (not recorded)').' · '.$when($lead->created_at)],
        ['Details confirmed', (bool) $lead->details_confirmed_at, $lead->details_confirmed_at ? 'By '.($who($lead->detailsConfirmedBy) ?? 'a team member').' · '.$when($lead->details_confirmed_at) : 'Check the contact details and requirements, then use Confirm details above.'],
        ['Qualified', (bool) $lead->qualification_confirmed_at, $lead->qualification_confirmed_at ? 'By '.($who($lead->qualificationConfirmedBy) ?? 'a team member').' · '.$when($lead->qualification_confirmed_at) : 'Confirm the budget, timing and fit, then use Confirm qualification above.'],
        ['Agent works the tasks', (bool) $lead->converted_tenant_id || ($lead->assigned_agent_id && $taskTotal > 0 && $taskDone === $taskTotal), $lead->assignedAgent
            ? $lead->assignedAgent->full_name.' · '.($phaseLabel ? $phaseLabel.' · ' : '').$taskDone.' of '.$taskTotal.' tasks done'
            : 'Assign an agent so the follow-up tasks start.'],
        ['Converted to tenant', (bool) $lead->converted_tenant_id, $lead->converted_tenant_id
            ? 'Tenancy '.($lead->convertedTenant->tenancy_ref ?? '').' · '.$when($lead->converted_at)
            : ($lead->readyToConvert() ? 'Ready: use Convert to tenant.' : 'Available once the details and qualification are confirmed.')],
    ];
    $current = collect($steps)->search(fn ($s) => !$s[1]);
@endphp
<div class="lead-path" data-path-to-tenant>
    <div class="lead-path-head">
        <h3>Path to tenant</h3>
        @if($lead->assignedAgent)@businessCan('task_management', 'view')<a href="/agents/tasks">Open agent tasks <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endbusinessCan @endif
    </div>
    <ol class="lead-path-steps">
        @foreach($steps as $index => [$title, $done, $detail])
            @php $state = $done ? 'done' : ($index === $current ? 'current' : 'todo'); @endphp
            <li class="lead-path-step is-{{ $state }}" data-path-step="{{ $index + 1 }}" data-state="{{ $state }}" @if($state === 'current') aria-current="step" @endif>
                <span class="lead-path-dot" aria-hidden="true">@if($done)<i class="bi bi-check-lg"></i>@else{{ $index + 1 }}@endif</span>
                <div class="lead-path-text">
                    <strong>{{ $title }}<span class="visually-hidden"> ({{ $state === 'done' ? 'done' : ($state === 'current' ? 'next' : 'to do') }})</span></strong>
                    <small>{{ $detail }}</small>
                </div>
            </li>
        @endforeach
    </ol>
    <p class="lead-path-note">The agent's tasks guide the follow-up. A lead can be converted once it is qualified; open tasks are then closed and the agent gets the finalization tasks.</p>
</div>
