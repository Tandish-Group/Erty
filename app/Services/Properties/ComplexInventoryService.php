<?php

namespace App\Services\Properties;

use App\Services\Access\PropertyAccess;

use App\Models\{CommercialUnit, Complex, ComplexChange, ComplexResidentialUnit, ComplexUnit};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Single source of truth for property inventory changes, so that creating and editing
 * a complex (setup, editors, imports) produce the same records, audit and readiness state.
 */
class ComplexInventoryService
{
    public const COMMERCIAL_STATUSES = ['Available', 'Occupied', 'Under Maintenance', 'Reserved'];
    public const RESIDENTIAL_MANUAL_STATUSES = ['available', 'reserved', 'maintenance'];
    /** Older composition values (forms, imports and tests still send them). */
    public const COMPOSITION_TYPES = [
        'residential' => 'Apartment',
        'commercial' => 'Commercial Unit',
        'combined' => 'Apartment, Commercial Unit',
    ];

    /**
     * What a property can hold, and the token each kind writes into complexes.type (comma separated, in this
     * order). Specific house types (Villa, Townhouse…) live on the houses themselves, never in complexes.type.
     */
    public const KINDS = ['apartments' => 'Apartment', 'houses' => 'House', 'commercial' => 'Commercial Unit'];
    public const KIND_LABELS = ['apartments' => 'Apartments', 'houses' => 'Houses', 'commercial' => 'Commercial spaces'];
    /** Residential statuses a sale sets; nobody picks them by hand and occupancy sync leaves them alone. */
    public const SALE_STATUSES = ['under_offer', 'sold'];
    public const RESIDENTIAL_STATUS_LABELS = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'maintenance' => 'Under maintenance', 'under_offer' => 'Under offer', 'sold' => 'Sold'];

    /** The kinds a property (or a complexes.type value) holds, e.g. ['apartments', 'commercial']. */
    public static function kindsOf(Complex|string|null $complex): array
    {
        $type = $complex instanceof Complex ? $complex->type : $complex;
        $tokens = array_map(fn ($t) => mb_strtolower(trim($t)), explode(',', (string) $type));
        return array_keys(array_filter(self::KINDS, fn ($token) => in_array(mb_strtolower($token), $tokens, true)));
    }

    /** complexes.type for a set of kinds, always in the same order. */
    public static function typeFor(array $kinds): string
    {
        return implode(', ', array_values(array_intersect_key(self::KINDS, array_flip($kinds))));
    }

    /**
     * Kinds named in free text, as in the property import template's composition column:
     * "apartments", "houses" and/or "commercial" separated by commas (singular forms and the older
     * "residential" / "combined" work too). Empty when any part is not a known kind.
     */
    public static function kindsFromText(?string $text): array
    {
        $text = mb_strtolower(trim((string) $text));
        if (isset(self::COMPOSITION_TYPES[$text])) {
            return self::kindsOf(self::COMPOSITION_TYPES[$text]);
        }
        $aliases = [
            'apartment' => 'apartments', 'apartments' => 'apartments', 'flats' => 'apartments',
            'house' => 'houses', 'houses' => 'houses', 'homes' => 'houses',
            'commercial' => 'commercial', 'commercial units' => 'commercial', 'commercial spaces' => 'commercial',
        ];
        $kinds = [];
        foreach (preg_split('/\s*(?:,|;|\/|&|\band\b)\s*/', $text) as $part) {
            if ($part === '') {
                continue;
            }
            if (!isset($aliases[$part])) {
                return [];
            }
            $kinds[] = $aliases[$part];
        }
        return array_values(array_intersect(array_keys(self::KINDS), $kinds));
    }

    /** Kinds chosen on a form: `kinds[]` checkboxes, or an older `composition` value. */
    public static function kindsFromInput(array $input): array
    {
        if (isset($input['kinds'])) {
            return array_values(array_intersect(array_keys(self::KINDS), (array) $input['kinds']));
        }
        return isset(self::COMPOSITION_TYPES[$input['composition'] ?? '']) ? self::kindsOf(self::COMPOSITION_TYPES[$input['composition']]) : [];
    }

    public static function hasApartments(Complex $complex): bool
    {
        return in_array('apartments', self::kindsOf($complex), true);
    }

    public static function hasHouses(Complex $complex): bool
    {
        return in_array('houses', self::kindsOf($complex), true);
    }

    /** Apartments or houses: the residential unit table. */
    public static function hasResidential(Complex $complex): bool
    {
        return self::hasApartments($complex) || self::hasHouses($complex);
    }

    public static function hasCommercial(Complex $complex): bool
    {
        return in_array('commercial', self::kindsOf($complex), true);
    }

    /** "Mixed-use property", "Housing estate", "Single house"… */
    public static function compositionLabel(Complex $complex, ?int $houseCount = null): string
    {
        $kinds = self::kindsOf($complex);
        if (in_array('commercial', $kinds, true)) {
            return count($kinds) > 1 ? 'Mixed-use property' : 'Commercial property';
        }
        if ($kinds === ['houses']) {
            $houseCount ??= $complex->complex_residential_units()->where('unit_category', 'house')->count();
            return $houseCount === 1 ? 'Single house' : 'Housing estate';
        }
        return $kinds === ['apartments', 'houses'] ? 'Apartments & houses' : 'Residential property';
    }

    /** Limit a query to properties holding (or, with $not, lacking) a kind. Token-exact: "House" never matches "Townhouse". */
    public static function whereKind($query, string $kind, bool $not = false, string $column = 'complexes.type')
    {
        return $query->whereRaw("(',' || REPLACE(LOWER(COALESCE({$column}, '')), ', ', ',') || ',') " . ($not ? 'NOT LIKE' : 'LIKE') . ' ?', ['%,' . mb_strtolower(self::KINDS[$kind]) . ',%']);
    }

    /** Record an audit entry and return the property to draft setup. */
    public function invalidate(Complex $complex, string $field, $before, $after): void
    {
        $this->audit($complex, $field, $before, $after);
        $complex->update(['setup_status' => 'draft', 'setup_confirmed_at' => null, 'setup_confirmed_by' => null]);
    }

    /**
     * Occupancy of a commercial space from its active tenancies. Each tenancy occupies its
     * occupied_sq_area (falling back to sq_area), whatever the tenant type.
     */
    public function commercialOccupancy(\App\Models\CommercialUnit $unit, ?int $excludeTenancyId = null): array
    {
        $tenancies = app(PropertyAccess::class)->unrestricted(fn () => \App\Models\TenantManagement::where('commercial_unit_id', $unit->id)
            ->where('status', 'Active')
            ->when($excludeTenancyId, fn ($q) => $q->where('id', '!=', $excludeTenancyId))
            ->get(['id', 'occupied_sq_area', 'sq_area']));
        $occupied = (float) $tenancies->sum(fn ($t) => (float) ($t->occupied_sq_area ?? $t->sq_area ?? 0));
        $total = (float) $unit->unit_sq_ft;
        $maxSubdivisions = max(1, (int) ($unit->max_subdivisions ?? 1));
        $remainingSubdivisions = max(0, $maxSubdivisions - $tenancies->count());
        $remaining = max(0, $total - $occupied);

        return [
            'occupied_sq_ft' => round($occupied, 2),
            'remaining_sq_ft' => round($remaining, 2),
            'occupied_count' => $tenancies->count(),
            'max_subdivisions' => $maxSubdivisions,
            'remaining_subdivisions' => $remainingSubdivisions,
            'occupancy_percentage' => $total > 0 ? round($occupied / $total * 100, 2) : 0,
            'leasable' => in_array($unit->status, ['Available', 'Occupied'], true),
            'has_space' => in_array($unit->status, ['Available', 'Occupied'], true) && $remainingSubdivisions > 0 && $remaining >= (float) ($unit->min_sq_ft ?? 0) && $remaining > 0,
        ];
    }

    /** Refuse an area that does not fit the space (minimum, remaining area, subdivisions). */
    public function assertCommercialCapacity(\App\Models\CommercialUnit $unit, float $area, ?int $excludeTenancyId = null, string $field = 'occupied_sq_area'): void
    {
        $o = $this->commercialOccupancy($unit, $excludeTenancyId);
        if (!$o['leasable']) {
            throw ValidationException::withMessages(['commercial_unit_id' => "Space {$unit->unit_number} is {$unit->status} and cannot be let."]);
        }
        if ($area <= 0) {
            throw ValidationException::withMessages([$field => 'Enter the area this tenant occupies.']);
        }
        if ($area < (float) ($unit->min_sq_ft ?? 0)) {
            throw ValidationException::withMessages([$field => 'Space ' . $unit->unit_number . ' must be let in at least ' . number_format((float) $unit->min_sq_ft, 2) . ' sq ft.']);
        }
        if ($area > $o['remaining_sq_ft'] + 0.0001) {
            throw ValidationException::withMessages([$field => 'Only ' . number_format($o['remaining_sq_ft'], 2) . ' sq ft of space ' . $unit->unit_number . ' is still free.']);
        }
        if ($o['remaining_subdivisions'] < 1) {
            throw ValidationException::withMessages([$field => 'Space ' . $unit->unit_number . ' already has its maximum of ' . $o['max_subdivisions'] . ' tenancies.']);
        }
    }

    /**
     * Make recorded unit status match the active tenancies (after onboarding, editing, transfer
     * or termination). Manual statuses (maintenance, reserved) are left alone when no one is in.
     */
    public function syncOccupancy(?int $residentialUnitId = null, ?int $commercialUnitId = null): void
    {
        app(PropertyAccess::class)->unrestricted(function () use ($residentialUnitId, $commercialUnitId) {
            if ($residentialUnitId && ($unit = \App\Models\ComplexResidentialUnit::find($residentialUnitId))) {
                $active = $unit->activeTenancies()->exists();
                $status = $active ? 'occupied' : ($unit->status === 'occupied' ? 'available' : $unit->status);
                // A house under offer or sold follows its sale, not tenancies.
                if (in_array($unit->status, self::SALE_STATUSES, true)) $status = $unit->status;
                if ($status !== $unit->status) $unit->forceFill(['status' => $status])->save();
            }
            if ($commercialUnitId && ($unit = \App\Models\CommercialUnit::find($commercialUnitId))) {
                $active = $unit->activeTenants()->exists();
                $status = $active ? 'Occupied' : ($unit->status === 'Occupied' ? 'Available' : $unit->status);
                if ($status !== $unit->status) $unit->forceFill(['status' => $status])->save();
            }
        });
    }

    /** Free the parking slots held by a tenancy that has ended. */
    public function releaseParking(int $tenancyId): void
    {
        \App\Models\ComplexParkingSlot::withoutGlobalScopes()->where('occupied_tenant_id', $tenancyId)
            ->update(['is_occupied' => false, 'occupied_tenant_id' => null]);
    }

    /** Free a tenancy's parking slots, keeping one (it moves with the tenant to a new tenancy). */
    public function releaseParkingExcept(int $tenancyId, ?int $keepSlotId): void
    {
        \App\Models\ComplexParkingSlot::withoutGlobalScopes()->where('occupied_tenant_id', $tenancyId)
            ->when($keepSlotId, fn ($q) => $q->where('id', '!=', $keepSlotId))
            ->update(['is_occupied' => false, 'occupied_tenant_id' => null]);
    }

    public function audit(Complex $complex, string $field, $before, $after): void
    {
        ComplexChange::create([
            'complex_id' => $complex->id,
            'field_name' => $field,
            'old_value' => is_array($before) ? json_encode($before) : ($before === null ? null : (string) $before),
            'new_value' => is_array($after) ? json_encode($after) : (string) $after,
            'user_id' => auth()->id(),
            'user_ip' => request()?->ip(),
        ]);
    }

    /**
     * The only residential generator: one specification group plus its physical units.
     * Units start available, unnumbered and without a floor, carrying the specification's area.
     */
    public function generateResidential(Complex $complex, array $spec): ComplexUnit
    {
        $house = ($spec['category'] ?? 'apartment') === 'house';
        // A house specification also records its type, plot and what it is offered for; every house copies it.
        $houseSpec = $house ? [
            'storeys' => isset($spec['storeys']) && $spec['storeys'] !== '' ? (int) $spec['storeys'] : null,
            'plot_size' => isset($spec['plot_size']) && $spec['plot_size'] !== '' ? $spec['plot_size'] : null,
            'plot_unit' => ($spec['plot_size'] ?? '') !== '' ? ($spec['plot_unit'] ?? 'sq_m') : null,
            'offer' => $spec['offer'] ?? 'rent',
            'sale_price' => in_array($spec['offer'] ?? 'rent', ['sale', 'both'], true) ? $spec['sale_price'] : null,
        ] : [];
        $group = ComplexUnit::create([
            'complex_id' => $complex->id,
            'unit_category' => $house ? 'house' : 'apartment',
            'unit_type' => $house ? $spec['house_type'] : null,
            'bedrooms' => (int) $spec['bedrooms'],
            'bathrooms' => (int) $spec['bathrooms'],
            'sq_ft' => $spec['sq_ft'],
            'unit_count' => (int) $spec['unit_count'],
        ] + $houseSpec + ($house ? ['features' => array_values(array_intersect(array_keys(config('property.house_features')), (array) ($spec['features'] ?? [])))] : []));

        $now = now();
        $rows = [];
        for ($i = 0; $i < (int) $spec['unit_count']; $i++) {
            $rows[] = [
                'complex_id' => $complex->id, 'complex_unit_id' => $group->id, 'unit_category' => $house ? 'house' : 'apartment',
                'bedrooms' => (int) $spec['bedrooms'], 'bathrooms' => (int) $spec['bathrooms'], 'sq_ft' => $spec['sq_ft'],
                'unit_number' => null, 'floor' => null, 'status' => 'available', 'created_at' => $now, 'updated_at' => $now,
            ] + ($house ? [
                'house_type' => $spec['house_type'], 'plot_size' => $houseSpec['plot_size'], 'plot_unit' => $houseSpec['plot_unit'],
                'offer' => $houseSpec['offer'], 'sale_price' => $houseSpec['sale_price'],
            ] : []);
        }
        ComplexResidentialUnit::insert($rows);

        $this->invalidate($complex, ($house ? 'house_specification:' : 'residential_specification:') . $group->id, null, [
            'bedrooms' => (int) $spec['bedrooms'], 'bathrooms' => (int) $spec['bathrooms'], 'sq_ft' => $spec['sq_ft'], 'unit_count' => (int) $spec['unit_count'],
        ] + ($house ? ['house_type' => $spec['house_type']] + $houseSpec : []));

        return $group;
    }

    /**
     * Validate commercial space rows for one property. Returns [errors keyed by "$index.field" => message, normalised rows].
     * `$ignoreId` is the unit being edited: its own number is not a duplicate, and its area/subdivisions
     * cannot drop below what active tenancies already occupy.
     */
    public function validateCommercialRows(Complex $complex, array $rows, ?int $ignoreId = null): array
    {
        $taken = CommercialUnit::where('complex_id', $complex->id)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->pluck('unit_number')->map(fn ($n) => mb_strtolower(trim((string) $n)))->filter()->all();

        $errors = [];
        $clean = [];
        foreach (array_values($rows) as $index => $row) {
            $number = trim((string) ($row['unit_number'] ?? ''));
            $area = is_numeric($row['unit_sq_ft'] ?? null) ? (float) $row['unit_sq_ft'] : null;
            $minimum = is_numeric($row['min_sq_ft'] ?? null) ? (float) $row['min_sq_ft'] : null;
            $subdivisions = is_numeric($row['max_subdivisions'] ?? null) ? (int) $row['max_subdivisions'] : null;
            $floors = trim((string) ($row['floors'] ?? ''));
            $customFloors = trim((string) ($row['custom_floors'] ?? ''));
            $status = $row['status'] ?? 'Available';
            $rent = ($row['monthly_rent'] ?? '') === '' || $row['monthly_rent'] === null ? null : $row['monthly_rent'];

            if ($number === '' || in_array(mb_strtolower($number), $taken, true)) {
                $errors["$index.unit_number"] = 'Enter a unique, non-empty unit number within this property.';
            }
            if ($area === null || $area < 1) {
                $errors["$index.unit_sq_ft"] = 'Enter the space area (at least 1 sq ft).';
            }
            if ($minimum === null || $minimum < 1) {
                $errors["$index.min_sq_ft"] = 'Enter the minimum leasable area (at least 1 sq ft).';
            } elseif ($area !== null && $minimum > $area) {
                $errors["$index.min_sq_ft"] = 'Minimum leasable area cannot exceed the unit area.';
            }
            if ($subdivisions === null || $subdivisions < 0 || $subdivisions > 20) {
                $errors["$index.max_subdivisions"] = 'Subdivisions must be between 0 and 20.';
            }
            if ($floors === '' && $customFloors === '') {
                $errors["$index.floors"] = 'Record the floor for this space.';
            }
            if (!in_array($status, self::COMMERCIAL_STATUSES, true)) {
                $errors["$index.status"] = 'Choose a valid status.';
            }
            if ($rent !== null && (!is_numeric($rent) || $rent < 0)) {
                $errors["$index.monthly_rent"] = 'Monthly rent must be zero or more.';
            }

            if ($ignoreId) {
                $tenancies = DB::table('tenant_management')->where('commercial_unit_id', $ignoreId)->where('status', 'Active')->get(['occupied_sq_area', 'sq_area']);
                $allocated = $tenancies->sum(fn ($t) => (float) ($t->occupied_sq_area ?? $t->sq_area ?? 0));
                if ($area !== null && $area < $allocated) {
                    $errors["$index.unit_sq_ft"] = 'Area cannot be less than the ' . rtrim(rtrim(number_format($allocated, 2), '0'), '.') . ' sq ft already let to active tenants.';
                }
                if ($subdivisions !== null && $tenancies->count() > max(1, $subdivisions)) {
                    $errors["$index.max_subdivisions"] = 'Subdivisions cannot be fewer than the ' . $tenancies->count() . ' active tenancies in this space.';
                }
                if ($tenancies->isNotEmpty() && $status !== 'Occupied') {
                    $errors["$index.status"] = 'This space has active tenants, so its status must stay Occupied.';
                }
                if ($tenancies->isEmpty() && $status === 'Occupied') {
                    $errors["$index.status"] = 'Only tenant onboarding marks a space as Occupied.';
                }
            } elseif ($status === 'Occupied') {
                $errors["$index.status"] = 'New spaces cannot start as Occupied; onboarding a tenant sets this.';
            }

            $taken[] = mb_strtolower($number);
            $clean[] = [
                'unit_number' => $number, 'unit_sq_ft' => $area, 'min_sq_ft' => $minimum, 'max_subdivisions' => $subdivisions,
                'floors' => $floors !== '' ? $floors : null, 'custom_floors' => $customFloors !== '' ? $customFloors : null,
                'monthly_rent' => $rent, 'status' => $status, 'description' => ($row['description'] ?? null) ?: null,
            ];
        }

        return [$errors, $clean];
    }

    /** Create validated commercial spaces (caller holds the property lock). */
    public function createCommercial(Complex $complex, array $cleanRows): array
    {
        $created = [];
        foreach ($cleanRows as $row) {
            $unit = CommercialUnit::create($row + ['complex_id' => $complex->id]);
            $created[] = $unit;
            $this->audit($complex, 'commercial_unit:' . $unit->id, null, $row);
        }
        $complex->update(['setup_status' => 'draft', 'setup_confirmed_at' => null, 'setup_confirmed_by' => null]);
        return $created;
    }

    /** Read the first worksheet of an uploaded spreadsheet into header-keyed rows (blank rows skipped). */
    public function readSheet(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_map(fn ($h) => Str::snake(trim((string) $h)), array_shift($sheet) ?? []);
        $rows = [];
        foreach ($sheet as $line => $values) {
            if (!array_filter($values, fn ($v) => $v !== null && trim((string) $v) !== '')) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                if ($header !== '') {
                    $row[$header] = is_string($values[$i] ?? null) ? trim($values[$i]) : ($values[$i] ?? null);
                }
            }
            $rows[$line + 2] = $row; // spreadsheet row number, for error messages
        }
        return $rows;
    }

    /** Build an .xlsx template with headers, one example row and notes. */
    public function template(array $headers, array $example, array $notes): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');
        $sheet->fromArray([$headers, $example], null, 'A1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
        $help = $spreadsheet->createSheet();
        $help->setTitle('Notes');
        $help->fromArray(array_map(fn ($note) => [$note], $notes), null, 'A1');
        $help->getColumnDimension('A')->setWidth(110);
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'erty-template') . '.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        return $path;
    }
}
