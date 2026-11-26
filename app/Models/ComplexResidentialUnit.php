<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplexResidentialUnit extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;
    /** On properties a member reaches only through units, only these units' rows (PropertyAccess::unitScope). */
    protected array $unitColumns = ['residential' => 'id'];

    use HasFactory;

    protected $fillable = [
        'complex_id',
        'complex_unit_id',
        'unit_number',
        'floor',
        'unit_category',
        'bedrooms',
        'bathrooms',
        'sq_ft',
        'status',
        'assigned_agent_id',
        'house_type',
        'plot_number',
        'plot_size',
        'plot_unit',
        'title_number',
        'offer',
        'sale_price',
    ];

    protected $casts = [
        'sq_ft' => 'decimal:2',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'floor' => 'integer',
        'plot_size' => 'float',
        'sale_price' => 'float',
    ];

    public function isHouse(): bool
    {
        return $this->unit_category === 'house';
    }

    public function forSale(): bool
    {
        return $this->isHouse() && in_array($this->offer, ['sale', 'both'], true);
    }

    public function forRent(): bool
    {
        return !$this->isHouse() || in_array($this->offer ?: 'rent', ['rent', 'both'], true);
    }

    /** "House 12", "Unit A-101" (or the record id while unnumbered). */
    public function label(): string
    {
        return ($this->isHouse() ? 'House ' : 'Unit ') . ($this->unit_number ?: '#' . $this->id);
    }

    /** "4-bed Maisonette", "2 bedrooms · 2 bathrooms". */
    public function layoutLabel(): string
    {
        if ($this->isHouse()) {
            return trim(($this->bedrooms ? $this->bedrooms . '-bed ' : '') . ($this->house_type ?: 'House'));
        }
        return ($this->bedrooms ? $this->bedrooms . ' ' . \Illuminate\Support\Str::plural('bedroom', $this->bedrooms) : 'Studio') . ' · ' . $this->bathrooms . ' ' . \Illuminate\Support\Str::plural('bathroom', $this->bathrooms);
    }

    public function plotLabel(): ?string
    {
        if (!$this->plot_size) return null;
        $size = rtrim(rtrim(number_format((float) $this->plot_size, 3), '0'), '.');
        return $size . ' ' . (config('property.plot_units')[$this->plot_unit] ?? $this->plot_unit);
    }

    public function sales()
    {
        return $this->hasMany(PropertySale::class, 'residential_unit_id');
    }

    /** The sale in progress (reserved or agreed), if any. */
    public function liveSale()
    {
        return $this->hasOne(PropertySale::class, 'residential_unit_id')->whereIn('status', PropertySale::LIVE);
    }

    /**
     * Get the complex that owns the residential unit.
     */
    /** Agent working this unit (overrides the property's agent for tenancies here). */
    public function assignedAgent()
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function complex()
    {
        return $this->belongsTo(Complex::class);
    }

    /**
     * Get the unit specification/template.
     */
    public function complexUnit()
    {
        return $this->belongsTo(ComplexUnit::class);
    }

    /**
     * Get the tenant management record associated with this residential unit.
     */
    public function tenantManagement()
    {
        return $this->hasOne(TenantManagement::class, 'residential_unit_id');   
    }

    public function activeTenancies()
    {
        return $this->hasMany(TenantManagement::class, 'residential_unit_id')->where('status', 'Active');
    }
}
