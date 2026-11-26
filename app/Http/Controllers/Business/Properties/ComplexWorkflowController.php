<?php

namespace App\Http\Controllers\Business\Properties;

use App\Http\Controllers\Controller;
use App\Models\{Complex, ComplexChange, ComplexUnit, ComplexResidentialUnit};
use App\Services\Access\BusinessPermissionService;
use App\Services\Properties\ComplexInventoryService;
use App\Services\Properties\ComplexSetupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ComplexWorkflowController extends Controller
{

    private function allow(string $action): void
    {
        abort_unless(app(BusinessPermissionService::class)->can(auth()->user(), 'complex_management', $action), 403);
    }

    private function saved(Request $request, string $url, string $message)
    {
        if ($request->expectsJson()) {
            // Shown if the page falls back to a full load instead of an in-place refresh.
            session()->flash('success', $message);
            return response()->json(['success' => true, 'redirect' => $url, 'message' => $message]);
        }
        return redirect()->to($url)->with('success', $message);
    }

    /** House fields: required (generating a house type) or optional (refining one house). */
    private static function houseRules(bool $generating): array
    {
        $need = $generating ? 'required' : 'nullable';
        return [
            'house_type' => $need . '|in:' . implode(',', config('property.house_types')),
            'storeys' => 'nullable|integer|min:1|max:10',
            'plot_size' => 'nullable|numeric|min:0.001|max:1000000', 'plot_unit' => 'nullable|in:' . implode(',', array_keys(config('property.plot_units'))),
            'offer' => $need . '|in:rent,sale,both', 'sale_price' => 'required_if:offer,sale,both|nullable|numeric|min:1|max:9999999999999',
        ] + ($generating ? [] : [
            'plot_number' => 'nullable|string|max:60', 'title_number' => 'nullable|string|max:120',
            'bedrooms' => 'nullable|integer|min:0|max:30', 'bathrooms' => 'nullable|integer|min:1|max:30',
        ]);
    }

    public function draft(Request $request)
    {
        $this->allow('create');
        $data = $request->validate([
            'title' => 'required|string|max:255', 'ref_code' => 'required|string|max:255',
            'email' => 'required|email|max:255', 'mobile' => 'required|string|max:50',
            'county' => 'required|string|max:255', 'county_specific' => 'required|string|max:255',
            'location_description' => 'required|string|max:5000', 'description' => 'required|string|max:10000',
            'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
            'kinds' => 'required_without:composition|array|min:1', 'kinds.*' => 'in:apartments,houses,commercial',
            'composition' => 'required_without:kinds|in:residential,commercial,combined', 'principal' => 'required|boolean',
            'manager_ids' => 'nullable|array', 'manager_ids.*' => 'integer',
            'assigned_agent_id' => 'nullable|integer',
        ]);
        $managerIds = $data['manager_ids'] ?? [];
        $agentId = $data['assigned_agent_id'] ?? null;
        unset($data['manager_ids'], $data['assigned_agent_id']);
        app(\App\Services\Agents\AgentAssignmentService::class)->assertAssignable($agentId ? (int) $agentId : null);
        if ($managerIds && !auth()->user()->hasBusinessPermission('complex_management', 'assign')) {
            throw ValidationException::withMessages(['manager_ids' => 'Your role does not allow you to assign team members to properties.']);
        }
        $data['type'] = ComplexInventoryService::typeFor(ComplexInventoryService::kindsFromInput($data));
        unset($data['composition'], $data['kinds']);
        $complex = DB::transaction(function () use ($data, $managerIds, $agentId) {
            $complex = Complex::create($data + ['user_id' => business_owner_id(), 'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(8)), 'available' => 0, 'class' => 'Regular', 'setup_status' => 'draft']
                + ($agentId ? ['assigned_agent_id' => $agentId, 'management_type' => Complex::MANAGED_BY_INDIVIDUAL] : ['management_type' => Complex::MANAGED_BY_COMPANY]));
            app(\App\Services\Agents\AssignmentHistoryService::class)->recordProperty($complex, $agentId ? (int) $agentId : null, 'Property created');
            $inventory = app(ComplexInventoryService::class);
            if ($agentId) {
                $inventory->audit($complex, 'assigned_agent_id', null, $agentId);
            }
            if ($managerIds) {
                app(\App\Services\Agents\PropertyAssignmentService::class)->setForComplex($complex, $managerIds);
                $inventory->audit($complex, 'managers', null, \App\Models\User::whereIn('id', $managerIds)->get()->map(fn ($u) => trim($u->first_name.' '.$u->last_name))->implode(', '));
            }
            // A team member limited to assigned properties manages the property they create.
            app(\App\Services\Access\PropertyAccess::class)->claimCreated($complex->id);
            return $complex;
        });
        return $this->saved($request, route('complex.setup', [$complex->slug, 'step' => 'identity']), 'Property draft saved. You can leave and resume setup from the portfolio.');
    }

    public function details(Request $request, string $slug)
    {
        $this->allow('edit');
        $data = $request->validate([
            'title' => 'required|string|max:255', 'ref_code' => 'required|string|max:255',
            'email' => 'required|email|max:255', 'mobile' => 'required|string|max:50',
            'county' => 'required|string|max:255', 'county_specific' => 'required|string|max:255',
            'location_description' => 'required|string|max:5000', 'description' => 'required|string|max:10000',
            'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
            'principal' => 'required|boolean', 'fingerprint' => 'required|string|size:64',
        ]);
        return DB::transaction(function () use ($data, $slug, $request) {
            $complex = Complex::forBusiness()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $step = app(ComplexSetupService::class)->steps($complex)['identity'];
            if (!hash_equals($step['fingerprint'], $data['fingerprint'])) throw ValidationException::withMessages(['details' => 'Property details changed while you were editing. Refresh before saving.']);
            unset($data['fingerprint']);
            foreach ($data as $field => $value) {
                if ((string) $complex->$field !== (string) $value) {
                    ComplexChange::create(['complex_id' => $complex->id, 'field_name' => $field, 'old_value' => $complex->$field, 'new_value' => $value, 'user_id' => auth()->id(), 'user_ip' => $request->ip()]);
                }
            }
            $complex->fill($data);
            if ($complex->isDirty()) $complex->fill(['setup_status' => 'draft', 'setup_confirmed_at' => null, 'setup_confirmed_by' => null]);
            $complex->save();
            return $this->saved($request, route('complex.setup', [$slug, 'step' => 'identity']), 'Property details saved. Review the updated information below.');
        });
    }

    public function composition(Request $request, string $slug)
    {
        $this->allow('edit');
        $data = $request->validate([
            'kinds' => 'required_without:composition|array|min:1', 'kinds.*' => 'in:apartments,houses,commercial',
            'composition' => 'required_without:kinds|in:residential,commercial,combined', 'fingerprint' => 'required|string|size:64',
        ]);
        return DB::transaction(function () use ($data, $slug, $request) {
            $complex = Complex::forBusiness()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $step = app(ComplexSetupService::class)->steps($complex)['structure'];
            if (!hash_equals($step['fingerprint'], $data['fingerprint'])) {
                throw ValidationException::withMessages(['composition' => 'Property composition changed. Refresh before saving.']);
            }
            $kinds = ComplexInventoryService::kindsFromInput($data);
            // Apartments and houses share the residential table, so each is checked by its category.
            $held = array_filter([
                'apartments' => $complex->complex_residential_units()->where('unit_category', '!=', 'house')->exists(),
                'houses' => $complex->complex_residential_units()->where('unit_category', 'house')->exists(),
                'commercial' => $complex->commercialUnits()->exists(),
            ]);
            if ($missing = array_diff(array_keys($held), $kinds)) {
                $names = implode(' and ', array_map(fn ($k) => mb_strtolower(ComplexInventoryService::KIND_LABELS[$k]), $missing));
                throw ValidationException::withMessages(['composition' => "This property already has {$names}. Keep them in its composition so those units remain accessible."]);
            }
            $type = ComplexInventoryService::typeFor($kinds);
            if ($complex->type !== $type) {
                ComplexChange::create(['complex_id' => $complex->id, 'field_name' => 'type', 'old_value' => $complex->type, 'new_value' => $type, 'user_id' => auth()->id(), 'user_ip' => $request->ip()]);
                $complex->update(['type' => $type, 'setup_status' => 'draft', 'setup_confirmed_at' => null, 'setup_confirmed_by' => null]);
            }
            return $this->saved($request, route('complex.setup', [$slug, 'step' => 'structure']), 'Property composition saved. Review the updated inventory plan.');
        });
    }

    public function review(Request $request, string $slug, string $step)
    {
        $this->allow('edit');
        $request->validate(['acknowledged' => 'accepted', 'fingerprint' => 'required|string|size:64', 'decision' => 'required|in:confirmed,deferred', 'note' => 'nullable|string|max:1000']);
        return DB::transaction(function () use ($request, $slug, $step) {
            $complex = Complex::forBusiness()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $steps = app(ComplexSetupService::class)->steps($complex);
            abort_unless(isset($steps[$step]), 404);
            $current = $steps[$step];
            if (!$current['valid'] || !hash_equals($current['fingerprint'], $request->fingerprint)) {
                throw ValidationException::withMessages(['review' => 'Information is incomplete or changed since you opened this step. Refresh and review the current records.']);
            }
            if ($request->decision === 'deferred' && (empty($current['optional']) || !filled($request->note))) {
                throw ValidationException::withMessages(['review' => 'Only optional sections may be deferred, with a reason.']);
            }
            ComplexChange::create(['complex_id' => $complex->id, 'field_name' => 'setup_review:'.$step, 'old_value' => $current['review']?->new_value,
                'new_value' => json_encode(['fingerprint' => $current['fingerprint'], 'decision' => $request->decision, 'note' => $request->note]), 'user_id' => auth()->id(), 'user_ip' => $request->ip()]);
            $keys = array_keys($steps);
            $next = $keys[array_search($step, $keys, true) + 1] ?? 'finish';
            return $this->saved($request, route('complex.setup', [$slug, 'step' => $next]), 'Review saved with your name and timestamp.');
        });
    }

    public function refineResidential(Request $request, string $slug, int $unit)
    {
        $this->allow('edit');
        $data = $request->validate([
            'unit_number' => 'required|string|max:255', 'floor' => 'nullable|integer|between:-20,300', 'sq_ft' => 'required|numeric|min:1',
            'status' => 'nullable|in:' . implode(',', ComplexInventoryService::RESIDENTIAL_MANUAL_STATUSES),
            'fingerprint' => 'required|string|size:64', 'page' => 'nullable|integer|min:1', 'return_to' => 'nullable|in:inventory',
        ] + self::houseRules(false));
        return DB::transaction(function () use ($data, $slug, $unit, $request) {
            $complex = Complex::forBusiness()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $record = $complex->complex_residential_units()->lockForUpdate()->findOrFail($unit);
            $house = $record->isHouse();
            $stepKey = $house ? 'houses' : 'residential';
            $step = app(ComplexSetupService::class)->steps($complex)[$stepKey] ?? null;
            abort_unless($step, 422);
            if (!hash_equals($step['fingerprint'], $data['fingerprint'])) throw ValidationException::withMessages(['inventory' => 'Inventory changed. Refresh before saving this unit.']);
            if (!$house && !isset($data['floor'])) throw ValidationException::withMessages(['floor' => 'Enter the floor this unit is on.']);
            $number = trim($data['unit_number']);
            $duplicate = $complex->complex_residential_units()->where('id', '!=', $unit)->get()->contains(fn ($other) => mb_strtolower(trim($other->unit_number ?? '')) === mb_strtolower($number));
            if ($number === '' || $duplicate) throw ValidationException::withMessages(['unit_number' => 'Use a unique, non-empty ' . ($house ? 'house' : 'unit') . ' number within this property.']);

            $inventory = app(ComplexInventoryService::class);
            // What setup reviews (identity) versus what a house is offered for (operational, audited only).
            $identity = $house ? ['unit_number', 'sq_ft', 'bedrooms', 'bathrooms', 'house_type', 'plot_number', 'plot_size', 'plot_unit', 'title_number'] : ['unit_number', 'floor', 'sq_ft'];
            $before = $record->only($identity);
            $after = ['unit_number' => $number, 'sq_ft' => $data['sq_ft']] + ($house ? [
                'bedrooms' => (int) ($data['bedrooms'] ?? $record->bedrooms), 'bathrooms' => (int) ($data['bathrooms'] ?? $record->bathrooms),
                // Fields left out of the request keep their recorded value; an empty field clears it.
                'house_type' => $data['house_type'] ?? $record->house_type,
                'plot_number' => array_key_exists('plot_number', $data) ? ($data['plot_number'] ?: null) : $record->plot_number,
                'plot_size' => array_key_exists('plot_size', $data) ? ($data['plot_size'] ?: null) : $record->plot_size,
                'plot_unit' => array_key_exists('plot_size', $data) ? ($data['plot_size'] ? ($data['plot_unit'] ?? 'sq_m') : null) : $record->plot_unit,
                'title_number' => array_key_exists('title_number', $data) ? ($data['title_number'] ?: null) : $record->title_number,
            ] : ['floor' => (int) $data['floor']]);
            $identityChanged = collect($identity)->contains(fn ($f) => is_numeric($before[$f]) || is_numeric($after[$f]) ? (float) $before[$f] !== (float) $after[$f] : (string) $before[$f] !== (string) $after[$f]);

            if ($house && isset($data['offer'])) {
                $offer = ['offer' => $data['offer'], 'sale_price' => in_array($data['offer'], ['sale', 'both'], true) ? $data['sale_price'] : null];
                if ($record->liveSale()->exists() && $offer['offer'] === 'rent') {
                    throw ValidationException::withMessages(['offer' => 'This house has a sale in progress. Cancel the sale before taking it off the market.']);
                }
                if ($offer['offer'] !== $record->offer || (float) $offer['sale_price'] !== (float) $record->sale_price) {
                    $inventory->audit($complex, 'house_offer:' . $unit, $record->only(['offer', 'sale_price']), $offer);
                    $record->fill($offer);
                }
            }

            // Status is operational, not setup: it never resets readiness, but occupancy belongs to tenancies and sales.
            $status = $data['status'] ?? null;
            if ($status !== null && $status !== $record->status) {
                if ($record->status === 'occupied' || $record->activeTenancies()->exists()) {
                    throw ValidationException::withMessages(['status' => 'This unit has an active tenancy, so its status is managed by the tenancy.']);
                }
                if (in_array($record->status, ComplexInventoryService::SALE_STATUSES, true)) {
                    throw ValidationException::withMessages(['status' => 'This house is ' . ComplexInventoryService::RESIDENTIAL_STATUS_LABELS[$record->status] . ', so its status follows the sale.']);
                }
                $inventory->audit($complex, 'residential_unit_status:' . $unit, $record->status, $status);
                $record->status = $status;
            }

            $record->fill($after)->save();
            if ($identityChanged) {
                $inventory->invalidate($complex, ($house ? 'house:' : 'residential_unit:') . $unit, $before, $after);
            }

            $noun = $house ? 'House ' : 'Unit ';
            $message = $identityChanged
                ? $noun . $number . ' saved. Review ' . ($house ? 'the houses' : 'residential setup') . ' before leasing; saving does not confirm the inventory.'
                : $noun . $number . ' saved.';
            if (($data['return_to'] ?? null) === 'inventory') {
                return $this->saved($request, route('residential-units.index', ['complex_id' => $complex->id]), $message);
            }
            return $this->saved($request, route('complex.setup', [$slug, 'step' => $stepKey, 'page' => $data['page'] ?? 1]), $identityChanged ? $noun . 'saved. Continue refining the rest, then review the inventory.' : $message);
        });
    }

    public function generate(Request $request, string $slug)
    {
        $this->allow('edit');
        $house = $request->input('category') === 'house';
        $data = $request->validate(['category' => 'nullable|in:apartment,house', 'bedrooms' => 'required|integer|min:0|max:30', 'bathrooms' => 'required|integer|min:1|max:30', 'sq_ft' => 'required|numeric|min:1', 'unit_count' => 'required|integer|min:1|max:500', 'fingerprint' => 'required|string|size:64']
            + ($house ? self::houseRules(true) + ['features' => 'nullable|array', 'features.*' => 'in:' . implode(',', array_keys(config('property.house_features')))] : []));
        return DB::transaction(function () use ($data, $slug, $request, $house) {
            $complex = Complex::forBusiness()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            abort_unless($house ? ComplexInventoryService::hasHouses($complex) : ComplexInventoryService::hasApartments($complex), 422, $house ? 'This property has no houses in its composition.' : 'This property has no apartments in its composition.');
            $stepKey = $house ? 'houses' : 'residential';
            $step = app(ComplexSetupService::class)->steps($complex)[$stepKey];
            if (!hash_equals($step['fingerprint'], $data['fingerprint'])) throw ValidationException::withMessages(['inventory' => 'Inventory changed. Refresh before generating another group.']);
            app(ComplexInventoryService::class)->generateResidential($complex, $data + ['category' => $house ? 'house' : 'apartment']);
            return $this->saved($request, route('complex.setup', [$slug, 'step' => $stepKey]), $house ? 'Houses added. Give each one its house number before confirming.' : 'Units generated. Assign their numbers and floors before confirming this inventory.');
        });
    }

    /**
     * Advanced settings: classification, supporting links and the responsible agent.
     * Identity/location, composition and inventory have their own editors.
     */
    public function advanced(Request $request, string $slug)
    {
        $this->allow('edit');
        // The editor sends one link per line; arrays are accepted too.
        $links = $request->input('links');
        if (is_string($links)) {
            $links = preg_split('/[\r\n,]+/', $links);
        }
        $request->merge(['links' => array_values(array_filter(array_map('trim', (array) ($links ?? [])), 'strlen'))]);
        $data = $request->validate([
            'class' => 'required|string|max:100',
            'links' => 'array|max:20',
            'links.*' => 'url|max:500',
            'assigned_agent_id' => 'nullable|integer',
        ], ['links.*.url' => 'Each supporting link must be a full web address starting with http:// or https://.']);

        return DB::transaction(function () use ($data, $slug, $request) {
            $complex = Complex::forBusiness()->where('slug', $slug)->lockForUpdate()->firstOrFail();
            $inventory = app(ComplexInventoryService::class);

            $agentId = $data['assigned_agent_id'] ?? null;
            // A property's lead agent must be an agent who signs in (an existing external agent may stay).
            if ($agentId && (int) $agentId !== (int) $complex->assigned_agent_id) {
                app(\App\Services\Agents\AgentAssignmentService::class)->assertAssignable((int) $agentId);
            }

            $primaryChanged = (string) $complex->assigned_agent_id !== (string) $agentId;
            foreach (['class' => $data['class'], 'assigned_agent_id' => $agentId] as $field => $value) {
                if ((string) $complex->$field !== (string) $value) {
                    $inventory->audit($complex, $field, $complex->$field, $value ?? '');
                    $complex->$field = $value;
                }
            }
            if ($primaryChanged) {
                // A primary manager means individual management; clearing it hands the property to the company.
                $complex->management_type = $agentId ? Complex::MANAGED_BY_INDIVIDUAL : Complex::MANAGED_BY_COMPANY;
            }
            $complex->save();
            if ($primaryChanged) {
                app(\App\Services\Agents\AssignmentHistoryService::class)->recordProperty($complex, $agentId ? (int) $agentId : null, 'Changed in property settings');
                app(\App\Services\Access\PropertyAccess::class)->forget();
            }

            $links = collect($data['links'] ?? [])->map(fn ($link) => trim((string) $link))->filter()->unique()->values();
            $current = $complex->links()->orderBy('id')->pluck('link');
            if ($links->all() !== $current->all()) {
                $complex->links()->delete();
                foreach ($links as $link) {
                    \App\Models\ComplexLink::create(['complex_id' => $complex->id, 'link' => $link]);
                }
                // Links are reviewed in the "Floor plans & supporting records" setup step.
                $inventory->invalidate($complex, 'links', $current->all(), $links->all());
            }

            return $this->saved($request, route('complex.show', $complex->slug) . '#settings', 'Advanced settings saved.');
        });
    }
}
