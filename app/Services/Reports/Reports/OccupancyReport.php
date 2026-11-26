<?php

namespace App\Services\Reports\Reports;

use App\Models\CommercialUnit;
use App\Models\ComplexResidentialUnit;
use App\Models\TenantManagement;
use App\Models\TenantTermination;
use App\Models\User;
use App\Services\Reports\PropertyFigures;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

/** Occupied and vacant units per property and unit type, moves in and out, and rent lost to vacancy. */
class OccupancyReport extends Report
{
    public function __construct(private PropertyFigures $figures)
    {
    }

    public function key(): string { return 'occupancy'; }
    public function title(): string { return 'Occupancy & vacancy'; }
    public function description(): string { return 'Occupied, vacant and on-notice units per property and unit type, moves in and out, and the rent vacant units could earn.'; }
    public function icon(): string { return 'fa-door-open'; }
    public function rights(): array { return ['reports.occupancy']; }

    public function build(ReportFilters $f, User $user): ReportResult
    {
        $properties = $this->figures->properties($f->complexId);
        $units = $this->figures->units($f->complexId);
        $endOccupied = $this->occupiedAt($f->to, $properties->keys()->all());

        $byProperty = $units->map(fn ($u) => [
            'property' => $properties[$u->complex_id] ?? '—',
            'total' => $u->total,
            'occupied' => $u->occupied,
            'vacant' => $u->vacant,
            'other' => $u->other,
            'on_notice' => $u->on_notice,
            'occupancy' => $u->occupancy,
            'occupied_end' => $endOccupied[$u->complex_id] ?? 0,
        ])->filter(fn ($r) => $r['total'] > 0)->values();

        $total = $units->sum('total');
        $occupied = $units->sum('occupied');
        $moveIns = TenantManagement::query()->where('managed_by', business_owner_id())
            ->whereBetween('move_in_date', [$f->from, $f->to])->whereIn('complex_id', $properties->keys())
            ->whereNull('previous_tenancy_id') // moves between units are counted as transfers
            ->with('tenantProfile')->orderBy('move_in_date')->get();
        $moveOuts = TenantTermination::query()->join('tenant_management as tm', 'tm.id', '=', 'tenant_terminations.tenant_management_id')
            ->where('tm.managed_by', business_owner_id())->whereIn('tm.complex_id', $properties->keys())
            ->whereBetween('tenant_terminations.termination_date', [$f->from, $f->to])
            ->leftJoin('tenant_profiles as tp', 'tp.id', '=', 'tm.tenant_profile_id')
            ->orderBy('tenant_terminations.termination_date')
            ->get(['tenant_terminations.termination_date', 'tenant_terminations.termination_reason', 'tm.complex_name', 'tm.unit_number', 'tm.tenancy_ref',
                DB::raw("COALESCE(NULLIF(tp.business_name, ''), tp.tenant_name) as tenant")]);
        $vacant = $this->vacantUnits($properties);

        $result = (new ReportResult())
            ->metric('Occupancy', $total ? round($occupied / $total * 100, 1) : 0, 'percent', $occupied . ' of ' . $total . ' units let today')
            ->metric('Vacant units', $units->sum('vacant'), 'number', $units->sum('other') ? $units->sum('other') . ' more reserved, under maintenance or under offer' : 'Ready to let')
            ->metric('On notice', $units->sum('on_notice'), 'number', 'Tenancies with a move-out date')
            ->metric('Moves in / out', $moveIns->count() . ' / ' . $moveOuts->count(), 'text', 'In the period');
        $priced = $vacant->whereNotNull('rent');
        $result->moneyMetric('Vacant units could earn', $priced->groupBy('currency')->map(fn ($g) => round($g->sum('rent'), 2))->all(),
            $priced->count() === $vacant->count() ? 'A month, at the asking rent' : $priced->count() . ' of ' . $vacant->count() . ' vacant units have an asking rent');

        $result->section('By property', [
            'property' => ['Property', 'text'], 'total' => ['Units', 'number'], 'occupied' => ['Occupied', 'number'], 'vacant' => ['Vacant', 'number'],
            'other' => ['Reserved / maintenance', 'number'], 'on_notice' => ['On notice', 'number'], 'occupancy' => ['Occupancy today', 'percent'],
            'occupied_end' => ['Let at period end', 'number'],
        ], $byProperty, true, 'Today, and the number of units let on the last day of the period (from tenancy dates).', 'No units yet.');

        $result->section('By unit type', [
            'property' => ['Property', 'text'], 'type' => ['Unit type', 'text'], 'total' => ['Units', 'number'], 'occupied' => ['Occupied', 'number'],
            'vacant' => ['Vacant', 'number'], 'occupancy' => ['Occupancy', 'percent'],
        ], $this->byType($properties), false, null, 'No units yet.');

        $result->section('Vacant units', [
            'property' => ['Property', 'text'], 'unit' => ['Unit', 'text'], 'type' => ['Type', 'text'], 'rent' => ['Asking rent', 'money'],
        ], $vacant, true, 'Available units and their asking rent: the published listing\'s rent for flats and houses, the set rent for commercial spaces.', 'No vacant units.');

        $result->section('Moved in', [
            'date' => ['Date', 'date'], 'tenant' => ['Tenant', 'text'], 'tenancy' => ['Tenancy', 'text'], 'property' => ['Property / unit', 'text'], 'rent' => ['Rent', 'money'],
        ], $moveIns->map(fn ($t) => [
            'date' => $t->move_in_date, 'tenant' => $t->tenantProfile?->tenant_type === 'Business' ? ($t->tenantProfile->business_name ?: $t->tenantProfile->tenant_name) : $t->tenantProfile?->tenant_name,
            'tenancy' => $t->tenancy_ref, 'property' => trim($t->complex_name . ($t->unit_number ? ' · ' . $t->unit_number : '')),
            'rent' => (float) $t->monthly_payment, 'currency' => $t->currency ?: default_currency(),
        ]), true, null, 'Nobody moved in during the period.');

        $result->section('Moved out', [
            'date' => ['Date', 'date'], 'tenant' => ['Tenant', 'text'], 'tenancy' => ['Tenancy', 'text'], 'property' => ['Property / unit', 'text'], 'reason' => ['Reason', 'text'],
        ], $moveOuts->map(fn ($t) => [
            'date' => $t->termination_date, 'tenant' => $t->tenant, 'tenancy' => $t->tenancy_ref,
            'property' => trim($t->complex_name . ($t->unit_number ? ' · ' . $t->unit_number : '')), 'reason' => ucfirst(str_replace('_', ' ', (string) $t->termination_reason)),
        ]), false, null, 'Nobody moved out during the period.');

        $result->note('Sold houses are not counted as units. Tenants moving between your units are counted under transfers in the Leases & notices report, not as moves in and out.');

        return $result;
    }

