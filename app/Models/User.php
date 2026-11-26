<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'last_name', 'full_name_slug', 'mobile', 'email', 'account_type', 'password', 'avatar', 'cover_photo', 'incorrect_attempts',
        'is_suspended','verification_status','verification_message','last_login_at','walkthrough_completed','walkthrough_completed_at',
        'is_sub_account','main_account_user_id','user_role','business_role_id','business_permissions',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_sub_account' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'walkthrough_completed' => 'boolean',
        'walkthrough_completed_at' => 'datetime',
        'password' => 'hashed',
        'business_permissions' => 'array',
        'is_suspended' => 'boolean',
        'must_change_password' => 'boolean',
        'invited_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
    ];

    public function listings()
    {
        return $this->hasMany(Listing::class);
    }

    public function complexes()
    {
        return $this->hasMany(Complex::class);
    }

    /**
     * The business record this user works for. Staff (sub-accounts) resolve to their main
     * account's business, so storage paths, names and settings are shared across the team.
     */
    public function business()
    {
        return $this->hasOne(Business::class, 'user_id', 'business_owner_key');
    }

    /** Owner user id used as the local key of business(): own id for owners, main account for staff. */
    public function getBusinessOwnerKeyAttribute(): ?int
    {
        return $this->businessOwnerId();
    }

    public function businessOwnerId(): ?int
    {
        if ($this->is_sub_account && $this->main_account_user_id) {
            return (int) $this->main_account_user_id;
        }
        return $this->id ? (int) $this->id : null;
    }

    public function isBusinessOwner(): bool
    {
        return !$this->is_sub_account;
    }

    /** A platform administrator (Spatie role "admin"): opens the Platform area (staff, roles, modules). */
    public function isPlatformAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Answers support tickets in the support inbox: platform administrators, and people a platform
     * administrator has made support agents (Spatie role "support").
     */
    public function answersSupport(): bool
    {
        return !$this->is_suspended && $this->hasAnyRole(['admin', 'support']);
    }

    public function businessOwner(): self
    {
        return $this->isBusinessOwner() ? $this : (self::find($this->businessOwnerId()) ?? $this);
    }

    public function serviceCharges()
    {
        return $this->hasMany(ServiceCharge::class);
    }

    public function mainAccount()
    {
        return $this->belongsTo(User::class, 'main_account_user_id');
    }

    public function businessRole()
    {
        return $this->belongsTo(BusinessRole::class, 'business_role_id');
    }

    /** Public URL of the profile photo (stored under the business folder), or null. */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }
        $folder = $this->business?->business_name ?? 'business';
        return '/storage/users/' . rawurlencode($folder) . '/avatars/' . rawurlencode($this->avatar);
    }

    /** Properties this team member has been assigned to manage. */
    public function managedComplexes()
    {
        return $this->belongsToMany(Complex::class, 'complex_user')->withPivot('assigned_by')->withTimestamps();
    }

    /** The CRM agent profile this sign-in works as (owners and staff may have one). */
    public function agentProfile()
    {
        return $this->hasOne(Agent::class, 'user_id');
    }

    /** True when this team member only sees the properties assigned to them (set on their role). */
    public function limitedToAssignedProperties(): bool
    {
        return !$this->isBusinessOwner() && (bool) $this->businessRole?->limitedToAssignedProperties();
    }

    public function hasBusinessPermission(string $group, string $action = 'view'): bool
    {
        return app(\App\Services\Access\BusinessPermissionService::class)
            ->can($this, $group, $action);
    }
}
