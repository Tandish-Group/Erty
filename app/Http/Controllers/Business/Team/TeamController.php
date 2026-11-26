<?php

namespace App\Http\Controllers\Business\Team;

use App\Http\Controllers\Controller;
use App\Models\BusinessRole;
use App\Models\Complex;
use App\Models\User;
use App\Services\Agents\AgentProfileService;
use App\Services\Access\BusinessPermissionService;
use App\Services\Agents\PropertyAssignmentService;
use App\Services\Access\TeamInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Team & roles: staff (sub-accounts) of a business. Staff are invited by email and set their own
 * password through the standard reset link. Route access is enforced by business.access
 * (subaccount_management.*); this controller adds record scope and privilege-escalation guards.
 */
class TeamController extends Controller
{
    public function __construct(
        private BusinessPermissionService $permissions,
        private TeamInvitationService $invitations,
        private PropertyAssignmentService $assignments,
        private AgentProfileService $agents,
    ) {
    }

    private function member(int $id): User
    {
        $member = User::where('is_sub_account', true)->where('main_account_user_id', business_owner_id())->findOrFail($id);
        abort_if($member->id === auth()->id(), 422, 'You cannot change your own team access. Ask another administrator or the account owner.');
        return $member;
    }

    public function index()
    {
        $ownerId = business_owner_id();
        // Standard roles the business has not been offered yet (new businesses, or roles added to the standard set).
        app(\App\Services\Access\StarterRoles::class)->ensureFor($ownerId);
        $owner = User::with('business')->findOrFail($ownerId);
        $members = User::with(['businessRole', 'agentProfile'])
            ->withCount(['managedComplexes' => fn ($q) => $q->where('complexes.user_id', $ownerId)])
            ->where('is_sub_account', true)->where('main_account_user_id', $ownerId)
            ->orderBy('first_name')->orderBy('last_name')->get();
        $roles = BusinessRole::forBusiness($ownerId)->withCount('assignedUsers')->orderBy('name')->get();
        $catalogue = $this->permissions->visibleDefinitions();
        $actor = auth()->user();
        $can = [
            'invite' => $this->permissions->can($actor, 'subaccount_management', 'create'),
            'editMembers' => $this->permissions->can($actor, 'subaccount_management', 'edit'),
            'deleteMembers' => $this->permissions->can($actor, 'subaccount_management', 'delete'),
            'viewRoles' => $this->permissions->can($actor, 'role_management', 'view'),
            'createRoles' => $this->permissions->can($actor, 'role_management', 'create'),
            'editRoles' => $this->permissions->can($actor, 'role_management', 'edit'),
            'deleteRoles' => $this->permissions->can($actor, 'role_management', 'delete'),
            'assignProperties' => $this->permissions->can($actor, 'complex_management', 'assign'),
            'manageAgents' => $this->permissions->can($actor, 'agent_management', 'create') && $this->permissions->can($actor, 'agent_management', 'edit'),
        ];
        $templates = collect(config('business_role_templates', []))->map(fn ($t, $key) => ['key' => $key, 'name' => $t['display_name'] ?? $key])->values();
        $actorPermissions = $actor->isBusinessOwner() ? null : $this->permissions->permissionsFor($actor);
        $actorLimited = $actor->limitedToAssignedProperties();
        // Each member's assigned properties, limited to those the actor can see (Eloquent scope).
        // Properties are searched on demand (searchProperties), so portfolios of any size stay fast.
        $assigned = Complex::where('complexes.user_id', $ownerId)
            ->join('complex_user', 'complex_user.complex_id', '=', 'complexes.id')
            ->whereIn('complex_user.user_id', $members->pluck('id'))
            ->orderBy('complexes.title')
            ->get(['complexes.id', 'complexes.title', 'complex_user.user_id as member_id'])
            ->groupBy('member_id')
            ->map(fn ($rows) => $rows->map(fn ($c) => ['id' => (int) $c->id, 'title' => $c->title])->values());
        $hasProperties = Complex::forBusiness($ownerId)->exists();
        // Unlinked agents whose email matches a member: offered as the agent to link.
        $suggestedAgents = \App\Models\Agent::where('created_by', $ownerId)->whereNull('user_id')
            ->whereIn(DB::raw('lower(email)'), $members->pluck('email')->map(fn ($e) => mb_strtolower((string) $e)))
            ->get(['id', 'first_name', 'last_name', 'email'])->keyBy(fn ($a) => mb_strtolower($a->email));

        // What each limited member actually reaches: properties in full (assigned, managed as primary, or
        // company-managed) and units held as an agent elsewhere. Same rules as PropertyAccess.
        $titles = Complex::withoutGlobalScopes()->forBusiness($ownerId)->pluck('title', 'id');
        $reach = $members->filter(fn ($m) => $m->businessRole?->limitedToAssignedProperties())->mapWithKeys(function (User $member) use ($titles) {
            $access = new \App\Services\Access\PropertyAccess;
            $access->activate($member);
            $units = collect($access->unitScope())->map(fn ($u) => count($u['residential']) + count($u['commercial']));
            $full = collect($access->complexIds() ?? [])->reject(fn ($id) => $units->has($id));
            return [$member->id => [
                'full' => $full->map(fn ($id) => $titles[$id] ?? null)->filter()->sort()->values()->all(),
                'units' => $units->mapWithKeys(fn ($n, $id) => [$titles[$id] ?? ('#' . $id) => $n])->all(),
            ]];
        });
        $stats = [
            'people' => $members->count() + 1,
            'active' => 1 + $members->filter(fn ($m) => !$m->is_suspended && !($m->invited_at && !$m->invitation_accepted_at && !$m->last_login_at))->count(),
            'invited' => $members->filter(fn ($m) => !$m->is_suspended && $m->invited_at && !$m->invitation_accepted_at && !$m->last_login_at)->count(),
            'suspended' => $members->where('is_suspended', true)->count(),
            'agents' => $members->filter(fn ($m) => $m->agentProfile && $m->agentProfile->is_active)->count(),
            'limited' => $reach->count(),
        ];

        return view('pages.business.account.team.index', compact('owner', 'members', 'roles', 'catalogue', 'can', 'templates', 'actorPermissions', 'actorLimited', 'assigned', 'hasProperties', 'suggestedAgents', 'reach', 'stats'));
    }

