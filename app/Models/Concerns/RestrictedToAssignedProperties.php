<?php

namespace App\Models\Concerns;

use App\Models\Scopes\AssignedPropertiesScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Records that belong to a property. When a team member's role is limited to assigned properties,
 * queries only return records of those properties.
 *
 * Models declare how they reach a property:
 * - default: a complex_id column;
 * - $propertyColumn: another column holding the property id ("id" on Complex itself);
 * - $tenancyColumn: a column holding a tenancy (tenant_management) id;
 * - or override restrictToProperties() for anything else.
 */
trait RestrictedToAssignedProperties
{
    public static function bootRestrictedToAssignedProperties(): void
    {
        static::addGlobalScope(new AssignedPropertiesScope);
    }

    public function restrictToProperties(Builder $query, array $complexIds, ?int $agentId, int $userId): void
    {
        if (property_exists($this, 'tenancyColumn')) {
            $query->whereIn($this->qualifyColumn($this->tenancyColumn), static::tenanciesIn($complexIds));
            return;
        }
        $column = property_exists($this, 'propertyColumn') ? $this->propertyColumn : 'complex_id';
        static::constrainUnits($query, $this->qualifyColumn($column), $complexIds,
            property_exists($this, 'unitColumns') ? array_map(fn ($c) => $this->qualifyColumn($c), $this->unitColumns) : null);
    }

    /**
     * complex column IN the member's properties; on unit-only properties (PropertyAccess::unitScope) only rows
     * of the allowed units — for models that link to units ($unitColumns: kind => column). Models without a unit
     * link stay property-level.
     */
    public static function constrainUnits($query, string $complexColumn, array $complexIds, ?array $unitColumns): void
    {
        $unitOnly = $unitColumns ? app(\App\Services\Access\PropertyAccess::class)->unitScope() : [];
        if (!$unitOnly) {
            $query->whereIn($complexColumn, $complexIds ?: [0]);
            return;
        }
        $full = array_values(array_diff($complexIds, array_keys($unitOnly)));
        $allowed = ['residential' => [], 'commercial' => []];
        foreach ($unitOnly as $units) {
            $allowed['residential'] = array_merge($allowed['residential'], $units['residential']);
            $allowed['commercial'] = array_merge($allowed['commercial'], $units['commercial']);
        }
        $query->where(function ($q) use ($complexColumn, $full, $unitOnly, $unitColumns, $allowed) {
            $q->whereIn($complexColumn, $full ?: [0])
              ->orWhere(function ($u) use ($complexColumn, $unitOnly, $unitColumns, $allowed) {
                  $u->whereIn($complexColumn, array_keys($unitOnly))->where(function ($w) use ($unitColumns, $allowed) {
                      $w->whereRaw('1 = 0');
                      foreach ($unitColumns as $kind => $column) {
                          $w->orWhereIn($column, $allowed[$kind] ?: [0]);
                      }
                  });
              });
        });
    }

    /** Sub-query: tenancy ids (or another tenancy column) at the given properties; unit-only properties give their units' tenancies. */
    public static function tenanciesIn(array $complexIds, string $select = 'id')
    {
        $query = DB::table('tenant_management')->select($select);
        static::constrainUnits($query, 'tenant_management.complex_id', $complexIds,
            ['residential' => 'tenant_management.residential_unit_id', 'commercial' => 'tenant_management.commercial_unit_id']);
        return $query;
    }
}
