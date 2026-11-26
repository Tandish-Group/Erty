<?php

namespace App\Http\Controllers\Business\Properties;

use App\Models\Complex;
use App\Models\ServiceCharge;
use App\Models\ComplexAmenity;
use App\Services\Access\BusinessPermissionService;
use App\Services\Properties\ComplexInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Recurring service charges billed to tenants for a property's shared facilities
 * (parking, security, water …). Reviewed in the "Service charges" setup step.
 */
class ServiceChargeController extends Controller
{
    public const INTERVALS = ['Monthly', 'Quarterly', 'Semi-annually', 'Annually'];

    private function allow(string $action): void
    {
        abort_unless(app(BusinessPermissionService::class)->can(auth()->user(), 'service_charge_management', $action)
            || app(BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'edit'), 403);
    }

    private function scopedCharges()
    {
        return ServiceCharge::where('service_charges.amenity_type', 'complex_amenities')
            ->whereIn('service_charges.complex_id', Complex::forBusiness()->select('id'));
    }

    public function serviceCharges(Request $request)
    {
        $this->allow('view');
        $complexes = Complex::forBusiness()->orderBy('title')->get(['id', 'slug', 'title', 'county', 'county_specific']);
        $selected = $request->integer('complex_id') ?: null;

        $serviceCharges = $this->scopedCharges()
            ->select(['service_charges.*', 'complex_amenities.amenity_type as facility_type', 'complexes.title as complex_title', 'complexes.slug as complex_slug', 'complexes.county', 'complexes.county_specific'])
            ->join('complex_amenities', 'service_charges.amenity_id', '=', 'complex_amenities.id')
            ->join('complexes', 'service_charges.complex_id', '=', 'complexes.id')
            ->when($selected, fn ($q) => $q->where('service_charges.complex_id', $selected))
            ->orderBy('complexes.title')->orderBy('service_charges.id')
            ->get();

        $groupedCharges = $serviceCharges->groupBy('complex_title');
        $facilityLabels = collect(config('property_facilities'))->map(fn ($f) => $f['label'])->all();
        $intervals = self::INTERVALS;
        $canEdit = app(BusinessPermissionService::class)->can(auth()->user(), 'service_charge_management', 'create')
            || app(BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'edit');

        return view('pages.business.complex.service_charges', compact('groupedCharges', 'complexes', 'serviceCharges', 'selected', 'facilityLabels', 'intervals', 'canEdit'));
    }

