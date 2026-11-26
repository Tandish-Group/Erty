<?php

namespace App\Services\Access;

use App\Mail\StaffInvitationMail;
use App\Models\BusinessRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates team members (sub-accounts) and sends their invitation link. Used by Account → Team & roles
 * and by CRM → Agents ("invite as team member"). Invited people set their own password through the
 * standard reset link.
 */
class TeamInvitationService
{
    public function __construct(private BusinessPermissionService $permissions)
    {
    }

    /**
     * @param array{first_name:string,last_name:string,email:string,mobile:string} $person
     */
    public function invite(array $person, BusinessRole $role, ?array $overrides = null): User
    {
        $ownerId = (int) business_owner_id();
        abort_unless((int) $role->business_id === $ownerId, 404);
        if (!$role->is_active) {
            throw ValidationException::withMessages(['business_role_id' => 'Choose an active role.']);
        }
        $email = mb_strtolower(trim($person['email']));
        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email address already has an account.']);
        }
        $this->guardGrant($role, $overrides);

        $member = DB::transaction(function () use ($person, $email, $role, $overrides, $ownerId) {
            $member = User::create([
                'first_name' => trim($person['first_name']),
                'last_name' => trim($person['last_name']),
                'full_name_slug' => Str::slug($person['first_name'] . ' ' . $person['last_name'] . ' ' . Str::lower(Str::random(5))),
                'email' => $email,
                'mobile' => trim($person['mobile']),
                'account_type' => 'Business',
                'password' => Hash::make(Str::random(40)), // unusable until the invitation is accepted
                'is_sub_account' => true,
                'main_account_user_id' => $ownerId,
                'business_role_id' => $role->id,
                'business_permissions' => $overrides,
                'is_suspended' => false,
            ]);
            $member->forceFill(['invited_at' => now(), 'invited_by' => auth()->id()])->save();
            $member->assignRole('business');
            return $member;
        });

        $this->sendInvitation($member, $role);
        return $member;
    }

    public function sendInvitation(User $member, ?BusinessRole $role): void
    {
        $token = Password::broker()->createToken($member);
        $url = url(route('password.reset', ['token' => $token, 'email' => $member->email], false));
        $owner = User::with('business')->find($member->businessOwnerId());
        $actor = auth()->user();

        Mail::to($member->email)->queue(new StaffInvitationMail(
            $member,
            $owner?->business?->business_name ?? config('app.name'),
            $role?->name ?? 'Team member',
            trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) ?: 'Your administrator',
            $url,
            (int) config('auth.passwords.users.expire', 60),
        ));
    }

    /**
     * Team members may only hand out what they hold themselves: the permissions, and the property
     * reach (someone limited to assigned properties cannot give out an all-properties role).
     */
    public function guardGrant(BusinessRole|array $role, ?array $overrides): void
    {
        $actor = auth()->user();
        if (!$actor || $actor->isBusinessOwner()) {
            return;
        }
        $rolePermissions = $role instanceof BusinessRole ? ($role->permissions ?? []) : $role;
        $effective = $overrides ?? $rolePermissions;
        if (!$this->permissions->isSubset($effective, $this->permissions->permissionsFor($actor))) {
            throw ValidationException::withMessages(['permissions' => 'You can only give a team member permissions that you have yourself.']);
        }
        if ($role instanceof BusinessRole) {
            $this->guardScope($role->property_scope ?? BusinessRole::SCOPE_ALL);
        }
    }

    public function guardScope(?string $scope): void
    {
        $actor = auth()->user();
        if ($actor && $actor->limitedToAssignedProperties() && ($scope ?? BusinessRole::SCOPE_ALL) !== BusinessRole::SCOPE_ASSIGNED) {
            throw ValidationException::withMessages(['property_scope' => 'You only work with properties assigned to you, so you can only give out roles limited to assigned properties.']);
        }
    }
}
