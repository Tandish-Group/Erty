<?php

namespace App\Http\Controllers\Business\Agents\Concerns;

use App\Models\Agent;
use App\Services\Agents\AgentProfileService;
use Illuminate\Http\Request;

/** Who sees and works which lead tasks: an agent's own, or every agent's for people who manage all tasks. */
trait ScopesLeadTaskBoard
{
    /** The signed-in person's own CRM agent profile, if they work as an agent. */
    protected function ownAgent(): ?Agent
    {
        return AgentProfileService::current();
    }
    /**
     * Who may read every agent's task board: anyone who manages all tasks, and people who see every
     * property without being an agent themselves (e.g. a read-only reviewer). Acting on tasks still needs
     * managesAllTasks() or being the task's agent.
     */
    protected function seesAllTasks(): bool
    {
        $user = auth()->user();
        // Without an agent profile, a member who may view tasks sees the board; people limited to assigned
        // properties only get tasks on leads for those properties (LeadTask's assigned-properties scope).
        return $this->managesAllTasks()
            || (!$this->ownAgent() && $user->hasBusinessPermission('task_management', 'view'));
    }
    protected function managesAllTasks(): bool
    {
        $user = auth()->user();
        return $user->isBusinessOwner()
            || ($user->hasBusinessPermission('task_management', 'edit') && !$user->limitedToAssignedProperties());
    }
    /** "mine" (default for agents) or "all" (managers, and always for managers without an agent profile). */
    protected function boardScope(Request $request): string
    {
        if (!$this->seesAllTasks()) return 'mine';
        if (!$this->ownAgent()) return 'all';
        // Managers see every agent's tasks by default; agents can narrow to their own.
        return $request->input('scope') === 'mine' ? 'mine' : 'all';
    }
    /** One agent chosen on the board (managers only, within the business). */
    protected function agentFilter(Request $request, string $scope): ?int
    {
        $id = (int) $request->input('agent_id');
        if ($scope !== 'all' || $id <= 0) return null;
        return Agent::where('created_by', business_owner_id())->whereKey($id)->exists() ? $id : null;
    }
    /** Agents whose tasks appear on the task board. */
    protected function boardAgentIds(string $scope, ?int $agentFilter = null): array
    {
        if ($scope === 'all') {
            if ($agentFilter) return [$agentFilter];
            return Agent::where('created_by', business_owner_id())->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        return ($agent = $this->ownAgent()) ? [(int) $agent->id] : [];
    }
    /** Agents whose tasks the signed-in person may act on (start, complete, block, skip, add). */
    protected function workableAgentIds(): array
    {
        $ids = $this->managesAllTasks() ? $this->boardAgentIds('all') : [];
        if ($agent = $this->ownAgent()) $ids[] = (int) $agent->id;
        return array_values(array_unique($ids));
    }
}
