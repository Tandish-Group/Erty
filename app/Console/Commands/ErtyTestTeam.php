<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\BusinessRole;
use App\Models\Complex;
use App\Models\User;
use App\Services\Agents\AgentAssignmentService;
use App\Services\Access\StarterRoles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Development only: one sign-in test user for every starter role that has nobody on it, each with
 * assignments so there is something they should see and something they must not. Optionally sets one
 * password for every user of the business (asked for without echo; never stored in plain text).
 */
class ErtyTestTeam extends Command
{
    protected $signature = 'erty:test-team {--owner= : Business owner user id (default: the only business)} {--set-passwords : Ask for one password and set it for every user of the business, owner included}';

    protected $description = 'Create a test user per role with property assignments (local/testing only)';

    public function handle(AgentAssignmentService $assignments): int
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->error('erty:test-team only runs in local or testing environments.');
            return self::FAILURE;
        }
        $ownerId = (int) ($this->option('owner') ?: DB::table('businesses')->orderBy('id')->value('user_id'));
        $owner = User::find($ownerId);
        if (!$owner) {
            $this->error('No business owner found.');
            return self::FAILURE;
        }
        Auth::login($owner);
        app(StarterRoles::class)->ensureFor($ownerId);

        $properties = Complex::withoutGlobalScopes()->forBusiness($ownerId)->orderBy('id')->get();
        [$first, $second] = [$properties->get(0), $properties->get(1) ?? $properties->get(0)];

        $rows = [];
        foreach (BusinessRole::forBusiness($ownerId)->where('is_active', true)->orderBy('id')->get() as $role) {
            if (User::where('business_role_id', $role->id)->where('main_account_user_id', $ownerId)->exists()) {
                $rows[] = [$role->department, $role->name, User::where('business_role_id', $role->id)->where('main_account_user_id', $ownerId)->pluck('email')->implode(', '), 'existing'];
                continue;
            }
            $user = $this->createMember($role, $ownerId);
            $note = $this->assign($role, $user, $ownerId, $first, $second, $assignments);
            $rows[] = [$role->department, $role->name, $user->email, 'created' . ($note ? ' · ' . $note : '')];
        }

        if ($this->option('set-passwords')) {
            $password = (string) $this->secret('Password for every user of this business (owner included)');
            if (mb_strlen($password) < 8) {
                $this->error('Use at least 8 characters. Nothing changed.');
                return self::FAILURE;
            }
            $hash = Hash::make($password);
            $count = User::where(fn ($q) => $q->where('id', $ownerId)->orWhere('main_account_user_id', $ownerId))
                ->update(['password' => $hash, 'must_change_password' => false]);
            User::where(fn ($q) => $q->where('id', $ownerId)->orWhere('main_account_user_id', $ownerId))->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
            $this->info("Password set for {$count} users.");
        }

        $this->table(['Department', 'Role', 'User', 'Status'], $rows);
        return self::SUCCESS;
    }

    private function createMember(BusinessRole $role, int $ownerId): User
    {
        $slug = Str::slug($role->name, '.');
        $email = "test.{$slug}@erty.test";
        $user = User::whereRaw('lower(email) = ?', [$email])->first() ?? User::create([
            'first_name' => 'Test', 'last_name' => Str::limit($role->name, 40, ''),
            'full_name_slug' => Str::slug('test ' . $role->name . ' ' . Str::lower(Str::random(5))),
            'email' => $email, 'mobile' => '+2547000' . str_pad((string) $role->id, 5, '0', STR_PAD_LEFT), 'account_type' => 'Business',
            'password' => Hash::make(Str::random(40)), 'is_sub_account' => true, 'main_account_user_id' => $ownerId,
            'business_role_id' => $role->id, 'is_suspended' => false,
        ]);
        $user->forceFill(['business_role_id' => $role->id, 'main_account_user_id' => $ownerId, 'is_sub_account' => true, 'email_verified_at' => $user->email_verified_at ?? now(), 'invited_at' => $user->invited_at ?? now(), 'invited_by' => $ownerId])->save();
        if (!$user->hasRole('business')) {
            $user->assignRole('business');
        }
        return $user;
    }

    /** Give limited roles a property (or units) of their own, so the other property is "not theirs". */
    private function assign(BusinessRole $role, User $user, int $ownerId, ?Complex $first, ?Complex $second, AgentAssignmentService $assignments): ?string
    {
        if (!$role->limitedToAssignedProperties() || !$first) {
            return null;
        }
        if ($role->isAgentRole()) {
            // A field agent: an agent profile holding one free unit on the second property, and one lead there.
            $agent = Agent::where('user_id', $user->id)->first() ?? Agent::create([
                'created_by' => $ownerId, 'user_id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name,
                'email' => $user->email, 'phone' => $user->mobile, 'agent_type' => 'employee', 'status' => 'active', 'is_active' => true,
                'employment_type' => 'full_time', 'start_date' => now()->toDateString(),
            ]);
            $target = $second ?? $first;
            foreach (['commercial' => \App\Models\CommercialUnit::class, 'residential' => \App\Models\ComplexResidentialUnit::class] as $kind => $model) {
                $unit = $model::withoutGlobalScopes()->where('complex_id', $target->id)->whereNull('assigned_agent_id')->orderBy('id')->first();
                if ($unit) {
                    $assignments->assignUnits(User::find($ownerId), $target, $kind, [$unit->id], $agent->id);
                    return "agent; {$kind} unit {$unit->unit_number} at {$target->title}";
                }
            }
            return "agent at {$target->title} (no free unit)";
        }
        $property = in_array($role->name, ['Maintenance Coordinator'], true) ? ($second ?? $first) : $first;
        DB::table('complex_user')->updateOrInsert(['complex_id' => $property->id, 'user_id' => $user->id], ['assigned_by' => $ownerId, 'created_at' => now(), 'updated_at' => now()]);
        return "assigned {$property->title}";
    }
}