    /** Units let on a date, per property: tenancies that had started and not yet ended. */
    private function occupiedAt(string $date, array $complexIds): array
    {
        // The assigned-properties scope qualifies columns with the table name, so no alias here.
        return TenantManagement::query()
            ->where('tenant_management.managed_by', business_owner_id())->whereIn('tenant_management.complex_id', $complexIds)
            ->where('tenant_management.move_in_date', '<=', $date)
            ->leftJoin('tenant_terminations as tt', 'tt.tenant_management_id', '=', 'tenant_management.id')
            ->where(fn ($q) => $q->whereIn('tenant_management.status', ['Active', 'On Notice'])
                ->orWhere('tt.termination_date', '>', $date)
                ->orWhere(fn ($q) => $q->where('tenant_management.status', 'Transferred')->where('tenant_management.expected_move_out_date', '>', $date)))
            ->groupBy('tenant_management.complex_id')
            ->get(['tenant_management.complex_id', DB::raw('COUNT(DISTINCT COALESCE(tenant_management.residential_unit_id, -tenant_management.commercial_unit_id)) as n')])
            ->pluck('n', 'complex_id')->map(fn ($n) => (int) $n)->all();
    }

    private function byType($properties)
    {
        $residential = ComplexResidentialUnit::query()->whereIn('complex_residential_units.complex_id', $properties->keys())
            ->where('complex_residential_units.status', '!=', 'sold')
            ->leftJoin('complex_units as cu', 'cu.id', '=', 'complex_residential_units.complex_unit_id')
            ->groupBy('complex_residential_units.complex_id', 'cu.id', 'cu.unit_type', 'cu.unit_category', 'cu.bedrooms')
            ->get(['complex_residential_units.complex_id', 'cu.unit_type', 'cu.unit_category', 'cu.bedrooms', DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN complex_residential_units.status = 'occupied' THEN 1 ELSE 0 END) as occupied"),
                DB::raw("SUM(CASE WHEN complex_residential_units.status = 'available' THEN 1 ELSE 0 END) as vacant")])
            ->map(fn ($r) => [
                'property' => $properties[$r->complex_id] ?? '—',
                'type' => $r->unit_type ?: (($r->bedrooms ? $r->bedrooms . '-bedroom ' : '') . ($r->unit_category === 'house' ? 'house' : 'flat')),
                'total' => (int) $r->total, 'occupied' => (int) $r->occupied, 'vacant' => (int) $r->vacant,
                'occupancy' => $r->total ? round($r->occupied / $r->total * 100, 1) : null,
            ]);
        $commercial = CommercialUnit::query()->whereIn('complex_id', $properties->keys())->groupBy('complex_id')
            ->get(['complex_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied"),
                DB::raw("SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as vacant")])
            ->map(fn ($r) => [
                'property' => $properties[$r->complex_id] ?? '—', 'type' => 'Commercial space',
                'total' => (int) $r->total, 'occupied' => (int) $r->occupied, 'vacant' => (int) $r->vacant,
                'occupancy' => $r->total ? round($r->occupied / $r->total * 100, 1) : null,
            ]);

