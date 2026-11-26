<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Requests\StoreLeadRequest;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Complex;
use App\Models\Lead;
use App\Models\LeadProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;


class LeadManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = business_owner_id();
        
        // Get manual leads count
        $manualLeadsActive = Lead::forBusiness($userId)->active()->count();
        $manualLeadsNewThisWeek = Lead::forBusiness($userId)->recentlyCreated(7)->count();
        $manualLeadsQualified = Lead::forBusiness($userId)->byStatus('qualified')->count();
        
        // Viewing requests from public listings are managed in the Listings module
        // (Listings → Viewings & applications), not in the CRM lead list.

        $stats = [
            'total_active' => $manualLeadsActive,
            'new_this_week' => $manualLeadsNewThisWeek,
            'qualified' => $manualLeadsQualified,
            'conversion_rate' => $this->calculateConversionRate()
        ];

        $agents = Agent::where('created_by', $userId)->where('status', 'active')->get();
        $property_types = cache()->remember('property_types', now()->addHour(), function() {
            return DB::table('property_types')->get();
        });

        return view('pages.business.crm.lead_management.index', compact('stats', 'agents', 'property_types'));
    }

    private function calculateConversionRate()
    {
        $totalLeads = Lead::forBusiness()->count();
        $convertedLeads = Lead::forBusiness()->where('status', 'closed_won')->count();
        
        if ($totalLeads === 0) return 0;
        
        return round(($convertedLeads / $totalLeads) * 100, 1);
    }

    /**
     * Show the lead onboarding page.
     */
    public function onboardLead()
    {
        $property_types = cache()->remember('property_types', now()->addHour(), function() {
            return DB::table('property_types')->get();
        });

        return view('pages.business.crm.lead_management.onboard_lead', compact('property_types'));
    }

    /**
     * Fetch leads data for DataTables.
     */
    public function getLeadsData(Request $request)
    {
        $userId = business_owner_id();
        
        // Get manual leads
        $query = Lead::with(['user', 'creator:id,first_name,last_name', 'assignedAgent', 'complex', 'profile'])
            ->where('user_id', $userId) // this business's leads only
            ->orderBy('created_at', 'desc');

        // Filter by lead type
        if ($request->lead_type && in_array($request->lead_type, ['Individual', 'Business'])) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('lead_type', $request->lead_type);
            });
        }
    
        // Filter by status
        if ($request->lead_status) {
            $query->where('status', $request->lead_status);
        }
    
        // Filter by priority
        if ($request->priority_level) {
            $query->where('priority', $request->priority_level);
        }
    
        // Filter by source
        if ($request->source) {
            $query->where('source', $request->source);
        }
    
        // Filter by property type
        if ($request->property_type) {
            $query->where('property_type', $request->property_type);
        }
    
        // Filter by name/company/email/phone (generic search)
        if (is_string($request->search) && trim($request->search) !== '') {
            $search = trim($request->search);
            $query->where(function($mainQuery) use ($search) {
                // Search in profile
                $mainQuery->whereHas('profile', function($q) use ($search) {
                    $q->whereLike('first_name', "%{$search}%")
                      ->orWhereLike('last_name', "%{$search}%")
                      ->orWhereLike('company_name', "%{$search}%")
                      ->orWhereLike('email', "%{$search}%")
                      ->orWhereLike('business_email', "%{$search}%")
                      ->orWhereLike('phone', "%{$search}%")
                      ->orWhereLike('business_phone', "%{$search}%");
                });
        
                // Also search in assigned agent (if exists)
                $mainQuery->orWhereHas('assignedAgent', function($q) use ($search) {
                    $q->whereLike('first_name', "%{$search}%")
                      ->orWhereLike('last_name', "%{$search}%");
                });
            });
        }

    
        // Filter by assigned agent
        if ($request->assigned_agent_id) {
            $query->where('assigned_agent_id', $request->assigned_agent_id);
           
        }
    
        // Filter by budget range
        if ($request->budget_min) {
            $query->where('budget_min', '>=', $request->budget_min);
        }
        if ($request->budget_max) {
            $query->where('budget_max', '<=', $request->budget_max);
        }
    
        // Filter by bedrooms/bathrooms
        if ($request->bedrooms) {
            $query->where('bedrooms', $request->bedrooms);
        }
        if ($request->bathrooms) {
            $query->where('bathrooms', $request->bathrooms);
        }
    
        // Filter by location preference
        if ($request->location_preference) {
            $query->whereLike('location_preference', "%{$request->location_preference}%");
        }
    
        // Filter by timeline
        if ($request->timeline) {
            $query->where('timeline', $request->timeline);
        }
    
        // Filter by contact method/time
        if ($request->contact_method) {
            $query->where('contact_method', $request->contact_method);
        }
        if ($request->contact_time) {
            $query->where('contact_time', $request->contact_time);
        }
    
        // Filter by follow up date
        if ($request->follow_up_date) {
            $query->whereDate('follow_up_date', $request->follow_up_date);
        }
    
        // Filter by complex
        if ($request->complex_id) {
            $query->where('complex_id', $request->complex_id);
        }
    
        // You can add more filters as needed...
        $manualLeads = $query->get();
        

        // Transform manual leads
        $transformedManualLeads = $manualLeads->map(function($lead) {
            return array_merge($lead->toArray(), [
                'encrypted_lead_id' => Crypt::encrypt($lead->id),
                'encrypted_user_id' => Crypt::encrypt($lead->user_id),
                'encrypted_agent_id' => Crypt::encrypt($lead->assigned_agent_id),
                'lead_source_type' => 'manual',
                'full_name' => $lead->profile ? ($lead->profile->lead_type === 'Individual' ? 
                    $lead->profile->first_name . ' ' . $lead->profile->last_name : 
                    $lead->profile->company_name) : 'N/A',
                'contact_email' => $lead->profile ? ($lead->profile->email ?? $lead->profile->business_email) : 'N/A',
                'contact_phone' => $lead->profile ? ($lead->profile->phone ?? $lead->profile->business_phone) : 'N/A',
                'added_by' => $lead->creator ? trim($lead->creator->first_name . ' ' . $lead->creator->last_name) : null,
                'ready_to_convert' => $lead->readyToConvert(),
                'agent_name' => $lead->assignedAgent?->full_name,
                'agent_url' => agent_profile_url($lead->assignedAgent),
            ]);
        });
        
        
        // CRM leads are manual leads only; viewing requests live in Listings → Viewings & applications.
        $allLeads = $transformedManualLeads;
        
        // Filter by lead source type if specified
        if ($request->lead_source_type) {
            $allLeads = $allLeads->filter(function($lead) use ($request) {
                return $lead['lead_source_type'] === $request->lead_source_type;
            });
        }
    
        return response()->json([
            'data' => $allLeads->values(), // Reset array keys after filter
            'total' => $allLeads->count(),
            'manual_count' => $transformedManualLeads->count(),
            'schedule_count' => 0
        ]);
    }

    /**
     * Search lead profiles for existing profile selection
     */
    public function searchProfiles(Request $request)
    {
        $search = $request->get('q', '');

        // Profiles behind this business's leads that the person can see (leads are limited to their properties).
        $profiles = LeadProfile::whereIn('id', Lead::forBusiness()->whereNotNull('lead_profile_id')->select('lead_profile_id'))
            ->where(function($query) use ($search) {
                $query->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%")
                      ->orWhere('company_name', 'LIKE', "%{$search}%")
                      ->orWhere('business_email', 'LIKE', "%{$search}%")
                      ->orWhere('business_phone', 'LIKE', "%{$search}%");
            })
            ->limit(20)
            ->get()
            ->map(function($profile) {
                $displayName = $profile->lead_type === 'Individual' 
                    ? "{$profile->first_name} {$profile->last_name}"
                    : $profile->company_name;
                
                $primaryContact = $profile->lead_type === 'Individual'
                    ? $profile->email
                    : "{$profile->business_email} • {$profile->contact_person}";
                
                return [
                    'id' => $profile->id,
                    'text' => $displayName,
                    'lead_type' => $profile->lead_type,
                    'contact' => $primaryContact,
                    'phone' => $profile->lead_type === 'Individual' ? $profile->phone : $profile->business_phone,
                    'profile' => $profile
                ];
            });
        
        return response()->json([
            'results' => $profiles
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLeadRequest $request)
    {
        try {
            $lead = DB::transaction(function () use ($request) {
                // Determine if using existing profile or creating new
                if ($request->profile_mode === 'existing' && $request->existing_profile_id) {
                    $profile = LeadProfile::whereIn('id', Lead::forBusiness()->select('lead_profile_id'))->find($request->existing_profile_id);
                    if (!$profile) {
                        throw new \Exception('Selected profile not found.');
                    }
                } else {
                    // Create the lead profile first
                    $profileData = $this->prepareProfileData($request);
                    $profile = LeadProfile::create($profileData);
                }
                
                
                // Prepare lead data with profile_id
                $leadData = $this->prepareLeadData($request, $profile->id);
                
                // Create the lead
                $lead = Lead::create($leadData);
                
                // Calculate initial lead score
                $lead->calculateLeadScore();
                $lead->addInteraction('lead_created', ['source' => $lead->source]);
                
                // Auto-assign if requested
                if ($request->assigned_agent === 'auto') {
                    $this->autoAssignAgent($lead);
                }
                
                // Set follow-up date if provided
                if ($request->follow_up_date) {
                    $lead->update([
                        'follow_up_date' => Carbon::parse($request->follow_up_date)
                    ]);
                }
                
                return $lead;
            });

            // The assigned agent is notified by LeadObserver.

            return response()->json([
                'status' => 200,
                'message' => 'Lead created successfully!',
                'data' => [
                    'lead_id' => $lead->id,
                    'lead_score' => $lead->lead_score,
                    'full_name' => $lead->full_name
                ]
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while creating the lead: ' . $e->getMessage()
            ], 500);
        }
    }
    
    
    private function prepareProfileData(Request $request)
    {
        $data = [
            'lead_type' => $request->lead_type,
        ];
    
        // Add Individual-specific fields
        if ($request->lead_type === 'Individual') {
            $data = array_merge($data, [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'occupation' => $request->occupation,
                'date_of_birth' => $request->date_of_birth,
                'current_address' => $request->current_address
            ]);
        }
    
        // Add Business-specific fields
        if ($request->lead_type === 'Business') {
            $data = array_merge($data, [
                'company_name' => $request->company_name,
                'registration_number' => $request->registration_number,
                'contact_person' => $request->contact_person,
                'contact_title' => $request->contact_title,
                'business_email' => $request->business_email,
                'business_phone' => $request->business_phone,
                'industry' => $request->industry,
                'company_size' => $request->company_size,
                'business_address' => $request->business_address
            ]);
        }
    
        return $data;
    }
    
    private function prepareLeadData(Request $request, $profileId)
    {
        $complex = $request->complex ? Complex::where('slug', $request->complex)->where('user_id', business_owner_id())->first() : null;
        if ($request->complex && !$complex) {
            throw \Illuminate\Validation\ValidationException::withMessages(['complex' => 'Choose one of your properties.']);
        }
        $complex_assigned_agent_id = $complex->assigned_agent_id ?? null;
        $requestedAgentId = $request->input('assigned_agent');
        if ($complex_assigned_agent_id) {
            $complex_assigned_agent_id = Agent::where('id', $complex_assigned_agent_id)
                ->where('created_by', business_owner_id())
                ->where('status', 'active')
                ->where('is_active', true)
                ->value('id');
        }
        
        $data = [
            'user_id' => business_owner_id(),
            'created_by' => auth()->id(), // who captured it (owner or team member)
            'lead_profile_id' => $profileId,
            'source' => $request->source,
            'property_type' => $request->property_type,
            'status' => 'new',
            'priority' => $request->priority,
            'purpose' => $request->purpose,
            'budget_min' => $request->budget_min,
            'budget_max' => $request->budget_max,
            'bedrooms' => $request->bedrooms,
            'bathrooms' => $request->bathrooms,
            'location_preference' => $request->location_preference,
            'timeline' => $request->timeline,
            'special_requirements' => $request->special_requirements,
            'contact_method' => $request->contact_method ?? 'any',
            'contact_time' => $request->contact_time ?? 'anytime',
            'notes' => $request->notes,
            'referral_details' => $request->referral_details,
            'min_sq_area' => $request->min_sq_area,
            'max_sq_area' => $request->max_sq_area,
            'business_use' => $request->business_use,
            'complex_id' => $complex->id ?? null,
            'assigned_agent_id' => $requestedAgentId === 'auto' ? null : ($requestedAgentId ?: $complex_assigned_agent_id),
        ];

    
        return $data;
    }
    
    private function autoAssignAgent(Lead $lead)
    {
        // For now, we'll skip this since agent management isn't implemented yet
        // This will be implemented when you add the agent management section
        
        $availableAgents = Agent::where('created_by', business_owner_id())
            ->where('status', 'active')
            ->where('is_active', true)
            ->get();
        
        if ($availableAgents->count() > 0) {
            $agent = $availableAgents->random();
            $lead->assignToAgent($agent->id);
        }
    }
    
    


    /**
     * Display the specified lead profile.
     */
    public function show($id)
    {
        try {
            $leadId = Crypt::decrypt($id);
            
            $lead = Lead::with([
                'user', 
                'creator',
                'convertedTenant',
                'assignedAgent', 
                'complex.assignedAgent', 
                'profile',
                'detailsConfirmedBy',
                'qualificationConfirmedBy',
                'tasks' => function($query) {
                    $query->orderBy('phase', 'asc')
                          ->orderBy('sequence_order', 'asc')
                          ->orderBy('due_date', 'asc');
                }
            ])->where('user_id', business_owner_id())->findOrFail($leadId);

            // Calculate engagement metrics
            $engagementMetrics = [
                'total_interactions' => count($lead->interactions ?? []),
                'last_contact' => $lead->last_contacted_at ? $lead->last_contacted_at->diffForHumans() : 'Never',
                'contact_attempts' => $lead->contact_attempts ?? 0,
                'lead_age_days' => (int) floor($lead->created_at->diffInDays(now())),
                'phase_duration_days' => $lead->phase_started_at ? $lead->phase_started_at->diffInDays(now()) : 0
            ];

            
            // Calculate phase progress based on actual tasks
            $totalTasks = $lead->tasks->count();
            $completedTasks = $lead->tasks->filter(function($task) {
                return in_array($task->status->value, ['completed', 'skipped']);
            })->count();
            
            $phaseProgress = [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'pending' => $lead->tasks->where('status.value', 'pending')->count(),
                'in_progress' => $lead->tasks->where('status.value', 'in_progress')->count(),
                'percentage' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0
            ];
            
            // Get activity timeline
            $activityTimeline = $this->getLeadActivityTimeline($lead);

            // dd( $activityTimeline);
            
            return view('pages.business.crm.lead_management.show', compact(
                'lead', 
                'engagementMetrics', 
                'phaseProgress',
                'activityTimeline'
            ));
            
        } catch (\Exception $e) {
            Log::error('Error loading lead profile: ' . $e->getMessage());
            return redirect()->route('lead_management.index')
                ->with('error', 'Lead not found or unable to load.');
        }
    }

    private function getLeadActivityTimeline(Lead $lead)
    {
        $timeline = [];
        
        // Add creation event (unless the lead's own "created" interaction already records who added it)
        $hasCreatedInteraction = is_array($lead->interactions) && collect($lead->interactions)->contains(fn ($i) => ($i['type'] ?? null) === 'lead_created');
        if (!$hasCreatedInteraction) $timeline[] = [
            'type' => 'created',
            'icon' => 'bi-plus-circle',
            'color' => 'success',
            'title' => 'Lead Created',
            'description' => "Lead created in the system",
            'timestamp' => $lead->created_at,
            'user' => $lead->user
        ];
        
        // Add interactions from database
        if ($lead->interactions && is_array($lead->interactions)) {
            foreach ($lead->interactions as $interaction) {
                $type = $interaction['type'] ?? 'interaction';
                $details = $interaction['details'] ?? [];
                
                // Build description based on interaction type
                $description = $this->buildInteractionDescription($type, $details);
                
                $timeline[] = [
                    'type' => $type,
                    'icon' => $this->getInteractionIcon($type),
                    'color' => $this->getInteractionColor($type),
                    'title' => $this->getInteractionTitle($type),
                    'description' => $description,
                    'details' => $details,
                    'timestamp' => \Carbon\Carbon::parse($interaction['timestamp'])->setTimezone(config('app.timezone')), // stored in UTC
                    'user' => isset($interaction['user_id']) ? User::find($interaction['user_id']) : null
                ];
            }
        }
        
        // Add agent assignment if exists
        if ($lead->assigned_agent_id && $lead->assignedAgent) {
            $timeline[] = [
                'type' => 'assigned',
                'icon' => 'bi-person-check',
                'color' => 'info',
                'title' => 'Agent Assigned',
                'description' => "Lead assigned to {$lead->assignedAgent->full_name}",
                'timestamp' => $lead->created_at, // Use creation time as proxy
                'user' => $lead->assignedAgent
            ];
        }
        
        // Sort by timestamp descending (most recent first)
        usort($timeline, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        return $timeline;
    }
    
    private function buildInteractionDescription($type, $details)
    {
        if (!is_array($details)) {
            return is_string($details) ? $details : '';
        }
        
        return match($type) {
            'task_auto_started' => "Task '{$details['task_title']}' was automatically started in {$details['phase']} phase",
            
            'task_started' => "Started task: {$details['task_title']}",
            
            'task_completed' => $this->formatTaskCompletionDescription($details),
            
            'phase_advanced' => "Lead advanced from " . ucfirst($details['old_phase']) . " to " . ucfirst($details['new_phase']) . " phase" 
                              . (isset($details['trigger_task']) ? " (triggered by: {$details['trigger_task']})" : ""),
            
            'status_change' => "Status changed from " . ucfirst($details['from'] ?? 'unknown') . " to " . ucfirst($details['to'] ?? 'unknown')
                             . (isset($details['notes']) && $details['notes'] ? ": {$details['notes']}" : ""),
            
            'assigned' => $details,
            
            'call', 'email', 'meeting', 'note' => is_string($details) ? $details : ($details['notes'] ?? ''),
            
            'lead_created' => 'Lead added' . (!empty($details['source']) ? ' from ' . str_replace('_', ' ', $details['source']) : ''),
            'converted_to_tenant' => 'Converted to a tenant' . (!empty($details['tenancy_ref']) ? ': tenancy ' . $details['tenancy_ref'] : ''),
            // Other structured details are shown as labelled fields under the entry, not as raw data.
            default => is_string($details) ? $details : ''
        };
    }
    
    private function formatTaskCompletionDescription($details)
    {
        $description = "Completed task: {$details['task_title']}";
        
        if (isset($details['completion_data']) && is_array($details['completion_data'])) {
            $data = $details['completion_data'];
            $highlights = [];
            
            // Extract key completion data points
            if (isset($data['interest_level'])) {
                $highlights[] = "Interest: " . ucwords(str_replace('_', ' ', $data['interest_level']));
            }
            if (isset($data['contact_outcome'])) {
                $highlights[] = "Outcome: " . ucwords(str_replace('_', ' ', $data['contact_outcome']));
            }
            if (isset($data['new_status'])) {
                $highlights[] = "New Status: " . ucwords($data['new_status']);
            }
            if (isset($data['priority'])) {
                $highlights[] = "Priority: " . ucwords($data['priority']);
            }
            if (isset($data['property_count'])) {
                $highlights[] = "{$data['property_count']} properties identified";
            }
            
            if (!empty($highlights)) {
                $description .= " • " . implode(' • ', $highlights);
            }
        }
        
        return $description;
    }
    
    private function getInteractionTitle($type)
    {
        return match($type) {
            'task_auto_started' => 'Task Auto-Started',
            'task_started' => 'Task Started',
            'task_completed' => 'Task Completed',
            'phase_advanced' => 'Phase Advanced',
            'status_change' => 'Status Changed',
            'assigned' => 'Agent Assigned',
            'call' => 'Phone Call',
            'email' => 'Email Sent',
            'meeting' => 'Meeting Held',
            'note' => 'Note Added',
            'lead_created' => 'Lead added',
            'converted_to_tenant' => 'Converted to tenant',
            default => ucfirst(str_replace('_', ' ', $type))
        };
    }
    
    private function getInteractionColor($type)
    {
        return match($type) {
            'task_completed' => 'success',
            'phase_advanced' => 'success',
            'task_auto_started', 'task_started' => 'primary',
            'status_change' => 'warning',
            'assigned' => 'info',
            'call', 'email', 'meeting' => 'primary',
            'note' => 'secondary',
            'lead_created' => 'success',
            'converted_to_tenant' => 'success',
            default => 'primary'
        };
    }
    
    private function getInteractionIcon($type)
    {
        return match($type) {
            'task_auto_started', 'task_started' => 'bi-play-circle',
            'task_completed' => 'bi-check-circle',
            'phase_advanced' => 'bi-arrow-right-circle',
            'status_change' => 'bi-arrow-repeat',
            'assigned' => 'bi-person-check',
            'call' => 'bi-telephone',
            'email' => 'bi-envelope',
            'meeting' => 'bi-calendar-event',
            'note' => 'bi-journal-text',
            'created' => 'bi-plus-circle',
            'lead_created' => 'bi-person-plus',
            'converted_to_tenant' => 'bi-house-door',
            default => 'bi-chat-dots'
        };
    }


    
    
}
