<?php

namespace App\Http\Controllers\Business\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\BusinessRole;
use App\Services\Agents\AgentProfileService;
use App\Services\Access\TeamInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Linking an agent profile to a team member's sign-in, and inviting the agent to sign in. */
class AgentLoginController extends Controller
{
    /** Active team members without an agent profile, for linking. */
    public function linkCandidates(Request $request, string $encryptedId)
    {
        Agent::fromProfileToken($encryptedId);
        $term = mb_strtolower(trim((string) $request->query('q', '')));
        $taken = Agent::whereNotNull('user_id')->pluck('user_id');
        $members = \App\Models\User::with('businessRole')->where('is_sub_account', true)->where('main_account_user_id', business_owner_id())
            ->where(fn ($q) => $q->whereNull('is_suspended')->orWhere('is_suspended', false))
            ->whereNotIn('id', $taken)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw('LOWER(first_name) LIKE ?', ["%$term%"])->orWhereRaw('LOWER(last_name) LIKE ?', ["%$term%"])->orWhereRaw('LOWER(email) LIKE ?', ["%$term%"])))
            ->orderBy('first_name')->limit(20)->get();

        return response()->json(['results' => $members->map(fn ($m) => [
            'id' => $m->id,
            'text' => trim($m->first_name . ' ' . $m->last_name) . ' (' . $m->email . ')' . ($m->businessRole ? ' · ' . $m->businessRole->name : ''),
        ])->values()]);
    }
    /** Link this agent to an existing team member's sign-in. */
    public function linkLogin(Request $request, string $encryptedId, AgentProfileService $agents)
    {
        $agent = Agent::fromProfileToken($encryptedId);
        $data = $request->validate(['user_id' => 'required|integer']);
        $member = \App\Models\User::where('is_sub_account', true)->where('main_account_user_id', business_owner_id())->findOrFail($data['user_id']);
        $agents->link($member, $agent);

        return response()->json(['success' => true, 'message' => trim($member->first_name . ' ' . $member->last_name) . ' now signs in as this agent.']);
    }
    /** Remove the sign-in from this agent; the agent record and its history stay. */
    public function unlinkLogin(string $encryptedId, AgentProfileService $agents)
    {
        $agent = Agent::fromProfileToken($encryptedId);
        abort_if((int) $agent->user_id === (int) auth()->id(), 422, 'You cannot unlink your own agent profile.');
        $agents->unlink($agent);

        return response()->json(['success' => true, 'message' => 'The sign-in was unlinked. The agent stays as an external agent.']);
    }
    /** Invite this agent to sign in: creates a team member from the agent's details and links it. */
    public function inviteLogin(Request $request, string $encryptedId, TeamInvitationService $invitations, AgentProfileService $agents)
    {
        abort_unless(auth()->user()->hasBusinessPermission('subaccount_management', 'create'), 403, 'Your role does not allow you to invite team members.');
        $agent = Agent::fromProfileToken($encryptedId);
        abort_if($agent->user_id, 422, 'This agent already has a sign-in.');
        $data = $request->validate([
            'business_role_id' => ['required', 'integer', Rule::exists('business_roles', 'id')->where('business_id', business_owner_id())],
        ]);
        $email = $agent->agent_type === 'agency' ? ($agent->contact_person_email ?: $agent->email) : $agent->email;
        $role = BusinessRole::forBusiness(business_owner_id())->findOrFail($data['business_role_id']);

        $member = DB::transaction(function () use ($agent, $email, $role, $invitations, $agents) {
            $member = $invitations->invite([
                'first_name' => $agent->first_name,
                'last_name' => $agent->last_name,
                'email' => $email,
                'mobile' => $agent->phone ?: ($agent->contact_person_phone ?: '-'),
            ], $role);
            $agents->link($member, $agent);
            return $member;
        });

        return response()->json(['success' => true, 'message' => "Invitation sent to {$member->email}. They'll set a password and sign in as this agent."]);
    }
}
