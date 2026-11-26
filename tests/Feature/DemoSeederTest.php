<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\{Artisan, DB};
use Tests\TestCase;

/** The demo business is built through Erty's own services, so this exercises the main flows end to end. */
class DemoSeederTest extends TestCase
{
    public function test_the_demo_builds_a_working_business_once(): void
    {
        config(['database.default' => 'demo_test', 'database.connections.demo_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'mail.default' => 'array']);
        Artisan::call('migrate', ['--force' => true]);

        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

        $this->assertSame(1, DB::table('businesses')->count());
        $this->assertSame(3, DB::table('complexes')->where('setup_status', 'ready')->count(), 'every property is set up and confirmed');
        $this->assertSame(5, DB::table('tenant_management')->where('status', 'Active')->count());
        $this->assertGreaterThan(5, DB::table('tenant_invoices')->count(), 'rent is billed monthly since the start');
        $this->assertGreaterThan(0, DB::table('commission_entries')->count(), 'agents earn commission on rent collected');
        $this->assertSame(1, DB::table('property_sales')->count());
        $this->assertGreaterThan(0, DB::table('listings')->where('status', 'Published')->count());
        $this->assertGreaterThan(1, DB::table('users')->count(), 'a team member per starter role');

        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
        $this->assertSame(1, DB::table('businesses')->count(), 'a second run changes nothing');
    }
}
