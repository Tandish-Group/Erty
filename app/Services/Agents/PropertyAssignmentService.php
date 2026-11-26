<?php

namespace App\Services\Agents;

use App\Services\Access\PropertyAccess;

use App\Models\Complex;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Which team members manage which properties (complex_user). Changes only touch the properties the
 * person making them can see, so a member limited to assigned properties never removes assignments
 * on properties outside their reach.
 */
class PropertyAssignmentService
{
    public function __construct(private PropertyAccess $access)
    {
    }

    /** Active team members of the business who can be assigned to properties. */
    public function assignableMembers()
    {
        return User::with('businessRole')->where('is_sub_account', true)->where('main_account_user_id', business_owner_id())
            ->where(fn ($q) => $q->whereNull('is_suspended')->orWhere('is_suspended', false))
            ->orderBy('first_name')->orderBy('last_name')->get();
    }

    /** Set the properties a team member manages (within the properties the actor can see). */
    public function setForMember(User $member, array $complexIds): void
    {
        $visible = Complex::forBusiness()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $wanted = array_values(array_unique(array_map('intval', $complexIds)));
        if ($unknown = array_diff($wanted, $visible)) {
            throw ValidationException::withMessages(['complex_ids' => 'Choose properties of this business that you can see.']);
        }

        DB::transaction(function () use ($member, $visible, $wanted) {
            DB::table('complex_user')->where('user_id', $member->id)->whereIn('complex_id', $visible)->whereNotIn('complex_id', $wanted ?: [0])->delete();
            $this->attach($wanted, [$member->id]);
        });
        $this->access->forget();
    }

    /** Set the team members who manage a property. */
    public function setForComplex(Complex $complex, array $userIds): void
    {
        $members = $this->assignableMembers()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $wanted = array_values(array_unique(array_map('intval', $userIds)));
        if (array_diff($wanted, $members)) {
            throw ValidationException::withMessages(['user_ids' => 'Choose active team members of this business.']);
        }

        DB::transaction(function () use ($complex, $wanted) {
            // Suspended members keep their assignments until someone removes them from the Team page.
            $active = $this->assignableMembers()->pluck('id')->all();
            DB::table('complex_user')->where('complex_id', $complex->id)->whereIn('user_id', $active ?: [0])->whereNotIn('user_id', $wanted ?: [0])->delete();
            $this->attach([$complex->id], $wanted);
        });
        $this->access->forget();
    }

    private function attach(array $complexIds, array $userIds): void
    {
        $rows = [];
        foreach ($complexIds as $complexId) {
            foreach ($userIds as $userId) {
                $rows[] = ['complex_id' => $complexId, 'user_id' => $userId, 'assigned_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()];
            }
        }
        if ($rows) DB::table('complex_user')->insertOrIgnore($rows);
    }
}
