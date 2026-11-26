<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A team member (or the owner) can also work as a CRM agent. The agent profile carries the CRM side
 * (commission, targets, assigned leads and tasks); the user carries the sign-in and permissions.
 */
class AgentProfileService
{
    /** Link or create the agent profile for this person and make sure it is active. */
    public function ensureFor(User $member): Agent
    {
        $ownerId = (int) $member->businessOwnerId();

        return DB::transaction(function () use ($member, $ownerId) {
            $agent = Agent::withTrashed()->where('user_id', $member->id)->first();

            if (!$agent) {
                // An existing agent of this business with the same email becomes this person's profile.
                $agent = Agent::where('created_by', $ownerId)->whereNull('user_id')
                    ->whereRaw('lower(email) = ?', [mb_strtolower((string) $member->email)])->first();
            }

            if (!$agent && Agent::withTrashed()->whereRaw('lower(email) = ?', [mb_strtolower((string) $member->email)])->exists()) {
                throw ValidationException::withMessages(['agent' => 'Another agent record already uses this email address. Link it from CRM → Agents instead.']);
            }

            if (!$agent) {
                $agent = new Agent([
                    'agent_type' => 'employee',
                    'first_name' => $member->first_name ?: 'Team',
                    'last_name' => $member->last_name ?: 'Member',
                    'email' => mb_strtolower((string) $member->email),
                    'phone' => $member->mobile ?: '-',
                    'employment_type' => 'full_time',
                    'start_date' => now()->toDateString(),
                    'status' => 'active',
                    'is_active' => true,
                ]);
                $agent->created_by = $ownerId;
            }

            if ($agent->trashed()) {
                $agent->restore();
            }
            $agent->user_id = $member->id;
            $agent->status = 'active';
            $agent->is_active = true;
            $agent->save();

            return $agent;
        });
    }

    /** Link an existing agent record of the business to a sign-in (owner or team member). */
    public function link(User $member, Agent $agent): Agent
    {
        $ownerId = (int) $member->businessOwnerId();
        if ((int) $agent->created_by !== $ownerId) {
            throw ValidationException::withMessages(['agent_id' => 'Choose an agent of this business.']);
        }
        if ($agent->user_id && (int) $agent->user_id !== (int) $member->id) {
            throw ValidationException::withMessages(['agent_id' => 'This agent is already linked to another sign-in. Unlink it first.']);
        }
        $current = Agent::where('user_id', $member->id)->first();
        if ($current && $current->id !== $agent->id) {
            throw ValidationException::withMessages(['agent_id' => 'This person already has an agent profile. Unlink it before linking another one.']);
        }
        $agent->forceFill(['user_id' => $member->id, 'status' => 'active', 'is_active' => true])->save();
        return $agent;
    }

    /** Remove the sign-in from an agent record; the agent stays as an external agent with its history. */
    public function unlink(Agent $agent): void
    {
        $agent->forceFill(['user_id' => null])->save();
    }

    /** An unlinked agent of the business with the person's email, offered as the likely match. */
    public static function suggestedFor(User $member): ?Agent
    {
        return Agent::where('created_by', $member->businessOwnerId())->whereNull('user_id')
            ->whereRaw('lower(email) = ?', [mb_strtolower((string) $member->email)])->first();
    }

    /** Stop working as an agent: the profile is kept (history, commissions) but becomes inactive. */
    public function release(User $member): void
    {
        $agent = Agent::where('user_id', $member->id)->first();
        if ($agent) {
            $agent->forceFill(['status' => 'inactive', 'is_active' => false])->save();
        }
    }

    /** The active agent profile of the signed-in person, if they work as an agent. */
    /**
     * The viewer's own agent profile when they may only see their own commission: a team member with an
     * agent profile and view-only agent access. Null for owners, agent editors and staff without an agent
     * profile (finance, viewers), who see the business's commission within their property access.
     */
    public static function ownCommissionOnly(?User $user = null): ?Agent
    {
        $user ??= auth()->user();
        if (!$user || $user->isBusinessOwner() || $user->hasBusinessPermission('agent_management', 'edit')) {
            return null;
        }
        return self::current($user);
    }

    /**
     * Whether the viewer may see this agent's commission (totals, statement, payouts): always their own;
     * someone else's only for people who see every property and are not limited to their own commission.
     */
    public static function canSeeCommissionOf(Agent $agent, ?User $user = null): bool
    {
        $user ??= auth()->user();
        if (!$user) {
            return false;
        }
        if ($user->isBusinessOwner()) {
            return true;
        }
        $own = self::current($user);
        if ($own && (int) $own->id === (int) $agent->id) {
            return true;
        }
        return !self::ownCommissionOnly($user) && !$user->limitedToAssignedProperties();
    }

    public static function current(?User $user = null): ?Agent
    {
        $user ??= auth()->user();
        if (!$user) {
            return null;
        }
        return Agent::where('user_id', $user->id)->where('created_by', $user->businessOwnerId())->first();
    }
}
