<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageUpload extends Model
{
    /** A photo of a unit-type listing (type column). */
    public const LISTING_PHOTO = 'listing';

    protected $fillable = [
        'image',
        'user_id',
        'listing_id',
        'type',
    ];

    public function listing(){
        return $this->belongsTo('App\Models\Listing');
    }

    /** Public URL of the stored file (photos live on the "public" disk). */
    public function url(): string
    {
        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->image);
    }

}
