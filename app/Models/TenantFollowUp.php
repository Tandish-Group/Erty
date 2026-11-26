<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A recorded follow-up with a tenant about their account (nothing is sent from Erty). */
class TenantFollowUp extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    protected string $tenancyColumn = 'tenancy_id';

    protected $fillable = ['tenancy_id', 'user_id', 'method', 'note', 'contacted_on', 'promise_date', 'promise_amount', 'promise_status'];

    protected $casts = ['contacted_on' => 'date', 'promise_date' => 'date', 'promise_amount' => 'decimal:2'];

    public const METHODS = ['call' => 'Phone call', 'sms' => 'SMS', 'email' => 'Email', 'visit' => 'Visit', 'other' => 'Other'];

    public function tenancy(): BelongsTo { return $this->belongsTo(TenantManagement::class, 'tenancy_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
