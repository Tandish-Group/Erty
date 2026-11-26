<?php

namespace App\Http\Controllers\Business\Properties;

use App\Models\Business;
use App\Models\Complex;
use App\Models\ComplexFloorPlan;
use App\Services\Access\BusinessPermissionService;
use App\Services\Properties\ComplexInventoryService;
use App\Services\ImageService;
use App\Services\StorageUsageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

/**
 * Property floor plans. Files live on the public disk at
 *   users/{business}/plans/complex/{slug}/{file}  (+ /thumbnail/{file})
 * and are reviewed in the "Floor plans & supporting records" setup step.
 */
class ComplexFloorPlanController extends Controller
{
    public function __construct(protected StorageUsageService $storageUsageService)
    {
        $this->middleware('auth');
    }

    private function allow(string $action): void
    {
        abort_unless(app(BusinessPermissionService::class)->can(auth()->user(), 'complex_management', $action), 403);
    }

    public static function directory(string $businessName, Complex $complex): string
    {
        return 'users/' . $businessName . '/plans/complex/' . $complex->slug;
    }

    private function businessName(): string
    {
        return (string) Business::where('user_id', business_owner_id())->value('business_name');
    }

    public function store(Request $request, ImageService $imageService)
    {
        $this->allow('edit');
        $complex = Complex::forBusiness()->find((int) $request->input('complex_id'));
        if (!$complex) {
            return response()->json(['message' => 'Choose one of your properties.'], 404);
        }

        $files = collect($request->allFiles())->flatten()->all();
        if (!$files) {
            return response()->json(['message' => 'Choose at least one floor plan image.'], 422);
        }
        foreach ($files as $file) {
            if (!$file->isValid() || !in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'], true) || $file->getSize() > 15 * 1024 * 1024) {
                return response()->json(['message' => 'Floor plans must be JPG, PNG or WebP images of up to 15 MB.'], 422);
            }
        }

        $business = $this->businessName();
        $directory = self::directory($business, $complex);
        $disk = Storage::disk('public');
        $written = [];
        $bytes = 0;

        try {
            $names = DB::transaction(function () use ($files, $complex, $business, $directory, $disk, $imageService, &$written, &$bytes) {
                $names = [];
                foreach ($files as $file) {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $fileName = \Illuminate\Support\Str::slug($name) . '_' . now()->format('YmdHis') . '_' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4)) . '.' . $extension;

                    $image = Image::make($file)->resize(1920, 1080, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })->encode($extension === 'jpg' ? 'jpeg' : $extension, 80);
                    $disk->put($directory . '/' . $fileName, (string) $image);
                    $written[] = $directory . '/' . $fileName;
                    $bytes += strlen((string) $image);

                    $imageService->createThumbnail($file, $fileName, 'complex', $complex->slug, $business);
                    $written[] = $directory . '/thumbnail/' . $fileName;

                    ComplexFloorPlan::create(['user_id' => business_owner_id(), 'complex_id' => $complex->id, 'plan' => $fileName]);
                    $names[] = $fileName;
                }
                app(ComplexInventoryService::class)->invalidate($complex, 'floor_plans', null, $names);
                return $names;
            });
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                $disk->delete($path);
            }
            Log::error('Floor plan upload failed', ['complex_id' => $complex->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'The floor plans could not be uploaded. Check the images and try again.'], 500);
        }

        $this->storageUsageService->incrementUsage($bytes, business_owner_id());

        return response()->json(['message' => 'Your floor plans have been uploaded. Review the documents setup step.', 'data' => $names, 'status' => 200]);
    }

    public function show($slug)
    {
        $this->allow('view');
        $Complex = Complex::forBusiness()->where('slug', $slug)->firstOrFail();
        $ComplexFloorPlans = ComplexFloorPlan::where('complex_id', $Complex->id)->orderBy('id')->get();
        $planDirectory = self::directory($this->businessName(), $Complex);

        return view('pages.business.complex.floor_plans.upload')->with(compact('ComplexFloorPlans', 'Complex', 'planDirectory'));
    }

    /** Delete several plans by file name (the gallery sends the checked names). */
    public function complex_bulk_delete(Request $request)
    {
        $this->allow('edit');
        $names = array_filter((array) $request->input('values', []), 'is_string');
        $plans = ComplexFloorPlan::whereIn('plan', $names)
            ->whereIn('complex_id', Complex::forBusiness()->select('id'))
            ->get();
        if ($plans->isEmpty()) {
            return response()->json(['message' => 'No matching floor plans were found.'], 404);
        }
        foreach ($plans->groupBy('complex_id') as $complexId => $group) {
            $this->deletePlans(Complex::findOrFail($complexId), $group);
        }

        return response()->json(['message' => 'Your floor plans have been deleted.', 'status' => 200]);
    }

    public function destroy($id)
    {
        $this->allow('edit');
        $plan = ComplexFloorPlan::whereIn('complex_id', Complex::forBusiness()->select('id'))->findOrFail((int) $id);
        $this->deletePlans(Complex::findOrFail($plan->complex_id), collect([$plan]));

        return redirect()->back()->with('success', 'Your floor plan has been deleted.');
    }

    private function deletePlans(Complex $complex, $plans): void
    {
        $disk = Storage::disk('public');
        $directory = self::directory($this->businessName(), $complex);
        $freed = 0;
        foreach ($plans as $plan) {
            foreach ([$directory . '/' . $plan->plan, $directory . '/thumbnail/' . $plan->plan] as $path) {
                if ($disk->exists($path)) {
                    $freed += $path === $directory . '/' . $plan->plan ? $disk->size($path) : 0;
                    $disk->delete($path);
                }
            }
            $plan->delete();
        }
        app(ComplexInventoryService::class)->invalidate($complex, 'floor_plans', $plans->pluck('plan')->all(), 'deleted');
        if ($freed > 0) {
            $this->storageUsageService->decrementUsage($freed, business_owner_id());
        }
    }
}
