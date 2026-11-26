<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;

/**
 * Which properties the signed-in team member may work with.
 *
 * Roles can be limited to "only properties assigned to the member" (business_roles.property_scope).
 * Such a member works with:
 * - in full: properties assigned to them (complex_user), properties they are the primary manager of,
 *   and every company-managed property (the team works those according to their roles);
 * - unit-only: properties they reach only through units assigned to them. There they see just those
 *   units and what belongs to them (tenancies, bills, payments, repairs) — see unitScope().
 * The limit is switched on per request by the business.access middleware, so console commands,
 * queue jobs, public pages and renters are never affected. Eloquent models that hang off a property
 * use AssignedPropertiesScope, which reads complexIds() from here.
 */
class PropertyAccess
{
    private ?User $user = null;
    private ?array $complexIds = null;
    /** @var array<int, array{residential: int[], commercial: int[]}> unit-only properties => allowed units */
    private array $unitScope = [];
    private bool $loaded = false;
    private ?int $agentId = null;
    private bool $agentLoaded = false;
    private int $paused = 0;

    public function activate(User $user): void
    {
        $this->user = $user;
        $this->forget();
    }

    public function deactivate(): void
    {
        $this->user = null;
        $this->forget();
    }

    /** Clear cached assignments (after properties are assigned, created or removed). */
    public function forget(): void
    {
        $this->complexIds = null;
        $this->unitScope = [];
        $this->loaded = false;
        $this->agentId = null;
        $this->agentLoaded = false;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    /** True when the current request is limited to the member's assigned properties. */
    public function restricted(): bool
    {
        return $this->paused === 0 && $this->user !== null && $this->user->limitedToAssignedProperties();
    }

    /** Assigned property ids, or null when the current user may see every property of the business. */
    public function complexIds(): ?array
    {
        if (!$this->restricted()) {
            return null;
        }
        if (!$this->loaded) {
            $ownerId = $this->user->businessOwnerId();
            $full = DB::table('complex_user')
                ->join('complexes', 'complexes.id', '=', 'complex_user.complex_id')
                ->where('complex_user.user_id', $this->user->id)
                ->where('complexes.user_id', $ownerId)
                ->pluck('complex_user.complex_id');
            // Company-managed properties are worked by the whole team.
            if (\Illuminate\Support\Facades\Schema::hasColumn('complexes', 'management_type')) {
                $full = $full->merge(DB::table('complexes')->where('user_id', $ownerId)
                    ->where(fn ($q) => $q->where('management_type', 'company')->orWhereNull('assigned_agent_id'))->pluck('id'));
            }
            $units = ['residential' => [], 'commercial' => []];
            $unitComplexes = collect();
            if ($agentId = $this->agentId()) {
                // The properties they are the primary manager of.
                $full = $full->merge(DB::table('complexes')->where('user_id', $ownerId)->where('assigned_agent_id', $agentId)->pluck('id'));
                foreach (['residential' => 'complex_residential_units', 'commercial' => 'commercial_units'] as $kind => $table) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'assigned_agent_id')) {
                        $rows = DB::table($table)->join('complexes', 'complexes.id', '=', "$table.complex_id")
                            ->where('complexes.user_id', $ownerId)->where("$table.assigned_agent_id", $agentId)
                            ->get(["$table.id", "$table.complex_id"]);
                        foreach ($rows as $row) {
                            $units[$kind][(int) $row->complex_id][] = (int) $row->id;
                            $unitComplexes->push((int) $row->complex_id);
                        }
                    }
                }
            }
            $full = $full->map(fn ($id) => (int) $id)->unique();
            // Properties reached only through units: just those units.
            foreach ($unitComplexes->unique()->diff($full) as $complexId) {
                $this->unitScope[$complexId] = [
                    'residential' => $units['residential'][$complexId] ?? [],
                    'commercial' => $units['commercial'][$complexId] ?? [],
                ];
            }
            $this->complexIds = $full->merge(array_keys($this->unitScope))->unique()->values()->all();
            $this->loaded = true;
        }
        return $this->complexIds;
    }

    /**
     * Unit-only properties of the current member and the units they may see there
     * ([] when the member is not limited, or works every property they reach in full).
     *
     * @return array<int, array{residential: int[], commercial: int[]}>
     */
    public function unitScope(): array
    {
        if ($this->complexIds() === null) {
            return [];
        }
        return $this->unitScope;
    }

    /** Whether a specific unit is visible (always true when not limited or the property is open in full). */
    public function allowsUnit(?int $complexId, string $kind, ?int $unitId): bool
    {
        if (!$this->allows($complexId)) {
            return false;
        }
        $scope = $this->unitScope()[(int) $complexId] ?? null;
        return $scope === null || ($unitId !== null && in_array((int) $unitId, $scope[$kind] ?? [], true));
    }

    /** The CRM agent profile id of the current user (leads and tasks assigned to it stay visible). */
    public function agentId(): ?int
    {
        if (!$this->user) {
            return null;
        }
        if (!$this->agentLoaded) {
            $id = DB::table('agents')->where('user_id', $this->user->id)->whereNull('deleted_at')->value('id');
            $this->agentId = $id ? (int) $id : null;
            $this->agentLoaded = true;
        }
        return $this->agentId;
    }

    public function allows($complexId): bool
    {
        $ids = $this->complexIds();
        return $ids === null || ($complexId !== null && in_array((int) $complexId, $ids, true));
    }

    /** Stop the request when a limited team member refers to a property that is not assigned to them. */
    public function assertComplex($complexId): void
    {
        if ($complexId === null || $complexId === '' || $this->allows($complexId)) {
            return;
        }
        abort(403, 'This property is not assigned to you. Ask an administrator to assign it if you need to work on it.');
    }

    /**
     * Add the assigned-property filter to a query builder (for raw / joined queries). With unit columns
     * (['residential' => 'tm.residential_unit_id', 'commercial' => 'tm.commercial_unit_id']) properties a
     * member reaches only through units are limited to those units too.
     */
    public function constrain(BuilderContract $query, string $complexColumn, array $unitColumns = []): BuilderContract
    {
        $ids = $this->complexIds();
        if ($ids === null) {
            return $query;
        }
        $unitOnly = $unitColumns ? $this->unitScope() : [];
        if (!$unitOnly) {
            return $query->whereIn($complexColumn, $ids ?: [0]);
        }
        $full = array_values(array_diff($ids, array_keys($unitOnly)));
        return $query->where(function ($w) use ($complexColumn, $unitColumns, $full, $unitOnly) {
            $w->whereIn($complexColumn, $full ?: [0]);
            foreach ($unitOnly as $complexId => $units) {
                foreach (['residential', 'commercial'] as $kind) {
                    if (!empty($units[$kind]) && !empty($unitColumns[$kind])) {
                        $w->orWhere(fn ($u) => $u->where($complexColumn, $complexId)->whereIn($unitColumns[$kind], $units[$kind]));
                    }
                }
            }
        });
    }

    /** Run internal lookups (validation, totals across the business) without the assignment limit. */
    public function unrestricted(callable $callback): mixed
    {
        $this->paused++;
        try {
            return $callback();
        } finally {
            $this->paused--;
        }
    }

    /** Assign a property to a limited member who just created it, so they can keep working on it. */
    public function claimCreated(int $complexId): void
    {
        if (!$this->restricted()) {
            return;
        }
        DB::table('complex_user')->insertOrIgnore([
            'complex_id' => $complexId, 'user_id' => $this->user->id, 'assigned_by' => $this->user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->forget();
    }
}
