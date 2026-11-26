<?php

namespace App\Http\Controllers\Business\Properties;

use App\Http\Controllers\Controller;
use App\Models\{Complex, ComplexAmenity, ComplexChange, ComplexParkingSlot, ServiceCharge};
use App\Services\Access\BusinessPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\{Rule, ValidationException};

class PropertyFacilityController extends Controller
{
    private function properties(string $action = 'edit')
    {
        $user = auth()->user();
        abort_unless(app(BusinessPermissionService::class)->can($user, 'complex_management', $action), 403);
        return Complex::forBusiness($user->businessOwnerId());
    }

    private function facility(int $id): ComplexAmenity
    {
        return ComplexAmenity::whereIn('complex_id', $this->properties()->select('id'))->findOrFail($id);
    }

    public function create(string $slug)
    {
        $complex = $this->properties()->where('slug', $slug)->firstOrFail();
        return view('pages.business.complex.amenities.create', compact('complex'));
    }

    public function edit(int $id)
    {
        return response()->json(['success' => true, 'amenity' => $this->facility($id)]);
    }

    public function store(Request $request, string $slug)
    {
        $complex = $this->properties()->where('slug', $slug)->firstOrFail();
        return $this->save($request, $complex);
    }

    public function update(Request $request, int $id)
    {
        $facility = $this->facility($id);
        return $this->save($request, $facility->complex, $facility);
    }

    private function save(Request $request, Complex $complex, ?ComplexAmenity $facility = null)
    {
        $catalogue = config('property_facilities');
        $request->validate(['amenity_type' => ['required', Rule::in(array_keys($catalogue))]]);
        $type = $request->string('amenity_type')->toString();
        $rules = [
            'annual_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'amenity_currency' => ['nullable', 'required_with:annual_cost', Rule::in(['KES', 'USD', 'GBP', 'EUR', 'JPY', 'DHS'])],
        ];
        $attributes = [];
        foreach ($catalogue[$type]['fields'] as $name => $field) {
            $attributes[$name] = strtolower($field['label']);
            $rules[$name] = ['nullable'];
            if ($field['type'] === 'number') {
                array_push($rules[$name], 'integer', 'min:'.$field['min'], 'max:1000000');
            } else {
                array_push($rules[$name], 'string', 'max:'.($field['type'] === 'textarea' ? 5000 : 255));
                if (isset($field['options'])) $rules[$name][] = Rule::in(array_unique([...array_keys($field['options']), ...($facility?->$name ? [$facility->$name] : [])]));
            }
        }
        $data = $request->validate($rules, [], $attributes);
        DB::transaction(function () use ($request, $complex, $facility, $catalogue, $type, $data) {
            $complex = $this->properties()->whereKey($complex->id)->lockForUpdate()->firstOrFail();
            if ($facility) $facility = ComplexAmenity::where('complex_id', $complex->id)->lockForUpdate()->findOrFail($facility->id);
            $before = $facility?->toArray();
            if ($facility && $facility->amenity_type !== $type) $this->checkReferences($facility);
            $values = ['amenity_type' => $type, 'currency' => $data['amenity_currency'] ?? null, 'annual_price' => $data['annual_cost'] ?? null];
            foreach ($catalogue as $definition) foreach ($definition['fields'] as $name => $field) $values[$name] = $data[$name] ?? null;
            $labels = $type === 'parking' ? array_values(array_filter(array_map('trim', explode(',', $data['parking_ids'] ?? '')), fn ($label) => $label !== '')) : [];
            if (count($labels) !== count(array_unique(array_map('mb_strtolower', $labels)))) throw ValidationException::withMessages(['parking_ids' => 'Each parking space needs a different label.']);
            foreach ($labels as $label) if (mb_strlen($label) > 255) throw ValidationException::withMessages(['parking_ids' => 'Parking labels must be 255 characters or fewer.']);
            if ($type === 'parking') {
                if (count($labels) > (int) ($values['parking_slots'] ?? count($labels))) throw ValidationException::withMessages(['parking_slots' => 'Capacity cannot be smaller than the number of labelled spaces.']);
                $values['parking_slots'] = $values['parking_slots'] ?? (count($labels) ?: null);
                $values['parking_ids'] = $labels ? implode(', ', $labels) : null;
            }
            $existing = $facility ? $facility->parkingSlots()->lockForUpdate()->get() : collect();
            foreach ($existing as $slot) {
                if (!in_array($slot->parking_id, $labels, true) && $this->slotAssigned($slot)) throw ValidationException::withMessages(['parking_ids' => 'Space '.$slot->parking_id.' is assigned to a tenant. Keep its label or release the assignment first.']);
            }
            $conflicts = ComplexParkingSlot::whereIn('parking_id', $labels);
            if ($facility) $conflicts->where('amenity_id', '!=', $facility->id);
            if ($conflicts->exists()) throw ValidationException::withMessages(['parking_ids' => 'A parking label is already in use. Choose a unique property-specific label.']);
            $facility ??= new ComplexAmenity(['complex_id' => $complex->id]);
            $facility->fill($values)->save();
            foreach ($existing as $slot) if (!in_array($slot->parking_id, $labels, true)) $slot->delete();
            foreach ($labels as $label) {
                if (!$existing->contains('parking_id', $label)) $facility->parkingSlots()->create(['complex_id' => $complex->id, 'parking_id' => $label]);
            }
            $this->recordChange($complex, $facility->id, $before, $facility->fresh()->toArray(), $request);
        });
        $url = route('complex.show', $complex->slug).'#amenities';
        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Facility saved. Review facilities in the setup checklist before confirming the property.', 'redirect' => $url])
            : redirect($url)->with('success', 'Facility saved.');
    }

