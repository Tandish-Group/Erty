<?php

namespace App\Models\Scopes;

use App\Services\Access\PropertyAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Limits property-linked records to the signed-in member's assigned properties (see PropertyAccess). */
class AssignedPropertiesScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $access = app(PropertyAccess::class);
        $ids = $access->complexIds();
        if ($ids === null) {
            return;
        }
        $model->restrictToProperties($builder, $ids, $access->agentId(), (int) $access->user()->id);
    }
}
