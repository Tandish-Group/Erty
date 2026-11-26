<?php

namespace Tests\Feature;

use App\Models\TenantLease;
use App\Models\TenantManagement;
use App\Models\TenantMovement;
use App\Models\User;
use App\Services\Tenancy\TenantMovementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit transfers through the pages on the demo business: choosing a tenancy, requesting a move to a free unit,
 * approving it (which must create a valid new lease), cancelling, and who may do what.
 */
class UnitTransferPagesTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'transfer_test', 'database.connections.transfer_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'mail.default' => 'array']);
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
        User::query()->update(['must_change_password' => false]);
        $this->owner = User::where('email', 'owner@demo.erty.test')->firstOrFail();
    }

    private function member(string $role): User
    {
        return User::where('email', 'test.' . Str::slug($role, '.') . '@erty.test')->firstOrFail();
    }

    /** A residential tenancy with a free unit at its property, and that unit. */
    private function residentialMove(): array
    {
        $this->actingAs($this->owner);
        $service = app(TenantMovementService::class);
        foreach (TenantManagement::forBusiness($this->owner->id)->where('status', 'Active')->whereNotNull('residential_unit_id')->get() as $t) {
            $free = collect($service->getVacantUnits($t->complex_id, 'residential', $t->id))->firstWhere(fn ($u) => (int) $u['id'] !== (int) $t->residential_unit_id);
            if ($free) {
                return [$t, $free];
            }
        }
        $this->markTestSkipped('The demo data has no residential tenancy with a free unit beside it.');
    }

    public function test_pages_open_and_the_form_lists_free_units(): void
    {
        [$tenancy, $unit] = $this->residentialMove();

        $this->get('/tenant_management/movement')->assertOk()->assertSee('Unit transfers')->assertSee('No transfers yet');
        $this->get('/tenant_management/movement/create')->assertOk()->assertSee('Which tenancy is moving?')->assertSee($tenancy->tenancy_ref);
        $this->get('/tenant_management/movement/create?tenancy_id=' . $tenancy->id)->assertOk()
            ->assertSee('Move ' . $tenancy->tenantProfile->display_name)->assertSee($unit['label'])->assertSee('What will happen');

        $preview = $this->postJson('/tenant_management/movement/preview', ['tenancy_id' => $tenancy->id, 'move_type' => 'mid_month', 'transfer_date' => today()->toDateString(), 'new_rent' => 99999, 'new_deposit' => 0])->assertOk();
        $this->assertSame(today()->toDateString(), $preview->json('transfer_date'));
        $this->assertEquals(99999, $preview->json('new_rent'));
    }

    public function test_a_transfer_is_requested_approved_and_creates_a_valid_lease(): void
    {
        [$tenancy, $unit] = $this->residentialMove();

        $this->post('/tenant_management/movement/initiate', [
            'tenancy_id' => $tenancy->id, 'new_unit_id' => $unit['id'], 'move_type' => 'mid_month', 'transfer_date' => today()->toDateString(),
            'new_rent' => 52000, 'new_deposit' => $tenancy->security_deposit, 'furnishing' => 'Furnished', 'number_of_occupants' => 3,
            'parking_action' => 'keep', 'arrears_acknowledged' => 1, 'notes' => 'Needs a bigger unit',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $movement = TenantMovement::where('old_tenancy_id', $tenancy->id)->firstOrFail();
        $this->assertSame('pending', $movement->status);
        $this->assertSame($unit['label'], $movement->new_unit_number);

        // A second request for the same tenancy is refused while the first is open.
        $this->get('/tenant_management/movement/create?tenancy_id=' . $tenancy->id)->assertRedirect('/tenant_management/movement/' . $movement->ref);
        $this->get('/tenant_management/movement/' . $movement->ref)->assertOk()->assertSee('Approve and move now')->assertSee('Needs a bigger unit');

        $this->post('/tenant_management/movement/' . $movement->ref . '/approve')->assertRedirect()->assertSessionHas('success');
        $movement->refresh();
        $this->assertSame('completed', $movement->status);
        $new = TenantManagement::findOrFail($movement->new_tenancy_id);
        $this->assertSame('Transferred', $tenancy->fresh()->status);
        $this->assertSame((int) $unit['id'], (int) $new->residential_unit_id);
        $this->assertSame('Furnished', $new->furnishing);
        $this->assertSame(3, (int) $new->number_of_occupants);
        $lease = TenantLease::where('tenant_management_id', $new->id)->firstOrFail();
        $this->assertContains($lease->lease_type, ['fixed-term', 'month-to-month', 'periodic']);
        $this->assertContains($lease->payment_frequency, ['monthly', 'quarterly', 'semi-annually', 'annually']);
        $this->get('/tenant_management/movement?status=completed')->assertOk()->assertSee($movement->ref);
    }

    public function test_bad_requests_are_refused_with_reasons(): void
    {
        [$tenancy, $unit] = $this->residentialMove();
        $base = ['tenancy_id' => $tenancy->id, 'move_type' => 'mid_month', 'new_rent' => 1000, 'new_deposit' => 0, 'parking_action' => 'keep', 'arrears_acknowledged' => 1];

        $this->post('/tenant_management/movement/initiate', $base + ['new_unit_id' => $tenancy->residential_unit_id, 'transfer_date' => today()->toDateString()])
            ->assertSessionHasErrors('new_unit_id');
        $this->post('/tenant_management/movement/initiate', $base + ['new_unit_id' => $unit['id'], 'transfer_date' => today()->subDay()->toDateString()])
            ->assertSessionHasErrors('transfer_date');
        $this->assertSame(0, TenantMovement::count());
    }

    public function test_cancelling_needs_a_reason_and_keeps_the_tenant_in_place(): void
    {
        [$tenancy, $unit] = $this->residentialMove();
        $this->post('/tenant_management/movement/initiate', [
            'tenancy_id' => $tenancy->id, 'new_unit_id' => $unit['id'], 'move_type' => 'end_of_month', 'new_rent' => 1000, 'new_deposit' => 0,
            'parking_action' => 'keep', 'arrears_acknowledged' => 1,
        ])->assertSessionHasNoErrors();
        $movement = TenantMovement::firstOrFail();
        $this->assertTrue($movement->transfer_date->isFuture(), 'an end-of-period move waits for the next period');

        $this->post('/tenant_management/movement/' . $movement->ref . '/cancel', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post('/tenant_management/movement/' . $movement->ref . '/cancel', ['reason' => 'Tenant changed their mind'])->assertSessionHas('success');
        $this->assertSame('cancelled', $movement->fresh()->status);
        $this->assertSame('Active', $tenancy->fresh()->status);
        $this->get('/tenant_management/movement/' . $movement->ref)->assertOk()->assertSee('Tenant changed their mind')->assertDontSee('Approve and');
    }

    public function test_viewing_needs_tenants_and_moving_needs_the_transfers_right(): void
    {
        [$tenancy] = $this->residentialMove();
        $caretaker = $this->member('Caretaker'); // sees tenants, no transfers right
        $this->actingAs($caretaker)->get('/tenant_management/movement')->assertOk()->assertDontSee('Request a transfer');
        $this->get('/tenant_management/movement/create')->assertForbidden();
        $this->post('/tenant_management/movement/initiate', ['tenancy_id' => $tenancy->id])->assertForbidden();
    }
}
