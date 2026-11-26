<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Records owned by a business account (the owner's user id is stored on the row).
 *
 *   Complex::forBusiness()->get();        // the signed-in person's business
 *   Lead::forBusiness($ownerId)->count(); // a given business (jobs, commands)
 *
 * The owner column defaults to `user_id`; a model that stores it elsewhere sets
 * `protected string $businessOwnerColumn = 'managed_by';`.
 *
 * With no owner (nobody signed in and none given) the scope matches nothing rather than rows
 * whose owner is empty. This is separate from AssignedPropertiesScope, which narrows staff to
 * their assigned properties inside the business.
 */
trait BelongsToBusiness
{
    public function businessOwnerColumn(): string
    {
        return property_exists($this, 'businessOwnerColumn') ? $this->businessOwnerColumn : 'user_id';
    }

    public function scopeForBusiness(Builder $query, int|string|null $ownerId = null): Builder
    {
        $ownerId ??= business_owner_id();

        if ($ownerId === null || $ownerId === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn($this->businessOwnerColumn()), $ownerId);
    }
}
