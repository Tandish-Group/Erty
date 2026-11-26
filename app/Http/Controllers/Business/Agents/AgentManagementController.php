<?php

namespace App\Http\Controllers\Business\Agents;

use App\Http\Requests\StoreAgentRequest;

use App\Models\BusinessRole;
use App\Services\Agents\AgentProfileService;
use App\Services\Access\TeamInvitationService;
use App\Models\Lead;
use App\Models\Agent;
use App\Models\LeadTask;
use App\Models\Complex;
use App\Models\TenantManagement;
use App\Models\AgentRole;
use Illuminate\Http\Request;
use App\Models\AgentDepartment;
use Illuminate\Validation\Rule;
use App\Models\AgentWorkLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\Agents\AgentStatisticsService;
use App\Services\StorageUsageService;

class AgentManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(AgentStatisticsService $agentStatsService)
    {
        $userId = business_owner_id();

        $agentRoles = AgentRole::where('created_by', $userId)
            ->active()
            ->get();
        $agentDepartments = AgentDepartment::where('created_by', $userId)
            ->active()
            ->get();
        $agentWorkLocations = AgentWorkLocation::where('created_by', $userId)
            ->active()
            ->get();

        $agentStats = $agentStatsService->getDashboardStats($userId);
        // Inviting an agent who signs in creates a team member, so it needs that permission too.
        $canInviteLogin = auth()->user()->hasBusinessPermission('agent_management', 'invite')
            && auth()->user()->hasBusinessPermission('subaccount_management', 'create');
        $teamRoles = $canInviteLogin ? BusinessRole::forBusiness($userId)->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect();

        return view('pages.business.crm.agent_management.index', compact(
            'agentRoles', 'agentDepartments', 'agentWorkLocations', 'agentStats', 'canInviteLogin', 'teamRoles'
        ));
    }

     /**
     * Show the lead onboarding page.
     */
    public function onboardAgent()
    {
       $agentRoles = AgentRole::where('created_by', business_owner_id())
            ->active()
            ->get();
        $agentDepartments = AgentDepartment::where('created_by', business_owner_id())
            ->active()
            ->get();
        $agentWorkLocations = AgentWorkLocation::where('created_by', business_owner_id())
            ->active()
            ->get();

        return view('pages.business.crm.agent_management.onboard_agent', compact('agentRoles', 'agentDepartments', 'agentWorkLocations'));
    }

    /**
     * Get agents data for listing
     */
    public function getAgentsData(Request $request)
    {
        try {
            $query = Agent::with(['department', 'role', 'workLocation'])
                ->where('created_by', business_owner_id());
    
            // Apply status filter
            if ($request->status != '') {
                $query->where('status', $request->status);
            }
    
            // Apply department filter
            if ($request->department != '') {
                $query->where('department_id', $request->department);
            }
    
            // Apply search
            if ($request->search != '') {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->whereLike('first_name', "%{$search}%")
                        ->orWhereLike('last_name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereLike('phone', "%{$search}%");
                });
            }
    
            // Apply ordering
            if ($request->has('order')) {
                $orderColumn = $request->order[0]['column'];
                $orderDir = $request->order[0]['dir'];
    
                $columns = ['id', 'first_name', 'department_id', 'status', 'created_at'];
                if (isset($columns[$orderColumn])) {
                    $query->orderBy($columns[$orderColumn], $orderDir);
                }
            } else {
                $query->orderBy('created_at', 'desc');
            }
    
            // Apply pagination
            $start = $request->start ?? 0;
            $length = $request->length ?? 25;
            $agents = $query->skip($start)->take($length)->get();
    
            // Use an opaque URL-safe token for profile links instead of exposing
            // sequential database IDs in the listing response.
            $agents = $agents->map(function($agent) {
                $agentArray = $agent->toArray();
                $agentArray['encrypted_id'] = $agent->profile_route_token;
                $agentArray['has_login'] = (bool) $agent->user_id;
                unset($agentArray['user_id']);
                return $agentArray;
            });
    
            // Return plain agent data
            return response()->json([
                'data' => $agents,
                'recordsTotal' => $query->count(),
                'recordsFiltered' => $query->count(),
            ]);
    
        } catch (\Exception $e) {
            Log::error('Error fetching agents data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'error' => 'An error occurred while fetching agents data'
            ]);
        }
    }

    /**
     * Search agents for Select2 AJAX
     */
    public function searchAgents(Request $request)
    {
        try {
            $perPage = 10;
            $page = max(1, (int) $request->input('page', 1));
            $term = $request->input('q', $request->input('term', ''));
    
            // If id(s) provided, return those records (useful for Select2 initial selection)
            if ($request->filled('id')) {
                $ids = is_array($request->id) ? $request->id : explode(',', $request->id);
                $agents = Agent::whereIn('id', $ids)
                    ->where('created_by', business_owner_id())
                    ->where('status', 'active')
                    ->where('is_active', true)
                    ->get();
    
                $results = $agents->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'text' => $a->full_name . ($a->user_id ? ' (team member)' : ''),
                        'email' => $a->email,
                        'phone' => $a->phone,
                    ];
                })->values();
    
                return response()->json(['results' => $results, 'total' => $results->count()]);
            }
    
            $query = Agent::where('created_by', business_owner_id())
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('status', 'active')->orWhereNull('status');
                });
            // Agents without a sign-in, for linking to a team member.
            if ($request->boolean('unlinked')) {
                $query->whereNull('user_id');
            }
            // Only agents who sign in can be assigned to properties and units.
            if ($request->boolean('signin')) {
                $query->whereNotNull('user_id');
            }
            // Unit pickers: the agents the signed-in person may assign on this property.
            if ($request->filled('units_of')) {
                $complex = Complex::forBusiness()->where('slug', $request->input('units_of'))->first();
                $allowed = $complex ? app(\App\Services\Agents\AgentAssignmentService::class)->assignableForUnits(auth()->user(), $complex)->pluck('id') : collect();
                $query->whereIn('id', $allowed->all() ?: [0]);
            }
    
            if (!empty($term)) {
                $query->where(function ($q) use ($term) {
                    $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
                      ->orWhereLike('first_name', "%{$term}%")
                      ->orWhereLike('last_name', "%{$term}%")
                      ->orWhereLike('email', "%{$term}%")
                      ->orWhereLike('phone', "%{$term}%");
                });
            }
    
            $total = $query->count();
            $agents = $query->orderBy('first_name')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
    
            $results = $agents->map(function ($a) {
                return [
                    'id' => $a->id,
                    'text' => $a->full_name . ($a->user_id ? ' (team member)' : ''),
                    'email' => $a->email,
                    'phone' => $a->phone,
                ];
            })->values();
    
            return response()->json([
                'results' => $results,
                'total' => $total,
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching agents', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['results' => [], 'total' => 0], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAgentRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                // Handle file uploads
                $profilePhotoPath = null;
                $resumePath = null;
                $totalBytesUploaded = 0;

                if ($request->hasFile('profile_photo')) {
                    $profilePhotoPath = $request->file('profile_photo')->store('agents/photos', 'public');
                    $totalBytesUploaded += $request->file('profile_photo')->getSize();
                }

                if ($request->hasFile('resume')) {
                    $resumePath = $request->file('resume')->store('agents/resumes', 'public');
                    $totalBytesUploaded += $request->file('resume')->getSize();
                }

                // Track storage usage
                if ($totalBytesUploaded > 0) {
                    app(StorageUsageService::class)->incrementUsage($totalBytesUploaded);
                }

                // Prepare base data
                $data = [
                    'agent_type' => $request->agent_type,
                    'department_id' => $request->department,
                    'role_id' => $request->role,
                    'profile_photo' => $profilePhotoPath,
                    'resume' => $resumePath,
                    'certifications' => $request->certifications,
                    'years_experience' => $request->years_experience ?? 0,
                    'previous_employer' => $request->previous_employer,
                    'linkedin_profile' => $request->linkedin_profile,
                    'references' => $request->references,
                    'notes' => $request->notes,
                    'status' => 'active',
                    'is_active' => true,
                    'created_by' => business_owner_id(),
                ];

                // Add type-specific data
                if ($request->agent_type === 'agency') {
                    $data = array_merge($data, [
                        'company_name' => $request->company_name,
                        'agency_license' => $request->agency_license,
                        'contact_person_name' => $request->contact_person_name,
                        'contact_person_email' => $request->contact_person_email,
                        'contact_person_phone' => $request->contact_person_phone,
                        'company_address' => $request->company_address,
                        'tax_id' => $request->tax_id,
                        'business_registration_number' => $request->business_registration_number,
                        'email' => $request->contact_person_email, // Use contact email as main email
                        'phone' => $request->contact_person_phone, // Use contact phone as main phone
                        'first_name' => $request->company_name, // Use company name as first name for agencies
                        'last_name' => 'Agency', // Placeholder
                        'start_date' => now(), // Set current date for agencies
                        'commission_rate' => $request->commission_rate,
                        'commission_type' => $request->commission_type,
                        'payment_frequency' => $request->payment_frequency,
                        'base_salary' => $request->base_salary ?? 0,
                        'bonus_rate' => $request->bonus_rate ?? 0,
                        'sales_target' => $request->sales_target ?? 0,
                    ]);
                } elseif ($request->agent_type === 'employee') {
                    $data = array_merge($data, [
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'email' => $request->email,
                        'phone' => $request->phone,
                        'date_of_birth' => $request->date_of_birth,
                        'gender' => $request->gender,
                        'address' => $request->address,
                        'national_id' => $request->national_id,
                        'emergency_contact' => $request->emergency_contact,
                        'employment_type' => $request->employment_type,
                        'start_date' => $request->start_date,
                        'reporting_manager_id' => $request->reporting_manager,
                        'work_location_id' => $request->work_location,
                        'probation_period' => $request->probation_period ?? 0,
                        'specializations' => $request->specializations ? json_encode($request->specializations) : null,
                        'job_description' => $request->job_description,
                        'base_salary' => $request->base_salary ?? 0,
                        'commission_rate' => $request->commission_rate,
                        'commission_type' => $request->commission_type,
                        'bonus_rate' => $request->bonus_rate ?? 0,
                        'payment_frequency' => $request->payment_frequency,
                        'benefits' => $request->benefits ? json_encode($request->benefits) : null,
                        'sales_target' => $request->sales_target ?? 0,
                        'bank_account' => $request->bank_account,
                        'compensation_notes' => $request->compensation_notes,
                    ]);
                } elseif ($request->agent_type === 'contractor') {
                    $data = array_merge($data, [
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'email' => $request->email,
                        'phone' => $request->phone,
                        'date_of_birth' => $request->date_of_birth,
                        'gender' => $request->gender,
                        'address' => $request->address,
                        'national_id' => $request->national_id,
                        'emergency_contact' => $request->emergency_contact,
                        'start_date' => $request->contract_start_date, // Use contract start date as start date
                        'contract_start_date' => $request->contract_start_date,
                        'contract_end_date' => $request->contract_end_date,
                        'contract_type' => $request->contract_type,
                        'work_location_id' => $request->work_location_contractor,
                        'specializations' => $request->specializations ? json_encode($request->specializations) : null,
                        'job_description' => $request->job_description,
                        'hourly_rate' => $request->hourly_rate ?? 0,
                        'project_fee' => $request->project_fee ?? 0,
                        'payment_terms' => $request->payment_terms,
                        'commission_rate' => $request->commission_rate_contractor ?? 0,
                        'commission_type' => $request->commission_type_contractor,
                        'payment_frequency' => $request->payment_frequency_contractor,
                        'sales_target' => $request->sales_target_contractor ?? 0,
                    ]);
                }

                // Create the agent
                $agent = Agent::create($data);

                Log::info('Agent created successfully', ['agent_id' => $agent->id]);

                return response()->json([
                    'status' => 200,
                    'message' => 'Agent added successfully',
                    'data' => [
                        'id' => $agent->id,
                        'name' => $agent->first_name . ' ' . $agent->last_name,
                        'email' => $agent->email
                    ]
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error creating agent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while saving the agent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Invite an agent who also signs in: creates a team member (email invitation to set a password)
     * and links a CRM agent profile to them. External agents without a sign-in are added through
     * the onboarding form instead.
     */
    public function inviteAgent(Request $request, TeamInvitationService $invitations, AgentProfileService $agents)
    {
        abort_unless(auth()->user()->hasBusinessPermission('subaccount_management', 'create'), 403, 'Your role does not allow you to invite team members.');
        $ownerId = business_owner_id();

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email|unique:agents,email',
            'mobile' => 'required|string|max:20',
            'business_role_id' => ['required', 'integer', Rule::exists('business_roles', 'id')->where('business_id', $ownerId)],
            'role' => ['nullable', 'integer', Rule::exists('agent_roles', 'id')->where('created_by', $ownerId)],
            'department' => ['nullable', 'integer', Rule::exists('agent_departments', 'id')->where('created_by', $ownerId)],
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'message' => 'nullable|string|max:1000',
        ], [
            'email.unique' => 'This email address already has an account or agent profile.',
        ]);

        $teamRole = BusinessRole::forBusiness($ownerId)->findOrFail($data['business_role_id']);

        $agent = DB::transaction(function () use ($data, $teamRole, $invitations, $agents) {
            $member = $invitations->invite($data, $teamRole);
            $agent = $agents->ensureFor($member);
            $agent->update([
                'role_id' => $data['role'] ?? null,
                'department_id' => $data['department'] ?? null,
                'commission_rate' => $data['commission_rate'] ?? 0,
                'notes' => $data['message'] ?? null,
            ]);
            return $agent;
        });

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => "Invitation sent to {$agent->email}. They'll set a password and can then work their leads and tasks.",
            'data' => ['id' => $agent->id, 'email' => $agent->email, 'status' => $agent->status],
        ]);
    }


    /**
     * Show agent profile
     */
    public function showProfile($encryptedId, AgentStatisticsService $agentStatsService)
    {
        $id = null;

        try {
            $id = Agent::idFromProfileRouteToken((string) $encryptedId);

            $agent = Agent::with([
                'department',
                'role',
                'workLocation',
                'reportingManager',
                'creator'
            ])
            ->where('id', $id)
            ->where('created_by', business_owner_id())
            ->firstOrFail();
    
            // Get agent statistics
            $statistics = $agentStatsService->getAgentStats($agent->id);

            // Calculate performance metrics
            $performanceMetrics = $agentStatsService->getPerformanceMetrics($agent->id);

            // Get agent's leads
            $leads = Lead::where('assigned_agent_id', $agent->id)
                ->with(['assignedAgent', 'complex', 'convertedTenant.tenantProfile'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            $tenancies = TenantManagement::where('assigned_agent_id', $agent->id)
                ->where('managed_by', business_owner_id())
                ->with(['tenantProfile', 'complex', 'residentialUnit', 'commercialUnit'])
                ->orderByDesc('created_at')
                ->get();

            $relatedComplexIds = Lead::where('assigned_agent_id', $agent->id)
                ->whereNotNull('complex_id')
                ->distinct()
                ->pluck('complex_id')
                ->merge($tenancies->pluck('complex_id'))
                ->filter()
                ->unique()
                ->values();

            $propertyOwnerId = auth()->user()->is_sub_account
                ? auth()->user()->main_account_user_id
                : business_owner_id();
            $properties = Complex::forBusiness($propertyOwnerId)
                ->where(function ($query) use ($agent, $relatedComplexIds) {
                    $query->where('assigned_agent_id', $agent->id);
                    if ($relatedComplexIds->isNotEmpty()) {
                        $query->orWhereIn('id', $relatedComplexIds);
                    }
                })
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'type', 'county']);

            // What this agent answers for: properties they manage as primary, and units delegated to them elsewhere.
            $responsibility = $this->responsibilityOf($agent);

            // Get agent's tasks (all of them for proper grouping)
            $tasks = LeadTask::where('assigned_agent_id', $agent->id)
                ->with(['lead', 'template', 'blockedByTask'])
                ->orderBy('phase')
                ->orderBy('sequence_order')
                ->orderBy('created_at', 'desc')
                ->get();
    
            // Activity timeline
            $activityTimeline = [];
            
            // Add lead assignments to timeline
            foreach ($leads->take(5) as $lead) {
                $activityTimeline[] = [
                    'date' => $lead->created_at,
                    'icon' => 'fa-user-plus',
                    'color' => 'primary',
                    'title' => 'Lead Assigned',
                    'description' => "Assigned lead: {$lead->full_name}"
                ];
            }
    
            // Add task completions to timeline
            $completedTasks = LeadTask::where('assigned_agent_id', $agent->id)
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->take(5)
                ->get();
    
            foreach ($completedTasks as $task) {
                $activityTimeline[] = [
                    'date' => $task->completed_at,
                    'icon' => 'fa-check-circle',
                    'color' => 'success',
                    'title' => 'Task Completed',
                    'description' => $task->title
                ];
            }
    
            // Sort timeline by date
            usort($activityTimeline, function ($a, $b) {
                return $b['date'] <=> $a['date'];
            });
    
            // Sign-in link (team member) and commission for the chosen period.
            $login = $agent->user_id ? \App\Models\User::with('businessRole')->find($agent->user_id) : null;
            $canManageLogin = auth()->user()->hasBusinessPermission('agent_management', 'edit');
            $canInviteLogin = $canManageLogin && auth()->user()->hasBusinessPermission('subaccount_management', 'create');
            $teamRoles = $canInviteLogin ? BusinessRole::forBusiness(business_owner_id())->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect();
            [$period, $commission] = $this->commissionFor($agent, request());
            // Hierarchy: who this agent reports to, and who reports to them.
            $agent->load(['reportsTo', 'subAgents']);
            $descendants = $this->descendantIds($agent);
            $leadOptions = app(\App\Services\Agents\AgentAssignmentService::class)->signInAgents()
                ->reject(fn ($a) => $a->id === $agent->id || in_array($a->id, $descendants, true))->values();

            return view('pages.business.crm.agent_management.profile', compact(
                'agent',
                'statistics',
                'leads',
                'tenancies',
                'properties',
                'tasks',
                'performanceMetrics',
                'activityTimeline',
                'login',
                'canManageLogin',
                'canInviteLogin',
                'teamRoles',
                'period',
                'commission',
                'leadOptions',
                'responsibility'
            ));
    
        } catch (\Exception $e) {
            Log::error('Error loading agent profile', [
                'agent_id' => $id,
                'error' => $e->getMessage()
            ]);
    
            return redirect()->route('agent_management.index')
                ->with('error', 'Agent profile not found');
        }
    }

    /**
     * Properties the agent manages as primary manager, and the units delegated to them on other properties
     * (limited, like everything else, to what the viewer may see).
     *
     * @return array{primary: \Illuminate\Support\Collection, delegated: \Illuminate\Support\Collection, unit_count: int}
     */
    private function responsibilityOf(Agent $agent): array
    {
        $primary = Complex::where('assigned_agent_id', $agent->id)->where('management_type', Complex::MANAGED_BY_INDIVIDUAL)
            ->withCount(['complex_residential_units as residential_count', 'commercialUnits as commercial_count'])
            ->orderBy('title')->get(['id', 'title', 'slug', 'type', 'county', 'management_type', 'assigned_agent_id']);
        $units = collect()
            ->merge(\App\Models\ComplexResidentialUnit::where('assigned_agent_id', $agent->id)->get(['id', 'complex_id', 'unit_number'])->each(fn ($u) => $u->kind = 'residential'))
            ->merge(\App\Models\CommercialUnit::where('assigned_agent_id', $agent->id)->get(['id', 'complex_id', 'unit_number'])->each(fn ($u) => $u->kind = 'commercial'));
        $complexes = Complex::with('assignedAgent:id,first_name,last_name')->whereIn('id', $units->pluck('complex_id')->unique())
            ->whereNotIn('id', $primary->pluck('id'))->orderBy('title')->get(['id', 'title', 'slug', 'management_type', 'assigned_agent_id']);
        $delegated = $complexes->map(fn ($complex) => (object) [
            'complex' => $complex,
            'units' => $units->where('complex_id', $complex->id)->sortBy('unit_number', SORT_NATURAL)->values(),
        ]);
        return ['primary' => $primary, 'delegated' => $delegated, 'unit_count' => $delegated->sum(fn ($d) => $d->units->count())];
    }

    /** [period, commission] for the period chosen in the request (default: this month). */
    private function commissionFor(Agent $agent, Request $request): array
    {
        $period = \App\Services\Commission\AgentCommissionService::period($request->query('period'), $request->query('from'), $request->query('to'));
        $commission = app(\App\Services\Commission\AgentCommissionService::class)->forAgent($agent, $period['from'], $period['to']);
        return [$period, $commission];
    }

    /** Ids of every agent below this one in the reporting chain. */
    private function descendantIds(Agent $agent): array
    {
        $ids = [];
        $frontier = [$agent->id];
        while ($frontier) {
            $next = Agent::whereIn('reports_to_agent_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->diff($ids)->all();
            $ids = array_merge($ids, $next);
            $frontier = $next;
        }
        return $ids;
    }

    /** Set who this agent reports to, and the override this agent earns on their own sub-agents. */
    public function updateHierarchy(Request $request, string $encryptedId)
    {
        $agent = Agent::fromProfileToken($encryptedId);
        $data = $request->validate([
            'reports_to_agent_id' => 'nullable|integer',
            'override_rate' => 'required|numeric|min:0|max:100',
        ]);
        $leadId = $data['reports_to_agent_id'] ?? null;
        if ($leadId) {
            if ((int) $leadId === (int) $agent->id || in_array((int) $leadId, $this->descendantIds($agent), true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['reports_to_agent_id' => 'An agent cannot report to themselves or to someone who reports to them.']);
            }
            app(\App\Services\Agents\AgentAssignmentService::class)->assertAssignable((int) $leadId, 'reports_to_agent_id');
        }
        $agent->forceFill(['reports_to_agent_id' => $leadId, 'override_rate' => $data['override_rate']])->save();

        return response()->json(['success' => true, 'message' => 'Team and override saved.']);
    }

    /**
     * Update agent status
     */
    public function updateStatus(Request $request, $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            $agent = Agent::where('id', $id)
                ->where('created_by', business_owner_id())
                ->firstOrFail();
    
            $agent->update([
                'status' => $request->status
            ]);
    
            return response()->json([
                'success' => true,
                'message' => 'Agent status updated successfully'
            ]);
    
        } catch (\Exception $e) {
            Log::error('Error updating agent status', [
                'agent_id' => $id,
                'error' => $e->getMessage()
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent status'
            ], 500);
        }
    }

    /**
     * Show all tasks for a specific lead assigned to an agent
     */
    public function showLeadTasks($encryptedLeadId)
    {
        try {
            $leadId = decrypt($encryptedLeadId);
            $user = auth()->user();
            $lead = Lead::with(['assignedAgent', 'complex'])
                ->where('id', $leadId)
                ->whereHas('assignedAgent', fn ($query) => $query->where('created_by', $user->businessOwnerId()))
                ->firstOrFail();

            $agent = $lead->assignedAgent;
    
            // Get all tasks for this lead
            $tasks = LeadTask::with(['template', 'blockedByTask', 'creator', 'completedBy'])
                ->where('lead_id', $lead->id)
                ->where('assigned_agent_id', $agent->id)
                ->orderBy('phase')
                ->orderBy('sequence_order')
                ->orderBy('created_at')
                ->get();
    
            // Group tasks by phase
            $tasksByPhase = $tasks->groupBy('phase');
    
            // Calculate statistics using proper enum filtering
            $statistics = [
                'total_tasks' => $tasks->count(),
                'pending_tasks' => $tasks->filter(fn($task) => $task->status->value === 'pending')->count(),
                'in_progress_tasks' => $tasks->filter(fn($task) => $task->status->value === 'in_progress')->count(),
                'completed_tasks' => $tasks->filter(fn($task) => in_array($task->status->value, ['completed', 'skipped']))->count(),
                'blocked_tasks' => $tasks->filter(fn($task) => $task->status->value === 'blocked')->count(),
                'overdue_tasks' => $tasks->filter(function($task) {
                    return $task->isOverdue() && !in_array($task->status->value, ['completed', 'skipped', 'cancelled']);
                })->count()
            ];
    
            $progressPercentage = $statistics['total_tasks'] > 0 
                ? round(($statistics['completed_tasks'] / $statistics['total_tasks']) * 100) 
                : 0;
    
            return view('pages.business.crm.agent_management.lead_tasks', compact(
                'agent',
                'lead',
                'tasks',
                'tasksByPhase',
                'statistics',
                'progressPercentage'
            ));
    
        } catch (\Exception $e) {
            Log::error('Error loading lead tasks', [
                'lead_id' => $encryptedLeadId,
                'error' => $e->getMessage()
            ]);
    
            return redirect()->back()
                ->with('error', 'Failed to load lead tasks');
        }
    }
}
