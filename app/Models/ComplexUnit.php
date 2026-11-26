<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplexUnit extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    use HasFactory;

    protected $fillable = [
        'complex_id',
        'unit_category',
        'unit_type',
        'bedrooms',
        'bathrooms',
        'unit_subdivision',
        'unit_count',
        'sq_ft',
        'storeys',
        'plot_size',
        'plot_unit',
        'offer',
        'sale_price',
        'features',
    ];

    protected $casts = [
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'unit_subdivision' => 'integer',
        'unit_count' => 'integer',
        'sq_ft' => 'decimal:2',
        'storeys' => 'integer',
        'plot_size' => 'float',
        'sale_price' => 'float',
        'features' => 'array',
    ];

    public function isHouse(): bool
    {
        return $this->unit_category === 'house';
    }

    /** "4-bed Maisonette", "2-bedroom apartment", "Studio". */
    public function layoutLabel(): string
    {
        if ($this->isHouse()) {
            return trim(($this->bedrooms ? $this->bedrooms . '-bed ' : '') . ($this->unit_type ?: 'House'));
        }
        return $this->bedrooms ? $this->bedrooms . '-bedroom apartment' : 'Studio';
    }

    public function complex()
    {
        return $this->belongsTo(Complex::class);
    }

    public function residentialUnits()
    {
        return $this->hasMany(ComplexResidentialUnit::class, 'complex_unit_id');
    }
}
