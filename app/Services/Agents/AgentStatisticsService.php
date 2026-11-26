<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\Lead;
use App\Models\LeadTask;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgentStatisticsService
{
    /**
     * Get high-level stats for all agents belonging to a user (dashboard overview).
     */
    public function getDashboardStats(int $userId): array
    {
        $activeAgents = Agent::where('created_by', $userId)->active()->count();
        $agentIds = Agent::where('created_by', $userId)->active()->pluck('id');

        return [
            'active' => $activeAgents,
            'top_performers' => $this->getTopPerformersCount($agentIds),
            'total_sales' => $this->getTotalSalesThisMonth($agentIds),
            'avg_commission' => $this->getAvgCommissionRate($userId),
        ];
    }

    /**
     * Get statistics for a single agent.
     */
    public function getAgentStats(int $agentId): array
    {
        return [
            'total_leads' => Lead::where('assigned_agent_id', $agentId)->count(),
            'active_leads' => Lead::where('assigned_agent_id', $agentId)
                ->whereIn('status', ['new', 'contacted', 'qualified', 'proposal'])
                ->count(),
            'converted_leads' => Lead::where('assigned_agent_id', $agentId)
                ->where('status', 'closed_won')
                ->count(),
            'total_tasks' => LeadTask::where('assigned_agent_id', $agentId)->count(),
            'pending_tasks' => LeadTask::where('assigned_agent_id', $agentId)
                ->where('status', 'pending')
                ->count(),
            'completed_tasks' => LeadTask::where('assigned_agent_id', $agentId)
                ->where('status', 'completed')
                ->count(),
            'overdue_tasks' => LeadTask::where('assigned_agent_id', $agentId)->overdue()->count(),
        ];
    }

    /**
     * Get performance metrics for a single agent.
     */
    public function getPerformanceMetrics(int $agentId): array
    {
        $stats = $this->getAgentStats($agentId);

        // Calculate average response time (time between lead creation and first interaction)
        // Calculate this in PHP so the query works across the database engines
        // supported by the app (PostgreSQL does not provide MySQL's TIMESTAMPDIFF).
        $leadsWithInteractions = Lead::where('assigned_agent_id', $agentId)
            ->whereNotNull('last_contacted_at')
            ->get(['created_at', 'last_contacted_at']);

        $avgHours = $leadsWithInteractions->isEmpty()
            ? null
            : $leadsWithInteractions->avg(fn (Lead $lead) =>
                $lead->created_at->diffInSeconds($lead->last_contacted_at) / 3600
            );
        if ($avgHours !== null) {
            $avgResponseTime = $avgHours < 1
                ? round($avgHours * 60) . ' mins'
                : round($avgHours, 1) . ' hours';
        } else {
            $avgResponseTime = 'N/A';
        }

        return [
            'conversion_rate' => $stats['total_leads'] > 0
                ? round(($stats['converted_leads'] / $stats['total_leads']) * 100, 2)
                : 0,
            'task_completion_rate' => $stats['total_tasks'] > 0
                ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 2)
                : 0,
            'avg_response_time' => $avgResponseTime,
            'customer_satisfaction' => 'N/A',
        ];
    }

    /**
     * Count agents with at least one conversion in the last 30 days.
     */
    private function getTopPerformersCount(Collection $agentIds): int
    {
        return Lead::whereIn('assigned_agent_id', $agentIds)
            ->converted()
            ->where('converted_at', '>=', Carbon::now()->subDays(30))
            ->distinct('assigned_agent_id')
            ->count('assigned_agent_id');
    }

    /**
     * Sum of budget_max from converted leads this month.
     */
    private function getTotalSalesThisMonth(Collection $agentIds): float
    {
        return (float) Lead::whereIn('assigned_agent_id', $agentIds)
            ->converted()
            ->where('converted_at', '>=', Carbon::now()->startOfMonth())
            ->sum('budget_max');
    }

    /**
     * Average commission rate across active agents for a user.
     */
    private function getAvgCommissionRate(int $userId): float
    {
        return round(
            Agent::where('created_by', $userId)->active()->avg('commission_rate') ?? 0,
            1
        );
    }
}