    /** Property search for the "Properties they manage" picker (20 per page, Select2 format). */
    public function searchProperties(Request $request)
    {
        $term = mb_strtolower(trim((string) $request->query('q', '')));
        $page = max(1, (int) $request->query('page', 1));
        $query = Complex::forBusiness();
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                foreach (['title', 'ref_code', 'county', 'county_specific'] as $column) {
                    $q->orWhereRaw('LOWER(' . $column . ') LIKE ?', ['%' . $term . '%']);
                }
            });
        }
        $rows = $query->orderBy('title')->orderBy('id')->skip(($page - 1) * 20)->take(21)->get(['id', 'title', 'ref_code', 'county']);

        return response()->json([
            'results' => $rows->take(20)->map(fn ($c) => [
                'id' => $c->id,
                'text' => $c->title,
                'detail' => trim(implode(' · ', array_filter([$c->ref_code, $c->county]))),
            ])->values(),
            'pagination' => ['more' => $rows->count() > 20],
        ]);
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'mobile' => 'required|string|max:20',
            'business_role_id' => ['required', 'integer', Rule::exists('business_roles', 'id')->where('business_id', business_owner_id())],
            'custom_permissions' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'complex_ids' => 'nullable|array',
            'complex_ids.*' => 'integer',
            'agent_mode' => 'nullable|in:none,create,link',
            'agent_id' => 'nullable|required_if:agent_mode,link|integer',
        ], ['email.unique' => 'This email address already has an account.']);

        $role = BusinessRole::forBusiness(business_owner_id())->findOrFail($data['business_role_id']);
        $overrides = !empty($data['custom_permissions']) ? $this->permissions->normalize($data['permissions'] ?? []) : null;
        $this->guardExtras($data);

        $member = DB::transaction(function () use ($data, $role, $overrides) {
            $member = $this->invitations->invite($data, $role, $overrides);
            $this->applyExtras($member, $data);
            return $member;
        });

        return response()->json(['success' => true, 'message' => "Invitation sent to {$member->email}.", 'id' => $member->id]);
    }

    public function update(Request $request, int $id)
    {
        $member = $this->member($id);
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'mobile' => 'required|string|max:20',
            'business_role_id' => ['required', 'integer', Rule::exists('business_roles', 'id')->where('business_id', business_owner_id())],
            'custom_permissions' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'complex_ids' => 'nullable|array',
            'complex_ids.*' => 'integer',
            'agent_mode' => 'nullable|in:none,create,link',
            'agent_id' => 'nullable|required_if:agent_mode,link|integer',
        ]);

        $role = BusinessRole::forBusiness(business_owner_id())->findOrFail($data['business_role_id']);
        $overrides = !empty($data['custom_permissions'])
            ? $this->permissions->normalize($data['permissions'] ?? [], $member->business_permissions)
            : null;
        $this->invitations->guardGrant($role, $overrides);
        $this->guardExtras($data);

        DB::transaction(function () use ($member, $data, $role, $overrides) {
            $member->update([
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'mobile' => trim($data['mobile']),
                'business_role_id' => $role->id,
                'business_permissions' => $overrides,
            ]);
            $this->applyExtras($member, $data);
        });

        return response()->json(['success' => true, 'message' => 'Team member updated. Changes apply on their next page load.']);
    }

    /** Property assignments and the agent switch need their own permissions. */
    private function guardExtras(array $data): void
    {
        $actor = auth()->user();
        if (array_key_exists('complex_ids', $data) && $data['complex_ids'] !== null && !$this->permissions->can($actor, 'complex_management', 'assign')) {
            throw ValidationException::withMessages(['complex_ids' => 'Your role does not allow you to assign properties.']);
        }
        if (!empty($data['agent_mode'])
            && !($this->permissions->can($actor, 'agent_management', 'create') && $this->permissions->can($actor, 'agent_management', 'edit'))) {
            throw ValidationException::withMessages(['agent_mode' => 'Your role does not allow you to manage CRM agents.']);
        }
    }

    private function applyExtras(User $member, array $data): void
    {
        if (array_key_exists('complex_ids', $data) && $data['complex_ids'] !== null) {
            $this->assignments->setForMember($member, $data['complex_ids']);
        }
        match ($data['agent_mode'] ?? null) {
            'create' => $this->agents->ensureFor($member->fresh()),
            'link' => $this->agents->link($member->fresh(), \App\Models\Agent::where('created_by', business_owner_id())->findOrFail($data['agent_id'])),
            'none' => $this->agents->release($member),
            default => null,
        };
    }

    public function resendInvite(int $id)
    {
        $member = $this->member($id);
        abort_if($member->invitation_accepted_at, 422, 'This team member has already accepted their invitation.');
        $member->forceFill(['invited_at' => now(), 'invited_by' => auth()->id()])->save();
        $this->invitations->sendInvitation($member, $member->businessRole);

        return response()->json(['success' => true, 'message' => "A new invitation was sent to {$member->email}. Earlier links no longer work."]);
    }

    public function suspend(int $id)
    {
        $member = $this->member($id);
        $member->update(['is_suspended' => true]);
        // End their sessions on the next request (database sessions) and remember-me logins.
        $member->forceFill(['remember_token' => Str::random(60)])->save();

        return response()->json(['success' => true, 'message' => "{$member->first_name} has been suspended and will be signed out."]);
    }

    public function reactivate(int $id)
    {
        $member = $this->member($id);
        $member->update(['is_suspended' => false]);

        return response()->json(['success' => true, 'message' => "{$member->first_name} can sign in again."]);
    }

    /** Remove an invitation that was never accepted. Members who have signed in are suspended instead, keeping their history. */
    public function destroy(int $id)
    {
        $member = $this->member($id);
        if ($member->invitation_accepted_at || $member->last_login_at) {
            throw ValidationException::withMessages(['member' => 'This person has already used their account. Suspend them instead so their activity history is kept.']);
        }
        DB::table('password_resets')->where('email', $member->email)->delete();
        $member->delete();

        return response()->json(['success' => true, 'message' => 'Invitation removed.']);
    }
}
