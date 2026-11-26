<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantTenancyBilling extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenancy_id';

    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_tenancy_billings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenancy_id',
        'tenancy_ref',
        'currency',
        'billing_date',
        'billing_period_start',
        'billing_period_end',
        'billing_period_display',
        'tenancy_payment_frequency',
        'payment_due_date',
        'grace_period',
        'tenancy_billing_amount',
        'status',
        'rent_invoice_dispatched',
        'rent_invoice_dispatched_at',
        'invoice_number',
        'paid_amount',
        'paid_date',
        'payment_method',
        'payment_reference',
        'system_notes',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'billing_date' => 'date',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'payment_due_date' => 'date',
        'paid_date' => 'date',
        'rent_invoice_dispatched' => 'boolean',
        'rent_invoice_dispatched_at' => 'datetime',
        'tenancy_billing_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'grace_period' => 'integer',
    ];

    /**
     * Relationship to tenant management
     */
    public function tenant()
    {
        return $this->belongsTo(TenantManagement::class, 'tenancy_id');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'Paid');
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', ['System_Overdue_Recent', 'System_Overdue_Extended']);
    }

    /**
     * Accessors
     */
    public function getIsFullyPaidAttribute()
    {
        return $this->paid_amount >= $this->tenancy_billing_amount;
    }

    public function getIsOverdueAttribute()
    {
        return $this->payment_due_date < now() && !$this->is_fully_paid;
    }

    /**
     * Mutators
     */
    public function setTenancyBillingAmountAttribute($value)
    {
        $this->attributes['tenancy_billing_amount'] = round($value, 2);
    }

    public function setPaidAmountAttribute($value)
    {
        $this->attributes['paid_amount'] = round($value, 2);
    }
}