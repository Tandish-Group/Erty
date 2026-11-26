<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Rent\PaymentReconciliationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use \Tests\Concerns\TenantAccountsSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'recon_test', 'database.connections.recon_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        $this->schema();
        DB::table('users')->insert(['id' => 10, 'first_name' => 'Owner', 'email' => 'o@x.test', 'is_sub_account' => false]);
        DB::table('tenant_profiles')->insert([['id' => 1, 'tenant_name' => 'Amani Otieno', 'managed_by' => 10], ['id' => 2, 'tenant_name' => 'Baraka Ltd', 'managed_by' => 10]]);
        $a = $this->tenancy(['tenant_profile_id' => 1, 'tenancy_ref' => 'TMAMANI01', 'currency' => 'UGX']);
        $b = $this->tenancy(['tenant_profile_id' => 2, 'tenancy_ref' => 'TMBARAKA2']);
        $rows = [];
        foreach (range(1, 27) as $i) {
            $rows[] = ['tenant_id' => $i <= 3 ? $a->id : $b->id, 'payment_date' => sprintf('2026-10-%02d', $i), 'amount_paid' => 1000 * $i, 'payment_method' => 'mobile_money', 'transaction_reference' => 'REF-' . $i, 'payment_status' => 'paid', 'receipt_path' => null];
        }
        $rows[] = ['tenant_id' => $a->id, 'payment_date' => '2026-10-28', 'amount_paid' => 5, 'payment_method' => 'cash', 'transaction_reference' => null, 'payment_status' => 'paid', 'receipt_path' => 'already/has/proof.pdf'];
        DB::table('tenant_payments')->insert($rows);
        $this->actingAs(User::find(10));
    }

    private function unmatched(array $query = []): array
    {
        $request = Request::create('/tenant_management/payment-reconciliation/unmatched-payments', 'GET', $query);
        $request->setUserResolver(fn () => auth()->user());
        return app(PaymentReconciliationController::class)->getUnmatchedPayments($request)->getData(true);
    }

    public function test_payments_without_proof_come_newest_first_25_per_page(): void
    {
        $first = $this->unmatched();
        $this->assertSame(['total' => 27, 'page' => 1, 'last_page' => 2, 'from' => 1, 'to' => 25], $first['meta']);
        $this->assertSame('27 Oct 2026', $first['data'][0]['payment_date']);
        $this->assertSame('Mobile money', $first['data'][0]['payment_method']);
        $this->assertSame('KES 27,000.00', $first['data'][0]['amount']);

        $second = $this->unmatched(['page' => 2]);
        $this->assertCount(2, $second['data']);
        $this->assertSame('UGX 2,000.00', $second['data'][0]['amount'], 'each payment shows its tenancy currency');
    }

    public function test_search_ignores_case_and_covers_names_and_references(): void
    {
        $this->assertSame(3, $this->unmatched(['tenant_search' => 'amani'])['meta']['total']);
        $this->assertSame(3, $this->unmatched(['tenant_search' => 'tmamani'])['meta']['total']);
        $this->assertSame(1, $this->unmatched(['tenant_search' => 'ref-27'])['meta']['total']);
    }
}
