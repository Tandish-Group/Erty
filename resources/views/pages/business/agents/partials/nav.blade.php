{{-- Agents section navigation. --}}
@php
    $agentsNav = [
        ['/agents', 'Directory', request()->is('agents') || request()->is('agents/profile/*') || request()->is('agents/onboarding'), 'agent_management'],
        ['/agents/tasks', 'Tasks', request()->is('agents/tasks*') || request()->is('agents/lead-tasks/*'), 'task_management'],
        ['/agents/commissions', 'Commissions', request()->is('agents/commissions*'), 'agent_management'],
        ['/agents/settings', 'Preconfiguration', request()->is('agents/settings*'), 'agent_management'],
    ];
@endphp
<nav class="d-flex flex-wrap gap-2 mb-6" aria-label="Agents section" data-agents-nav>
    @foreach($agentsNav as [$href, $label, $active, $group])
        @if(auth()->user()->hasBusinessPermission($group, 'view'))
        <a href="{{ $href }}" class="btn btn-sm {{ $active ? 'btn-primary' : 'btn-light' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
        @endif
    @endforeach
    @if(\App\Services\Agents\AgentProfileService::current())
        <a href="{{ route('business.my_agent') }}" class="btn btn-sm btn-light-primary ms-auto">My agent dashboard</a>
    @endif
</nav>
