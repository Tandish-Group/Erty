<?php

namespace App\Http\Controllers\Business\Crm;

use App\Enums\LeadTaskStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadTask;
use Carbon\Carbon;

class CrmDashboardController extends Controller
{
    /**
     * Display the CRM dashboard with all metrics
     */
    public function index()
    {
        $user = auth()->user();
        
        // Get lead statistics
        $leadStats = $this->getLeadStatistics();
        
        // CRM is about leads; agents and their tasks have their own section (/agents).
        // Get recent leads
        $recentLeads = $this->getRecentLeads();
        
        // Get pipeline overview
        $pipelineStats = $this->getPipelineStatistics();

        return view('pages.business.crm.index', compact(
            'leadStats',
            'recentLeads',
            'pipelineStats'
        ));
    }

    /**
     * Get lead statistics
     */
    private function getLeadStatistics()
    {
        $user = auth()->user();
        $userId = $user->businessOwnerId();
    
        // Manual leads (from Lead model)
        $manualLeadsActive = Lead::forBusiness($userId)
            ->whereIn('status', ['new', 'contacted', 'qualified', 'in_progress'])
            ->count();
    
        $manualLeadsNewThisWeek = Lead::forBusiness($userId)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->count();
    
        // Leads are the business's own leads; renter viewing requests were removed with the renter side.
        return [
            'total_active' => $manualLeadsActive,
            'new_this_week' => $manualLeadsNewThisWeek,
            'qualified' => Lead::forBusiness($userId)
                ->where('status', 'qualified')
                ->count(),
            'conversion_rate' => $this->calculateConversionRate($userId)
        ];
    }

    /**
     * Calculate conversion rate
     */
    private function calculateConversionRate($userId)
    {
        $totalLeads = Lead::forBusiness($userId)->count();
        $convertedLeads = Lead::forBusiness($userId)
            ->converted()
            ->count();
        
        if ($totalLeads === 0) return 0;
        
        return round(($convertedLeads / $totalLeads) * 100, 1);
    }




    /**
     * Get recent leads for the dashboard
     */
    private function getRecentLeads()
    {
        $user = auth()->user();

        return Lead::forBusiness($user->businessOwnerId())
            ->with(['assignedAgent', 'profile', 'creator:id,first_name,last_name'])
            ->whereIn('status', ['new', 'contacted', 'qualified'])
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get()
            ->map(function($lead) {
                $profile = $lead->profile;
                $name = $profile?->lead_type === 'Business'
                    ? ($profile->company_name ?: 'Business lead')
                    : (trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? '')) ?: 'Lead #' . $lead->id);
                return [
                    'id' => $lead->id,
                    'url' => route('lead_management.show', \Illuminate\Support\Facades\Crypt::encrypt($lead->id)),
                    'name' => $name,
                    'initials' => strtoupper(mb_substr($name, 0, 1)),
                    'added_by' => $lead->creator ? trim($lead->creator->first_name . ' ' . $lead->creator->last_name) : null,
                    'description' => $lead->property_type ? "Looking for {$lead->bedrooms}BR {$lead->property_type}" : 'No property preference set',
                    'priority' => $lead->priority ?? 'medium',
                    'priority_label' => $this->getPriorityLabel($lead->priority ?? 'medium'),
                    'created_at' => $lead->created_at->diffForHumans(),
                    'agent' => $lead->assignedAgent ? $lead->assignedAgent->full_name : 'Unassigned',
                    'agent_url' => agent_profile_url($lead->assignedAgent)
                ];
            });
    }

    /**
     * Get pipeline statistics
     */
    private function getPipelineStatistics()
    {
        $user = auth()->user();

        $phases = [
            'qualification' => [
                'label' => 'Qualification',
                'icon' => 'rocket',
                'color' => 'primary'
            ],
            'engagement' => [
                'label' => 'Engagement',
                'icon' => 'handshake',
                'color' => 'warning'
            ],
            'conversion' => [
                'label' => 'Conversion',
                'icon' => 'document',
                'color' => 'info'
            ],
            'finalization' => [
                'label' => 'Finalization',
                'icon' => 'check-circle',
                'color' => 'success'
            ]
        ];

        $stats = [];

        foreach ($phases as $phase => $config) {
            $leadsInPhase = Lead::forBusiness($user->businessOwnerId())
                ->where('workflow_phase', $phase)
                ->get();

            $count = $leadsInPhase->count();
            $totalValue = $leadsInPhase->sum(function($lead) {
                // Estimate deal value from budget_max or average
                return $lead->budget_max ?? ($lead->budget_min ?? 0);
            });

            // Calculate progress percentage (based on completed tasks)
            $totalTasks = LeadTask::whereIn('lead_id', $leadsInPhase->pluck('id'))
                ->where('phase', $phase)
                ->count();
            
            $completedTasks = LeadTask::whereIn('lead_id', $leadsInPhase->pluck('id'))
                ->where('phase', $phase)
                ->where('status', LeadTaskStatusEnum::COMPLETED)
                ->count();

            $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

            $stats[$phase] = array_merge($config, [
                'count' => $count,
                'value' => $totalValue,
                'progress' => $progress
            ]);
        }

        return $stats;
    }

    /**
     * Get priority label
     */
    private function getPriorityLabel($priority)
    {
        $labels = [
            'low' => 'Cold',
            'medium' => 'Warm',
            'high' => 'Hot'
        ];

        return $labels[$priority] ?? 'Warm';
    }
}