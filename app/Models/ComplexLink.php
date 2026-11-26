<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplexLink extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    use HasFactory;

    protected $table = 'complex_links';

    protected $fillable = [
        'link',
        'complex_id',
    ];

    public function complex()
    {
        return $this->belongsTo(Complex::class);
    }

    
}
