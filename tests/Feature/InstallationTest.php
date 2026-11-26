<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\{Artisan, DB, Schema};
use Tests\TestCase;

/** A new installation from the baseline migrations: reference data is idempotent and one administrator is created. */
class InstallationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'install_test', 'database.connections.install_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Artisan::call('migrate', ['--force' => true]);
    }

    private function counts(): array
    {
        return collect(['roles', 'site_sections', 'property_types', 'history_categories', 'lead_task_templates', 'expense_types', 'legal_types', 'supports', 'descriptions'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }

    public function test_the_baseline_builds_every_table_and_reference_data_can_be_seeded_again(): void
    {
        $this->assertTrue(Schema::hasTable('tenant_management'));
        $this->assertFalse(Schema::hasTable('subscriptions'), 'retired tables are not created');

        Artisan::call('db:seed', ['--force' => true]);
        $first = $this->counts();
        Artisan::call('db:seed', ['--force' => true]);

        $this->assertSame($first, $this->counts());
        $this->assertSame(['listings', 'sales'], DB::table('site_sections')->orderBy('key')->pluck('key')->all());
        $this->assertSame(['admin', 'business', 'support'], DB::table('roles')->orderBy('name')->pluck('name')->all());
    }

    public function test_install_creates_one_administrator_who_must_change_the_password(): void
    {
        $this->artisan('erty:install', ['--email' => 'Owner@Example.test', '--business' => 'Demo Homes'])->assertSuccessful();
        $this->artisan('erty:install', ['--email' => 'second@example.test'])->assertSuccessful();

        $this->assertSame(1, DB::table('users')->count());
        $user = DB::table('users')->first();
        $this->assertSame('owner@example.test', $user->email);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertSame('Demo Homes', DB::table('businesses')->value('business_name'));
        $this->assertSame(['admin', 'business'], DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->orderBy('name')->pluck('name')->all());
        $this->assertFalse((bool) $user->is_sub_account, 'the administrator owns the business');
        $this->assertSame(count(\App\Services\Access\StarterRoles::definitions()), DB::table('business_roles')->where('business_id', $user->id)->count(), 'every starter role is offered');
    }
}
