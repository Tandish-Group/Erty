<?php

namespace Tests\Feature;

use App\Models\Complex;
use App\Models\User;
use App\Services\Rent\TenantReportService;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\Reports\IncomeExpensesReport;
use Illuminate\Support\Facades\{Artisan, DB};
use Tests\TestCase;

/**
 * Reports on the demo business: who can open which report, the figures agree with the tenant account
 * and expense rules, limited team members see only their properties, and every report downloads.
 */
class ReportsTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'reports_test', 'database.connections.reports_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'mail.default' => 'array']);
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
        $this->owner = User::where('email', 'owner@demo.erty.test')->firstOrFail();
        $this->owner->forceFill(['must_change_password' => false])->save();
        User::query()->update(['must_change_password' => false]);
    }

    private function member(string $role): User
    {
        return User::where('email', 'test.' . \Illuminate\Support\Str::slug($role, '.') . '@erty.test')->firstOrFail();
    }

    private function keysFor(User $user): array
    {
        $this->actingAs($user);

        return app(ReportRegistry::class)->availableTo($user)->keys()->all();
    }

    public function test_each_role_gets_the_reports_its_rights_allow(): void
    {
        $this->assertSame(['overview', 'income', 'rent-roll', 'occupancy', 'leases', 'agents', 'sales', 'maintenance'], $this->keysFor($this->owner));

        $finance = $this->keysFor($this->member('Finance Officer'));
        $this->assertContains('income', $finance);
        $this->assertContains('rent-roll', $finance);

        $caretaker = $this->keysFor($this->member('Caretaker'));
        $this->assertContains('occupancy', $caretaker);
        $this->assertContains('maintenance', $caretaker);
        $this->assertNotContains('income', $caretaker, 'no money reports without the reports right');
        $this->assertNotContains('rent-roll', $caretaker);
        $this->assertNotContains('agents', $caretaker);

        $this->actingAs($this->member('Caretaker'))->get('/reports/income')->assertForbidden();
    }

    public function test_reports_have_their_own_group_in_the_role_editor_and_downloads_need_their_own_right(): void
    {
        $group = config('business_route_permissions.reports');
        $this->assertSame(['view', 'financial', 'occupancy', 'agents', 'sales', 'maintenance', 'export'], $group['permissions']);
        $this->assertNotContains('reports', config('business_route_permissions.tenant_management.permissions'), 'the old Tenants "Reports" action is retired');

        $caretaker = $this->member('Caretaker');
        $this->assertSame(['view', 'occupancy', 'maintenance'], $caretaker->businessRole->permissions['reports']);
        $this->actingAs($caretaker)->get('/reports/occupancy')->assertOk()->assertDontSee('format=xlsx');
        $this->get('/reports/occupancy?format=xlsx')->assertForbidden();

        // Taking a report away from a role takes it off the menu and the page.
        $role = $caretaker->businessRole;
        $role->permissions = array_merge($role->permissions, ['reports' => ['view', 'occupancy']]);
        $role->save();
        $this->actingAs($caretaker->fresh())->get('/reports/maintenance')->assertForbidden();
    }

    public function test_existing_roles_keep_exactly_the_reports_they_could_open(): void
    {
        $id = DB::table('business_roles')->insertGetId([
            'business_id' => $this->owner->id, 'user_id' => $this->owner->id, 'name' => 'Old finance', 'is_active' => true,
            'permissions' => json_encode(['tenant_management' => ['view', 'payments', 'reports'], 'agent_management' => ['view']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_11_26_000002_reports_permissions.php');
        $migration->up();
        $migration->up(); // running it again changes nothing

        $permissions = json_decode(DB::table('business_roles')->where('id', $id)->value('permissions'), true);
        $this->assertSame(['view', 'financial', 'occupancy', 'agents', 'export'], $permissions['reports']);
        $this->assertSame(['view', 'payments'], $permissions['tenant_management']);
    }

    public function test_income_figures_follow_the_tenant_account_and_expense_rules(): void
    {
        $this->actingAs($this->owner);
        $filters = ReportFilters::make('last_12_months');
        $result = app(IncomeExpensesReport::class)->build($filters, $this->owner);
        $byProperty = collect($result->sections)->firstWhere('title', 'By property');

        $summary = app(TenantReportService::class)->getSummary($filters->from, $filters->to);
        $this->assertEqualsWithDelta($summary['total_expected'], collect($byProperty['rows'])->sum('billed'), 0.01, 'billed matches the tenant report rules');
        $this->assertEqualsWithDelta($summary['total_collected'], collect($byProperty['rows'])->sum('collected'), 0.01);

        // Repair costs are expenses once: the property expenses already include the closed repair's cost.
        $expenses = (float) DB::table('complex_expenses')->where('transaction_type', 'expense')
            ->where(fn ($q) => $q->whereNull('workflow_status')->orWhereNotIn('workflow_status', ['void', 'returned']))
            ->whereBetween('expense_date', [$filters->from, $filters->to])->sum('amount');
        $this->assertEqualsWithDelta($expenses, collect($byProperty['rows'])->sum('expenses'), 0.01);
        $repairs = (float) DB::table('maintenance_requests')->whereNotNull('expense_id')->sum('cost');
        $this->assertLessThanOrEqual($expenses + 0.01, $repairs, 'repair costs are inside expenses, not added on top');

        foreach ($byProperty['rows'] as $row) {
            $this->assertEqualsWithDelta($row['collected'] - $row['expenses'] - $row['commission'], $row['net'], 0.01);
        }
    }

    public function test_a_member_limited_to_assigned_properties_sees_only_those(): void
    {
        // Narrow the demo caretaker (limited to assigned properties) to one property.
        $caretaker = $this->member('Caretaker');
        $keep = (int) DB::table('complex_user')->where('user_id', $caretaker->id)->orderBy('complex_id')->value('complex_id');
        DB::table('complex_user')->where('user_id', $caretaker->id)->where('complex_id', '!=', $keep)->delete();
        // Company-managed properties are open to the whole team, so hand the others to an agent.
        $agent = (int) DB::table('agents')->value('id');
        DB::table('complexes')->where('id', '!=', $keep)->update(['management_type' => 'agent', 'assigned_agent_id' => $agent]);
        $access = app(\App\Services\Access\PropertyAccess::class);
        $access->activate($caretaker); // the same rules the app applies on each request
        $visible = $access->complexIds();
        $access->deactivate();
        $this->assertSame([$keep], array_values($visible));
        $hidden = Complex::withoutGlobalScopes()->whereKeyNot($keep)->pluck('title')->all();

        foreach (['/reports?period=last_12_months', '/reports/occupancy?period=last_12_months', '/reports/maintenance?period=last_12_months', '/reports/leases'] as $url) {
            $page = $this->actingAs($caretaker)->get($url)->assertOk();
            foreach ($hidden as $title) {
                $page->assertDontSee($title);
            }
        }
    }

    public function test_every_report_renders_and_downloads(): void
    {
        ini_set('memory_limit', '512M'); // fifteen workbooks and PDFs in one process; a request builds one
        $this->actingAs($this->owner)->get('/reports?period=last_12_months')->assertOk()->assertSee('Properties side by side');
        foreach (['income', 'rent-roll', 'occupancy', 'leases', 'agents', 'sales', 'maintenance'] as $key) {
            $this->get("/reports/{$key}?period=last_12_months")->assertOk();
            $xlsx = $this->get("/reports/{$key}?period=last_12_months&format=xlsx")->assertOk();
            $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('content-type'), $key);
            $pdf = $this->get("/reports/{$key}?period=last_12_months&format=pdf")->assertOk();
            $this->assertSame('application/pdf', $pdf->headers->get('content-type'), $key);
        }
        $this->get('/reports/overview?format=xlsx')->assertOk();
        $this->get('/reports/nonsense')->assertSessionHas('not_available');
    }
}