    private function validated(Request $request, ?ServiceCharge $existing = null): array
    {
        $data = $request->validate([
            'complex_id' => ['required', 'integer'],
            'amenity_id' => ['required', 'integer'],
            'payment_interval' => ['required', Rule::in(self::INTERVALS)],
            'charge' => ['required', 'integer', 'min:1', 'max:100000000'],
        ], [], ['amenity_id' => 'facility', 'charge' => 'amount']);

        $complex = Complex::forBusiness()->find($data['complex_id']);
        if (!$complex) {
            throw ValidationException::withMessages(['complex_id' => 'Choose one of your properties.']);
        }
        $facility = ComplexAmenity::where('complex_id', $complex->id)->find($data['amenity_id']);
        if (!$facility) {
            throw ValidationException::withMessages(['amenity_id' => 'Choose a facility recorded for this property.']);
        }
        $duplicate = ServiceCharge::where('amenity_type', 'complex_amenities')->where('amenity_id', $facility->id)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['amenity_id' => 'This facility already has a service charge. Edit the existing charge instead.']);
        }

        return [$complex, $facility, $data];
    }

    public function store(Request $request)
    {
        $this->allow('create');
        [$complex, $facility, $data] = $this->validated($request);

        $charge = DB::transaction(function () use ($complex, $facility, $data) {
            $charge = ServiceCharge::create([
                'user_id' => business_owner_id(), 'complex_id' => $complex->id, 'amenity_type' => 'complex_amenities',
                'amenity_id' => $facility->id, 'name' => $data['payment_interval'], 'charge' => $data['charge'],
            ]);
            app(ComplexInventoryService::class)->invalidate($complex, 'service_charge:' . $charge->id, null, ['facility' => $facility->amenity_type, 'interval' => $data['payment_interval'], 'charge' => $data['charge']]);
            return $charge;
        });

        return response()->json(['success' => true, 'status' => 200, 'id' => $charge->id, 'message' => 'Service charge added. Review the service charges setup step.']);
    }

    public function update(Request $request, $id)
    {
        $this->allow('edit');
        $charge = $this->scopedCharges()->findOrFail((int) $id);
        [$complex, $facility, $data] = $this->validated($request, $charge);

        DB::transaction(function () use ($charge, $complex, $facility, $data) {
            $before = $charge->only(['complex_id', 'amenity_id', 'name', 'charge']);
            $charge->update(['complex_id' => $complex->id, 'amenity_id' => $facility->id, 'name' => $data['payment_interval'], 'charge' => $data['charge']]);
            if ($charge->wasChanged()) {
                app(ComplexInventoryService::class)->invalidate($complex, 'service_charge:' . $charge->id, $before, $charge->only(['complex_id', 'amenity_id', 'name', 'charge']));
            }
        });

        return response()->json(['success' => true, 'status' => 200, 'message' => 'Service charge updated. Review the service charges setup step.']);
    }

    public function destroy($id)
    {
        return $this->deleteServiceCharge($id);
    }

    public function deleteServiceCharge($id)
    {
        $this->allow('delete');
        $charge = $this->scopedCharges()->find((int) $id);
        if (!$charge) {
            return response()->json(['success' => false, 'message' => 'Service charge not found.'], 404);
        }

        DB::transaction(function () use ($charge) {
            $complex = Complex::findOrFail($charge->complex_id);
            $before = $charge->only(['amenity_id', 'name', 'charge']);
            $charge->delete();
            app(ComplexInventoryService::class)->invalidate($complex, 'service_charge:' . $charge->id, $before, 'deleted');
        });

        return response()->json(['success' => true, 'message' => 'Service charge deleted.']);
    }

    /** Template for the service charge import, generated so it always matches the importer. */
    public function downloadServiceChargesSample()
    {
        $path = app(ComplexInventoryService::class)->template(
            ['complex_reference', 'amenity_type', 'payment_interval', 'charge_amount'],
            ['RVS-001', 'parking', 'Monthly', 2500],
            [
                'One row per facility charge. complex_reference must match one of your properties.',
                'amenity_type: the facility key, e.g. ' . implode(', ', array_keys(config('property_facilities'))) . '. The facility must already be recorded on the property.',
                'payment_interval: ' . implode(', ', self::INTERVALS) . '. charge_amount: whole number in KES.',
                'A facility with an existing charge is updated. Nothing is imported unless every row is valid.',
            ]
        );
        return response()->download($path, 'erty-service-charges-template.xlsx')->deleteFileAfterSend(true);
    }

    /** Import service charges; all-or-nothing, scoped to the business's own properties and facilities. */
    public function service_charges_upload(Request $request)
    {
        $this->allow('import');
        $file = collect($request->allFiles())->flatten()->first();
        if (!$file || !in_array(strtolower($file->getClientOriginalExtension()), ['xls', 'xlsx', 'csv'], true)) {
            return response()->json(['success' => false, 'message' => 'Upload the Excel template (.xlsx, .xls or .csv).'], 422);
        }

        $inventory = app(ComplexInventoryService::class);
        $rows = $inventory->readSheet($file->getRealPath());
        if (!$rows) {
            return response()->json(['success' => false, 'message' => 'The file has no service charge rows.'], 422);
        }

        $properties = Complex::forBusiness()->get()->keyBy(fn ($c) => mb_strtolower(trim((string) $c->ref_code)));
        $errors = [];
        $plan = [];
        foreach ($rows as $line => $row) {
            $complex = $properties->get(mb_strtolower(trim((string) ($row['complex_reference'] ?? ''))));
            $type = strtolower(str_replace(' ', '_', trim((string) ($row['amenity_type'] ?? ''))));
            $interval = collect(self::INTERVALS)->first(fn ($i) => strcasecmp($i, trim((string) ($row['payment_interval'] ?? 'Monthly'))) === 0);
            $amount = $row['charge_amount'] ?? null;
            $facility = $complex ? ComplexAmenity::where('complex_id', $complex->id)->where('amenity_type', $type)->orderBy('id')->first() : null;

            if (!$complex) { $errors[] = "Row $line: complex_reference does not match one of your properties."; continue; }
            if (!$facility) { $errors[] = "Row $line: {$complex->title} has no '{$type}' facility recorded."; continue; }
            if (!$interval) { $errors[] = "Row $line: payment_interval must be one of " . implode(', ', self::INTERVALS) . '.'; continue; }
            if (!is_numeric($amount) || (int) $amount < 1 || (float) $amount != (int) $amount) { $errors[] = "Row $line: charge_amount must be a whole number of at least 1."; continue; }
            $plan[] = [$complex, $facility, $interval, (int) $amount];
        }
        if ($errors) {
            return response()->json(['success' => false, 'message' => 'No service charges were imported. ' . implode(' ', array_slice($errors, 0, 10)), 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($plan, $inventory) {
            foreach ($plan as [$complex, $facility, $interval, $amount]) {
                $charge = ServiceCharge::firstOrNew(['amenity_type' => 'complex_amenities', 'amenity_id' => $facility->id]);
                $before = $charge->exists ? $charge->only(['name', 'charge']) : null;
                $charge->fill(['user_id' => business_owner_id(), 'complex_id' => $complex->id, 'name' => $interval, 'charge' => $amount])->save();
                $inventory->invalidate($complex, 'service_charge:' . $charge->id, $before, ['name' => $interval, 'charge' => $amount]);
            }
        });

        return response()->json(['success' => true, 'count' => count($plan), 'message' => count($plan) . ' service charges imported. Review the service charges setup step for each property.']);
    }
}
