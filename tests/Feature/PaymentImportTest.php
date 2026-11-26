<?php

namespace Tests\Feature;

use App\Models\TenantInvoice;
use App\Models\TenantManagement;
use App\Models\TenantPayment;
use App\Models\User;
use App\Services\Rent\TenantAccountService;
use App\Services\Tenancy\BulkUploadService;
use Illuminate\Support\Facades\{Artisan, DB};
use Tests\TestCase;

/**
 * Importing payments from a file: the example file, staging, the review page's checks, and importing onto the
 * running account (oldest invoice first, extra as credit, not-cleared payments waiting).
 */
class PaymentImportTest extends TestCase
{
    private User $owner;

    private const HEADERS = ['tenancy_ref', 'tenant_profile_ref', 'tenant_email', 'payment_date', 'amount', 'payment_method',
        'transaction_reference', 'invoice_number', 'cleared', 'payment_notes'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'payimport_test', 'database.connections.payimport_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'mail.default' => 'array']);
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
        User::query()->update(['must_change_password' => false]);
        $this->owner = User::where('email', 'owner@demo.erty.test')->firstOrFail();
        $this->actingAs($this->owner);
    }

    /** Stage rows the way the upload does (the browser sends a column for each field). */
    private function stage(array $rows): string
    {
        $map = ['tenancy_ref' => 0, 'tenant_profile_ref' => 1, 'tenant_email' => 2, 'payment_date' => 3, 'amount_paid' => 4,
            'payment_method' => 5, 'transaction_reference' => 6, 'invoice_id' => 7, 'cleared' => 8, 'payment_notes' => 9];
        $batch = 'BATCH_PAYMENTS_TEST_' . $this->owner->id;
        app(BulkUploadService::class)->storeBulkDataInTempTable('payments', $rows,
            collect($map)->map(fn ($i) => ['columnIndex' => $i])->all(), $batch, $this->owner->id);

        return $batch;
    }

    private function tenancyOwing(): TenantManagement
    {
        $accounts = app(TenantAccountService::class);
        $tenancy = TenantManagement::forBusiness($this->owner->id)->where('status', 'Active')->get()
            ->first(fn ($t) => $accounts->outstanding($t) > 0);
        if (!$tenancy) {
            $tenancy = TenantManagement::forBusiness($this->owner->id)->where('status', 'Active')->firstOrFail();
            app(\App\Services\Rent\TenantChargesService::class)->charge($tenancy, 'adjustment', 1000, 'Test charge', today());
        }

        return $tenancy;
    }

    public function test_the_example_file_has_the_running_account_columns(): void
    {
        $csv = $this->get('/tenant_management/bulk-upload/template/payments')->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', array_filter(explode("\n", trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv)))));
        $this->assertSame(self::HEADERS, $lines[0]);
        $this->assertStringContainsString('TMEXAMPLE01', $csv);
        $this->assertStringNotContainsString('payment_status', $csv);
        $this->assertStringNotContainsString('lease_amount', $csv);
    }

