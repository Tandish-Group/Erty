<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplexChange extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'complex_id',
        'field_name',
        'old_value',
        'new_value',
        'user_id',
        'user_ip'
    ];

    /**
     * Get the complex that owns the change.
     */
    public function complex()
    {
        return $this->belongsTo(Complex::class);
    }

    /**
     * Get the user that made the change.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}