    private function slotAssigned(ComplexParkingSlot $slot): bool
    {
        return $slot->is_occupied || $slot->occupied_tenant_id
            || (Schema::hasTable('tenant_parking_slots') && DB::table('tenant_parking_slots')->where('complex_parking_slot_id', $slot->id)->exists());
    }

    private function checkReferences(ComplexAmenity $facility): void
    {
        $charged = ServiceCharge::where('amenity_type', 'complex_amenities')->where('amenity_id', $facility->id)->exists();
        $assigned = Schema::hasTable('tenant_management_amenities') && DB::table('tenant_management_amenities')->where('amenity_id', $facility->id)->exists();
        if ($charged || $assigned) throw ValidationException::withMessages(['facility' => 'This facility is linked to tenant services or service charges. Update those links before removing it or changing its type.']);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['amenity_id' => 'required|integer']);
        return $this->remove($request, [$data['amenity_id']]);
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|distinct']);
        return $this->remove($request, $data['ids']);
    }

    private function remove(Request $request, array $ids)
    {
        // Check the entire request before any records are removed.
        foreach ($ids as $id) $this->facility($id);
        DB::transaction(function () use ($request, $ids) {
            foreach ($ids as $id) {
                $facility = $this->facility($id);
                $complex = $this->properties()->whereKey($facility->complex_id)->lockForUpdate()->firstOrFail();
                $facility = ComplexAmenity::lockForUpdate()->findOrFail($id);
                $this->checkReferences($facility);
                $slots = $facility->parkingSlots()->lockForUpdate()->get();
                foreach ($slots as $slot) if ($this->slotAssigned($slot)) throw ValidationException::withMessages(['facility' => 'Release the occupied parking spaces before removing this facility.']);
                $before = $facility->toArray();
                $facility->parkingSlots()->delete();
                $facility->delete();
                $this->recordChange($complex, $id, $before, null, $request);
            }
        });
        return response()->json(['success' => true, 'message' => 'Facility removed.']);
    }

    private function recordChange(Complex $complex, int $id, ?array $before, ?array $after, Request $request): void
    {
        ComplexChange::create(['complex_id' => $complex->id, 'field_name' => 'facility:'.$id, 'old_value' => json_encode($before), 'new_value' => json_encode($after), 'user_id' => auth()->id(), 'user_ip' => $request->ip()]);
        $complex->update(['setup_status' => 'draft', 'setup_confirmed_at' => null, 'setup_confirmed_by' => null]);
    }
}
