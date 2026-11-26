<?php

namespace App\Http\Controllers\Business\Account;

use App\Http\Controllers\Controller;
use App\Models\Complex;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Services\Agents\AgentProfileService;
use App\Services\Access\BusinessPermissionService;
use App\Services\Access\PropertyAccess;
use App\Services\StorageUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The signed-in person's own page: name, photo, phone, sign-in details, what they can access,
 * and their CRM agent profile. Business-wide settings live on /business_account.
 */
class ProfileController extends Controller
{
    public function show(BusinessPermissionService $permissions, PropertyAccess $access)
    {
        $user = auth()->user()->load('businessRole');
        $business = $user->business;
        $ownerId = $user->businessOwnerId();

        $managed = $access->unrestricted(fn () => Complex::forBusiness($ownerId)
            ->whereIn('id', $user->managedComplexes()->pluck('complexes.id'))->orderBy('title')->get(['id', 'title', 'slug']));

        $held = $permissions->permissionsFor($user);
        // What the person can do, under the same headings as the left menu, with each action's explanation.
        $catalogue = app(\App\Services\Access\PermissionCatalogue::class);
        $sections = collect($catalogue->sections($user))
            ->flatMap(fn ($section) => collect($section['groups'])->map(fn ($group) => [
                'label' => $group['label'],
                'actions' => collect($group['actions'])
                    ->filter(fn ($a) => $user->isBusinessOwner() || in_array($a['key'], $held[$group['group']] ?? [], true))
                    ->values()->all(),
            ]))
            ->filter(fn ($s) => $s['actions'])->values();
        $menu = $catalogue->menuFor($user->isBusinessOwner() ? [] : $held, $user->isBusinessOwner());

        $agent = AgentProfileService::current($user);
        $agentStats = null;
        if ($agent) {
            $agentStats = [
                'open_leads' => Lead::where('assigned_agent_id', $agent->id)->whereNotIn('status', ['converted', 'lost', 'closed'])->count(),
                'open_tasks' => LeadTask::where('assigned_agent_id', $agent->id)->whereIn('status', ['pending', 'in_progress', 'blocked'])->count(),
            ];
        }
        $canCreateAgent = !$agent && $permissions->can($user, 'agent_management', 'create');

        $canManageTeam = $permissions->can($user, 'subaccount_management', 'view') || $permissions->can($user, 'role_management', 'view');
        $canBusinessSettings = $permissions->can($user, 'account_settings', 'view');
        $isPlatformAdmin = $user->isPlatformAdmin();

        return view('pages.business.profile.index', compact('user', 'business', 'managed', 'sections', 'menu', 'agent', 'agentStats', 'canCreateAgent',
            'canManageTeam', 'canBusinessSettings', 'isPlatformAdmin'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'mobile' => 'required|string|max:20',
            'avatar' => 'nullable|image|mimes:png,jpg,jpeg|max:4096',
            'avatar_remove' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $folder = 'users/' . ($user->business->business_name ?? 'business') . '/avatars';
        $avatar = $user->avatar;

        if ($request->hasFile('avatar') || $request->boolean('avatar_remove')) {
            if ($avatar && Storage::exists("$folder/$avatar")) {
                $size = Storage::size("$folder/$avatar");
                Storage::delete("$folder/$avatar");
                app(StorageUsageService::class)->decrementUsage($size);
            }
            $avatar = null;
        }
        if ($file = $request->file('avatar')) {
            $avatar = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '_' . time() . '.' . $file->getClientOriginalExtension();
            Storage::putFileAs($folder, $file, $avatar);
            app(StorageUsageService::class)->incrementUsage($file->getSize());
        }

        $user->update([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'mobile' => trim($data['mobile']),
            'avatar' => $avatar,
        ]);
        // Keep the CRM agent contact details in step with the person's profile.
        AgentProfileService::current($user)?->update(['first_name' => $user->first_name, 'last_name' => $user->last_name, 'phone' => $user->mobile]);

        return redirect()->route('business.profile.show')->with('success', 'Your profile was updated.');
    }

    /** Owners (and staff allowed to add agents) can start working as a CRM agent themselves. */
    public function createAgentProfile(AgentProfileService $agents)
    {
        $agents->ensureFor(auth()->user());
        return redirect()->route('business.profile.show')->with('success', 'You now have a CRM agent profile. Leads and tasks can be assigned to you.');
    }
}