        return $residential->concat($commercial)->sortBy([['property', 'asc'], ['type', 'asc']])->values();
    }

    private function vacantUnits($properties)
    {
        $listingRent = DB::table('listings')->where('user_id', business_owner_id())->whereNotNull('complex_unit_id')
            ->orderByRaw("CASE WHEN status = 'Published' THEN 0 ELSE 1 END")->get(['complex_unit_id', 'amount', 'currency'])
            ->unique('complex_unit_id')->keyBy('complex_unit_id');
        $residential = ComplexResidentialUnit::query()->whereIn('complex_residential_units.complex_id', $properties->keys())
            ->where('complex_residential_units.status', 'available')
            ->leftJoin('complex_units as cu', 'cu.id', '=', 'complex_residential_units.complex_unit_id')
            ->get(['complex_residential_units.complex_id', 'complex_residential_units.complex_unit_id', 'complex_residential_units.unit_number', 'cu.unit_type', 'cu.unit_category'])
            ->map(fn ($u) => [
                'property' => $properties[$u->complex_id] ?? '—', 'unit' => $u->unit_number,
                'type' => $u->unit_type ?: ($u->unit_category === 'house' ? 'House' : 'Flat'),
                'rent' => (float) ($listingRent[$u->complex_unit_id]->amount ?? 0) > 0 ? (float) $listingRent[$u->complex_unit_id]->amount : null,
                'currency' => $listingRent[$u->complex_unit_id]->currency ?? default_currency(),
            ]);
        $commercial = CommercialUnit::query()->whereIn('complex_id', $properties->keys())->where('status', 'Available')
            ->get(['complex_id', 'unit_number', 'monthly_rent'])
            ->map(fn ($u) => [
                'property' => $properties[$u->complex_id] ?? '—', 'unit' => $u->unit_number, 'type' => 'Commercial space',
                'rent' => (float) $u->monthly_rent > 0 ? (float) $u->monthly_rent : null, 'currency' => default_currency(),
            ]);

        return $residential->concat($commercial)->sortBy([['property', 'asc'], ['unit', 'asc']])->values();
    }
}