    public function test_reviewed_payments_import_onto_the_running_account(): void
    {
        $tenancy = $this->tenancyOwing();
        $accounts = app(TenantAccountService::class);
        $owed = $accounts->outstanding($tenancy);
        $oldest = $accounts->openInvoices($tenancy)->first();
        $creditBefore = $accounts->credit($tenancy);

        $batch = $this->stage([
            [$tenancy->tenancy_ref, '', '', today()->toDateString(), (string) ($owed + 500), 'M-Pesa', 'IMPORT001', '', 'yes', 'Paid in full and a bit'],
            [$tenancy->tenancy_ref, '', '', today()->toDateString(), '2000', 'cheque', 'CHQ77', '', 'no', 'Cheque'],
            [$tenancy->tenancy_ref, '', '', today()->toDateString(), '100', 'cash', 'IMPORT001', '', 'yes', 'Same reference twice'],
            ['TMNOSUCH00', '', '', today()->toDateString(), '100', 'cash', 'X1', '', 'yes', 'Unknown tenancy'],
            [$tenancy->tenancy_ref, '', '', today()->addDays(3)->toDateString(), '100', 'cash', 'X2', '', 'yes', 'Future date'],
        ]);

        $page = $this->get('/tenant_management/bulk-upload/mapping')->assertOk()->assertSee('Payments to import');
        $page->assertSee('3 cannot be imported')->assertSee('appears twice in this file')->assertSee('No tenancy TMNOSUCH00')->assertSee('is in the future');
        $page->assertSee('Ready, waiting to clear')->assertSee('Oldest unpaid first');

        $ids = DB::table('temp_tenant_payments')->where('batch_id', $batch)->orderBy('row_number')->pluck('id')->all();
        $response = $this->postJson('/tenant_management/bulk-upload/confirm', [
            'type' => 'payment', 'selected_ids' => $ids, 'id_batch_map' => array_fill_keys($ids, $batch),
        ])->assertOk();
        $this->assertSame(2, $response->json('result.success'));
        $this->assertSame(3, $response->json('result.failed'));

        $paid = TenantPayment::where('transaction_reference', 'IMPORT001')->firstOrFail();
        $this->assertSame('mobile_money', $paid->payment_method);
        $this->assertEqualsWithDelta(0, $accounts->outstanding($tenancy->fresh()), 0.01, 'the payment settles what was owed');
        $this->assertEqualsWithDelta($creditBefore + 500, $accounts->credit($tenancy->fresh()), 0.01, 'the extra stays as credit');
        $this->assertTrue(DB::table('tenant_invoice_payment_allocations')->where('payment_id', $paid->id)->where('invoice_id', $oldest->id)->exists());
        $this->assertSame(TenantPayment::PENDING, TenantPayment::where('transaction_reference', 'CHQ77')->value('payment_status'));
        $this->assertSame(3, DB::table('temp_tenant_payments')->where('batch_id', $batch)->where('processing_status', 'failed')->count(), 'rows not imported stay with their reason');
    }

    public function test_an_invoice_of_another_tenant_is_refused_and_a_named_invoice_is_paid_first(): void
    {
        $tenancy = $this->tenancyOwing();
        $other = TenantInvoice::whereNotIn('tenancy_id', app(TenantAccountService::class)->chainIds($tenancy))->value('invoice_number');
        $own = app(TenantAccountService::class)->openInvoices($tenancy)->last();

        $rows = [[$tenancy->tenancy_ref, '', '', today()->toDateString(), '10', 'cash', 'NAMED1', $own->invoice_number, 'yes', '']];
        if ($other) {
            $rows[] = [$tenancy->tenancy_ref, '', '', today()->toDateString(), '10', 'cash', 'OTHER1', $other, 'yes', ''];
        }
        $batch = $this->stage($rows);
        $ids = DB::table('temp_tenant_payments')->where('batch_id', $batch)->orderBy('row_number')->pluck('id')->all();
        $this->postJson('/tenant_management/bulk-upload/confirm', ['type' => 'payment', 'selected_ids' => $ids, 'id_batch_map' => array_fill_keys($ids, $batch)])->assertOk();

        $named = TenantPayment::where('transaction_reference', 'NAMED1')->firstOrFail();
        $this->assertTrue(DB::table('tenant_invoice_payment_allocations')->where('payment_id', $named->id)->where('invoice_id', $own->id)->exists());
        if ($other) {
            $this->assertFalse(TenantPayment::where('transaction_reference', 'OTHER1')->exists());
            $this->assertStringContainsString('is not one of this tenant', DB::table('temp_tenant_payments')->where('transaction_reference', 'OTHER1')->value('error_message'));
        }
    }

    public function test_reconciliation_links_to_the_example_file_and_the_import(): void
    {
        $this->get('/tenant_management/payment-reconciliation')->assertOk()
            ->assertSee('/tenant_management/bulk-upload/template/payments', false)->assertSee('/tenant_management?import=payments', false);
        $caretaker = User::where('email', 'test.caretaker@erty.test')->firstOrFail();
        $this->actingAs($caretaker)->get('/tenant_management/bulk-upload/template/payments')->assertForbidden();
    }
}
