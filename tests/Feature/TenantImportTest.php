<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Tenancies\TenantImportController;
use App\Models\Complex;
use App\Models\ComplexResidentialUnit;
use App\Models\TenantInvoice;
use App\Models\TenantLease;
use App\Models\TenantManagement;
use App\Models\TenantNotice;
use App\Models\TenantPayment;
use App\Models\User;
use App\Services\Rent\TenantAccountService;
use App\Services\Tenancy\BulkUploadService;
use Illuminate\Support\Facades\{Artisan, DB, Mail};
use Tests\TestCase;

/**
 * Importing tenants with their tenancies, and notices already given: the example files, the checks on the review
 * page, and importing a tenancy that started before Erty (no past billing, opening balance, deposit held, no
 * e-mails, no commission).
 */
class TenantImportTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'tenimport_test', 'database.connections.tenimport_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'mail.default' => 'array']);
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
        User::query()->update(['must_change_password' => false]);
        $this->owner = User::where('email', 'owner@demo.erty.test')->firstOrFail();
        $this->actingAs($this->owner);
    }

    /** A free residential unit at a property that is set up. */
    protected function freeUnit(): ComplexResidentialUnit
    {
        foreach (ComplexResidentialUnit::query()->where('status', 'available')->get() as $unit) {
            $complex = Complex::find($unit->complex_id);
            if ($complex && $complex->setup_status === 'ready' && !$unit->activeTenancies()->exists() && $unit->forRent()) {
                return $unit;
            }
        }
        $this->markTestSkipped('The demo data has no free residential unit.');
    }

    protected function row(array $values): array
    {
        return array_map(fn ($column) => (string) ($values[$column] ?? ''), TenantImportController::TENANT_COLUMNS);
    }

    protected function stage(string $type, array $rows, array $columns): string
    {
        $batch = 'BATCH_' . strtoupper($type) . '_TEST_' . $this->owner->id;
        $map = collect($columns)->mapWithKeys(fn ($c, $i) => [$c => ['columnIndex' => $i]])->all();
        app(BulkUploadService::class)->storeBulkDataInTempTable($type, $rows, $map, $batch, $this->owner->id);

        return $batch;
    }

    private function confirm(string $type, string $table, string $batch)
    {
        $ids = DB::table($table)->where('batch_id', $batch)->orderBy('row_number')->pluck('id')->all();

        return $this->postJson('/tenant_management/bulk-upload/confirm', ['type' => $type, 'selected_ids' => $ids, 'id_batch_map' => array_fill_keys($ids, $batch)])->assertOk();
    }

    public function test_example_files_describe_tenancies_and_notices(): void
    {
        $tenants = $this->get('/tenant_management/bulk-upload/template/tenants')->assertOk()->streamedContent();
        $this->assertStringContainsString(implode(',', TenantImportController::TENANT_COLUMNS), $tenants);
        $this->assertStringContainsString('Example Gardens', $tenants);
        $notices = $this->get('/tenant_management/bulk-upload/template/notices')->assertOk()->streamedContent();
        $this->assertStringContainsString('tenancy_ref,tenant_profile_ref,tenant_email,notice_type,notice_date,effective_date', $notices);
        $this->assertStringNotContainsString('priority_level', $notices);
    }

    public function test_a_tenancy_from_before_erty_is_imported_without_billing_the_past(): void
    {
        Mail::fake();
        $unit = $this->freeUnit();
        $complex = Complex::find($unit->complex_id);
        $start = now()->subMonths(8)->startOfMonth();
        $batch = $this->stage('tenants', [$this->row([
            'tenant_type' => 'Individual', 'tenant_name' => 'Imported Tenant', 'tenant_email' => 'imported.tenant@example.com',
            'tenant_phone' => '+254700111222', 'tenant_id_number' => '99887766',
            'property' => $complex->title, 'unit_number' => $unit->unit_number, 'monthly_rent' => '40,000', 'payment_frequency' => 'Monthly',
            'start_date' => $start->format('d/m/Y'), 'deposit' => '40000', 'deposit_held' => '40000', 'balance_owed' => '15000', 'currency' => 'KES',
        ])], TenantImportController::TENANT_COLUMNS);

        $this->get('/tenant_management/bulk-upload/mapping')->assertOk()->assertSee('Tenants and tenancies')->assertSee('Imported Tenant')
            ->assertSee('1 ready')->assertSee('Rent billed from');
        $result = $this->confirm('tenant', 'temp_tenant_profiles', $batch);
        $this->assertSame(1, $result->json('result.success'), json_encode($result->json('result.errors')));

        $tenancy = TenantManagement::where('residential_unit_id', $unit->id)->where('status', 'Active')->firstOrFail();
        $accounts = app(TenantAccountService::class);
        $this->assertSame('Imported Tenant', $tenancy->tenantProfile->tenant_name);
        $this->assertTrue(TenantLease::where('tenant_management_id', $tenancy->id)->where('status', 'active')->exists());
        $this->assertSame(0, TenantInvoice::where('tenancy_id', $tenancy->id)->where('kind', 'rent')->whereDate('billing_period_start', '<', now()->startOfMonth())->count(), 'past periods are not billed');
        $this->assertSame(1, TenantInvoice::where('tenancy_id', $tenancy->id)->where('kind', 'adjustment')->where('amount', 15000)->count(), 'the balance owed is one opening invoice');
        $this->assertEqualsWithDelta(40000, $accounts->depositHeld($tenancy), 0.01, 'the deposit held is recorded');
        $this->assertSame(0, TenantPayment::where('tenant_id', $tenancy->id)->where('entry_type', 'payment')->count(), 'no money is reported as received today');
        $this->assertSame(0, DB::table('commission_entries')->where('tenancy_id', $tenancy->id)->count(), 'no commission on money held before Erty');
        Mail::assertNothingOutgoing();
        $this->assertSame(0, DB::table('temp_tenant_profiles')->where('batch_id', $batch)->count());
    }

    public function test_problems_are_explained_and_block_the_row(): void
    {
        $unit = $this->freeUnit();
        $complex = Complex::find($unit->complex_id);
        $let = TenantManagement::forBusiness($this->owner->id)->where('status', 'Active')->whereNotNull('residential_unit_id')->firstOrFail();
        $base = ['tenant_type' => 'Individual', 'tenant_phone' => '+254700000000', 'monthly_rent' => '1000', 'start_date' => '2026-01-01'];
        $this->stage('tenants', [
            $this->row(array_merge($base, ['tenant_name' => 'A', 'tenant_email' => 'a@example.com', 'tenant_id_number' => '1', 'property' => 'No Such Place', 'unit_number' => 'X1'])),
            $this->row(array_merge($base, ['tenant_name' => 'B', 'tenant_email' => 'b@example.com', 'tenant_id_number' => '2', 'property' => $let->complex_name, 'unit_number' => $let->unit_number])),
            $this->row(array_merge($base, ['tenant_name' => 'C', 'tenant_email' => 'c@example.com', 'tenant_id_number' => '3', 'property' => $complex->title, 'unit_number' => $unit->unit_number])),
            $this->row(array_merge($base, ['tenant_name' => 'D', 'tenant_email' => 'd@example.com', 'tenant_id_number' => '4', 'property' => $complex->title, 'unit_number' => $unit->unit_number])),
            $this->row(array_merge($base, ['tenant_name' => 'E', 'tenant_email' => 'e@example.com', 'tenant_id_number' => '5', 'property' => $complex->title, 'unit_number' => $unit->unit_number, 'start_date' => '31/02/2026'])),
            $this->row(['tenant_type' => 'Individual', 'tenant_name' => 'F', 'property' => $complex->title, 'unit_number' => $unit->unit_number, 'start_date' => '2026-01-01']),
        ], TenantImportController::TENANT_COLUMNS);

        $this->get('/tenant_management/bulk-upload/mapping')->assertOk()
            ->assertSee('No property called &quot;No Such Place&quot;', false)
            ->assertSee('already has a tenant in Erty')
            ->assertSee('appears twice in this file')
            ->assertSee('Start date &quot;31/02/2026&quot; is not a date', false)
            ->assertSee('No e-mail, phone, ID number')
            ->assertSee('1 ready')->assertSee('5 cannot be imported');
    }

    public function test_an_existing_tenant_is_reused_and_a_negative_balance_becomes_credit(): void
    {
        $unit = $this->freeUnit();
        $complex = Complex::find($unit->complex_id);
        $profile = TenantManagement::forBusiness($this->owner->id)->where('status', 'Active')->firstOrFail()->tenantProfile;
        $batch = $this->stage('tenants', [$this->row([
            'tenant_type' => $profile->tenant_type, 'tenant_name' => $profile->tenant_name, 'tenant_email' => $profile->tenant_email,
            'business_name' => $profile->business_name, 'business_email' => $profile->business_email,
            'property' => $complex->title, 'unit_number' => $unit->unit_number, 'monthly_rent' => '20000', 'start_date' => now()->subMonths(2)->toDateString(),
            'deposit' => '0', 'balance_owed' => '-3000',
        ])], TenantImportController::TENANT_COLUMNS);

        $this->get('/tenant_management/bulk-upload/mapping')->assertSee('Uses the existing tenant ' . $profile->tenant_ref);
        $this->assertSame(1, $this->confirm('tenant', 'temp_tenant_profiles', $batch)->json('result.success'));
        $tenancy = TenantManagement::where('residential_unit_id', $unit->id)->where('status', 'Active')->firstOrFail();
        $this->assertSame($profile->id, $tenancy->tenant_profile_id);
        $this->assertEqualsWithDelta(3000, app(TenantAccountService::class)->credit($tenancy), 0.01);
    }

    public function test_notices_already_given_are_kept_on_the_tenancy(): void
    {
        $tenancy = TenantManagement::forBusiness($this->owner->id)->where('status', 'Active')->firstOrFail();
        $columns = ['tenancy_ref', 'tenant_profile_ref', 'tenant_email', 'notice_type', 'notice_date', 'effective_date', 'notice_subject', 'notice_details', 'notice_period', 'delivery_methods'];
        $batch = $this->stage('notices', [
            [$tenancy->tenancy_ref, '', '', 'Lease termination', now()->subDays(10)->toDateString(), now()->addDays(20)->toDateString(), 'Moving out', '', '', 'email, post'],
            ['TMNOSUCH00', '', '', 'lease_termination', now()->subDays(10)->toDateString(), now()->addDays(20)->toDateString(), '', '', '', ''],
            [$tenancy->tenancy_ref, '', '', 'party', now()->subDays(10)->toDateString(), now()->subDays(20)->toDateString(), '', '', '', ''],
        ], $columns);

        $this->get('/tenant_management/bulk-upload/mapping')->assertOk()->assertSee('Notices')
            ->assertSee('No tenancy TMNOSUCH00')->assertSee('&quot;party&quot; is not a notice type', false)->assertSee('the effective date is before the notice date');
        $result = $this->confirm('notice', 'temp_tenant_notices', $batch);
        $this->assertSame(1, $result->json('result.success'));
        $notice = TenantNotice::where('tenant_id', $tenancy->id)->latest('id')->firstOrFail();
        $this->assertSame('lease_termination', $notice->notice_type);
        $this->assertSame('delivered', $notice->delivery_status);
        $this->assertSame(['email', 'physical'], $notice->delivery_methods);
    }
}
