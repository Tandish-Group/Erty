<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Access\ModuleService;
use App\Services\Access\PropertyAccess;
use App\Services\Access\StarterRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Platform administration (users with the "admin" role) for this single-business installation:
 * everyone who works in the business with their role, property access and sign-in state; restoring
 * someone who is locked out; the roles at a glance (adding missing standard roles); and module switches.
 * Inviting and editing staff stays on Team & roles.
 */
class PlatformController extends Controller
{
    public function __construct(private ModuleService $modules) {}

    public function index(Request $request)
    {
        $ownerId = auth()->user()->businessOwnerId();
        $business = DB::table('businesses')->where('user_id', $ownerId)->first();
        abort_unless($business, 404);

        $search = trim((string) $request->query('q', ''));
        $roleFilter = $request->integer('role') ?: null;
        $statusFilter = in_array($request->query('status'), ['active', 'locked', 'invited'], true) ? $request->query('status') : null;

        $everyone = User::where('id', $ownerId)
            ->orWhere(fn ($q) => $q->where('is_sub_account', true)->where('main_account_user_id', $ownerId))
            ->with(['businessRole:id,name,color,property_scope', 'agentProfile:id,user_id,is_active', 'roles:id,name'])
            ->withCount(['managedComplexes' => fn ($q) => $q->where('complexes.user_id', $ownerId)])
            ->orderByRaw('is_sub_account')->orderBy('first_name')->orderBy('last_name')
            ->get();

        $statusOf = fn (User $u) => $u->is_suspended ? 'locked'
            : ($u->is_sub_account && !$u->invitation_accepted_at && !$u->last_login_at && $u->invited_at ? 'invited' : 'active');

        $people = $everyone
            ->when($search !== '', fn ($c) => $c->filter(fn ($u) => str_contains(mb_strtolower($u->first_name . ' ' . $u->last_name . ' ' . $u->email), mb_strtolower($search))))
            ->when($roleFilter, fn ($c) => $c->filter(fn ($u) => (int) $u->business_role_id === $roleFilter))
            ->when($statusFilter, fn ($c) => $c->filter(fn ($u) => $statusOf($u) === $statusFilter))
            ->values();

        $counts = [
            'staff' => $everyone->where('is_sub_account', true)->count(),
            'locked' => $everyone->filter(fn ($u) => $statusOf($u) === 'locked')->count(),
            'invited' => $everyone->filter(fn ($u) => $statusOf($u) === 'invited')->count(),
            'agents' => $everyone->filter(fn ($u) => (bool) $u->agentProfile?->is_active)->count(),
        ];

        $roles = DB::table('business_roles')->where('business_id', $ownerId)
            ->leftJoin('users', fn ($j) => $j->on('users.business_role_id', '=', 'business_roles.id')->where('users.is_sub_account', true)->where('users.main_account_user_id', $ownerId))
            ->groupBy('business_roles.id', 'business_roles.name', 'business_roles.color', 'business_roles.property_scope', 'business_roles.is_active')
            ->orderBy('business_roles.name')
            ->get(['business_roles.id', 'business_roles.name', 'business_roles.color', 'business_roles.property_scope', 'business_roles.is_active', DB::raw('count(users.id) as members')]);
        $offered = json_decode((string) $business->starter_roles_added, true) ?: [];
        $missingStarterRoles = array_values(array_diff(array_keys(StarterRoles::definitions()), $offered));

        $modules = collect(ModuleService::MODULES)->map(fn ($m, $key) => $m + ['key' => $key, 'enabled' => $this->modules->enabled($key, auth()->user())]);

        $glance = app(PropertyAccess::class)->unrestricted(function () use ($ownerId) {
            $complexes = DB::table('complexes')->where('user_id', $ownerId)->select('id');
            return [
                'properties' => DB::table('complexes')->where('user_id', $ownerId)->count(),
                'units' => DB::table('complex_residential_units')->whereIn('complex_id', $complexes)->count()
                    + DB::table('commercial_units')->whereIn('complex_id', $complexes)->count(),
                'tenancies' => DB::table('tenant_management')->where('managed_by', $ownerId)->where('status', 'Active')->count(),
                'listings' => DB::table('listings')->where('user_id', $ownerId)->where('status', 'Published')->count(),
            ];
        });

        return view('pages.platform.index', compact('business', 'people', 'counts', 'roles', 'missingStarterRoles', 'modules', 'glance',
            'search', 'roleFilter', 'statusFilter', 'statusOf', 'ownerId') + ['filtered' => $search !== '' || $roleFilter || $statusFilter]);
    }

    /** Switch a module on or off. With one business, the installation setting is the business's setting. */
    public function module(Request $request)
    {
        $data = $request->validate([
            'module' => ['required', Rule::in(array_keys(ModuleService::MODULES))],
            'enabled' => ['required', 'boolean'],
        ]);
        $this->modules->setInstallation($data['module'], (bool) $data['enabled']);
        $this->modules->setForBusiness(auth()->user()->businessOwnerId(), $data['module'], null);

        return back()->with('success', ModuleService::MODULES[$data['module']]['label'] . ' is now ' . ($data['enabled'] ? 'on.' : 'off.'));
    }

    /** Let someone locked out (wrong passwords or codes, or suspended) sign in again. */
    public function restorePerson(int $user)
    {
        $ownerId = auth()->user()->businessOwnerId();
        $person = User::where('id', $user)->where(fn ($q) => $q->where('id', $ownerId)->orWhere('main_account_user_id', $ownerId))->firstOrFail();
        $person->forceFill(['is_suspended' => false, 'incorrect_attempts' => 0])->save();

        return back()->with('success', (trim($person->first_name . ' ' . $person->last_name) ?: $person->email) . ' can sign in again.');
    }

    /**
     * Let a team member answer support tickets (the "support" sign-in role), or stop them. They then open
     * the support inbox and hear about new tickets, without the rest of platform administration.
     */
    public function supportAgent(Request $request, int $user)
    {
        $ownerId = auth()->user()->businessOwnerId();
        $person = User::where('id', $user)->where(fn ($q) => $q->where('id', $ownerId)->orWhere('main_account_user_id', $ownerId))->firstOrFail();
        $name = trim($person->first_name . ' ' . $person->last_name) ?: $person->email;
        if ($person->isPlatformAdmin()) {
            return back()->with('success', $name . ' is a platform administrator and already answers support tickets.');
        }

        $enable = $request->boolean('enabled');
        $enable ? $person->assignRole(\Spatie\Permission\Models\Role::findOrCreate('support', 'web')) : $person->removeRole('support');
        if (!$enable) {
            \App\Models\SupportTicket::where('assigned_to', $person->id)->update(['assigned_to' => null]);
        }

        return back()->with('success', $enable ? $name . ' can now answer support tickets.' : $name . ' no longer answers support tickets; their tickets are unassigned.');
    }

    public function seedRoles(StarterRoles $roles)
    {
        $created = $roles->ensureFor(auth()->user()->businessOwnerId());

        return back()->with('success', $created ? "{$created} standard role" . ($created === 1 ? '' : 's') . ' added.' : 'Every standard role is already there.');
    }
}
