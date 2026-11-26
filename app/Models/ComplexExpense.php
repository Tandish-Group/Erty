<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplexExpense extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;
    /** On properties a member reaches only through units, only these units' rows (PropertyAccess::unitScope). */
    protected array $unitColumns = ['residential' => 'residential_unit_id', 'commercial' => 'commercial_unit_id'];

    protected $fillable = [
        'type',
        'description',
        'listing_type',
        'transaction_type',
        'currency',
        'amount',
        'expense_date',
        'user_id',
        'created_at',
        'updated_at',
        'complex_id',
        'scope',
        'residential_unit_id',
        'commercial_unit_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function expenseType()
    {
        return $this->belongsTo(ExpenseType::class, 'type');
    }

    public function residentialUnit()
    {
        return $this->belongsTo(ComplexResidentialUnit::class, 'residential_unit_id');
    }

    public function commercialUnit()
    {
        return $this->belongsTo(CommercialUnit::class, 'commercial_unit_id');
    }
}
