<?php
namespace App\Services;

use Intervention\Image\Facades\Image;

class ImageService
{
    public function createThumbnail($data, $fileNameToStore, $type, $slug, $business_name)
    {
        $img = Image::make($data)->resize(500, 500, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        // Write through the public disk so the directory is created and the file is served via /storage.
        \Illuminate\Support\Facades\Storage::disk('public')->put(
            'users/'.$business_name.'/plans/'.$type.'/'.$slug.'/thumbnail/'.$fileNameToStore,
            (string) $img->encode()
        );
    }

    
}