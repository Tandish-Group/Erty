<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplexHistory extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;
    /** On properties a member reaches only through units, only these units' rows (PropertyAccess::unitScope). */
    protected array $unitColumns = ['residential' => 'complex_residential_unit_id', 'commercial' => 'commercial_unit_id'];

    use HasFactory;

    protected $fillable = [
        'complex_id',
        'complex_residential_unit_id',
        'commercial_unit_id',
        'history_category_id',
        'category_tag',
        'data',
        'files',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
        'files' => 'array',
    ];

    public function complex()
    {
        return $this->belongsTo(Complex::class);
    }

    public function residentialUnit()
    {
        return $this->belongsTo(ComplexResidentialUnit::class, 'complex_residential_unit_id');
    }

    public function commercialUnit()
    {
        return $this->belongsTo(CommercialUnit::class, 'commercial_unit_id');
    }

    public function category()
    {
        return $this->belongsTo(HistoryCategory::class, 'history_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the unit display name (residential or commercial)
     */
    public function getUnitDisplayAttribute()
    {
        if ($this->residentialUnit) {
            return $this->residentialUnit->unit_number ?? 'Residential Unit #' . $this->complex_residential_unit_id;
        }
        
        if ($this->commercialUnit) {
            return $this->commercialUnit->unit_number ?? 'Commercial Unit #' . $this->commercial_unit_id;
        }
        
        return 'Complex-wide';
    }

    /**
     * Get the unit type (residential, commercial, or general)
     */
    public function getUnitTypeAttribute()
    {
        if ($this->complex_residential_unit_id) {
            return 'residential';
        }
        
        if ($this->commercial_unit_id) {
            return 'commercial';
        }
        
        return 'general';
    }
}
