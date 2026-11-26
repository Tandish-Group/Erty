<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Complex;
use App\Models\Lead;
use App\Models\TenantManagement;
use App\Models\User;
use App\Services\Crm\LeadPipeline;
use Illuminate\Support\Facades\{Artisan, DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The CRM home and the document centre on the demo business: the figures follow the shared lead rules, each
 * kind of document needs the right that opens it in its own section, and limited members see only their
 * properties' files.
 */
class CrmPagesTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'crm_test', 'database.connections.crm_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'mail.default' => 'array']);
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
        User::query()->update(['must_change_password' => false]);
        $this->owner = User::where('email', 'owner@demo.erty.test')->firstOrFail();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function member(string $role): User
    {
        return User::where('email', 'test.' . Str::slug($role, '.') . '@erty.test')->firstOrFail();
    }

    public function test_crm_home_shows_the_pipeline_and_what_needs_attention(): void
    {
        $lead = Lead::withoutGlobalScopes()->where('user_id', $this->owner->id)->whereNotIn('status', LeadPipeline::CLOSED_STATUSES)
            ->whereNull('converted_tenant_id')->whereNull('converted_sale_id')->firstOrFail();
        $lead->forceFill(['last_contacted_at' => now()->subDays(30), 'follow_up_date' => null, 'priority' => 'hot'])->save();

        $page = $this->actingAs($this->owner)->get('/customer_relationship')->assertOk();
        $page->assertSee('Pipeline today')->assertSee('Leads needing attention')->assertSee('Overdue follow-ups')
            ->assertSee($lead->fresh()->full_name)->assertSee('Not contacted for 30 days');

        $this->actingAs($this->owner);
        $open = LeadPipeline::open()->count();
        $page->assertViewHas('metrics', fn ($m) => $m['open'] === $open && $m['attention'] >= 1 && $m['hot'] >= 1);

        $this->actingAs($this->member('Caretaker'))->get('/customer_relationship')->assertForbidden();
    }

    public function test_document_centre_lists_files_from_each_section_and_downloads_them(): void
    {
        $tenancy = TenantManagement::forBusiness($this->owner->id)->orderBy('id')->firstOrFail();
        Storage::disk('public')->put('docs/lease.pdf', '%PDF-1.4 lease');
        $tenancy->forceFill(['uploaded_documents' => [['path' => 'docs/lease.pdf', 'original_name' => 'Signed lease.pdf', 'size' => 14, 'mime_type' => 'application/pdf', 'uploaded_at' => now()->toDateTimeString()]]])->save();
        $agent = Agent::where('created_by', $this->owner->id)->orderBy('id')->firstOrFail();
        Storage::disk('public')->put('agents/resumes/cv.pdf', '%PDF-1.4 cv');
        $agent->forceFill(['resume' => 'agents/resumes/cv.pdf'])->save();
        $tenancy->forceFill(['uploaded_documents' => $tenancy->uploaded_documents + [1 => ['path' => 'docs/gone.pdf', 'original_name' => 'Lost scan.pdf']]])->save();

        $page = $this->actingAs($this->owner)->get('/customer_relationship/document-center')->assertOk();
        $page->assertSee('Signed lease.pdf')->assertSee('CV, ')->assertSee('Lost scan.pdf')->assertSee('Missing from storage')
            ->assertSee('Tenancy files')->assertSee('Payment receipts')->assertSee('Agent CVs');

        $this->get('/customer_relationship/document-center?q=lease')->assertSee('Signed lease.pdf')->assertDontSee('CV, ');
        $this->get('/customer_relationship/document-center?source=agent')->assertSee('CV, ')->assertDontSee('Signed lease.pdf');

        $download = $this->get(route('document-center.file', ['source' => 'tenancy', 'id' => $tenancy->id, 'index' => 0]))->assertOk();
        $this->assertStringContainsString('Signed lease.pdf', (string) $download->headers->get('content-disposition'));
        $this->get(route('document-center.file', ['source' => 'agent', 'id' => $agent->id, 'index' => 0]))->assertOk();
        $this->get(route('document-center.file', ['source' => 'tenancy', 'id' => $tenancy->id, 'index' => 1]))->assertSessionHas('not_available'); // recorded but missing from storage
        $this->get(route('document-center.file', ['source' => 'tenancy', 'id' => $tenancy->id, 'index' => 7]))->assertSessionHas('not_available');
    }

    public function test_document_centre_follows_the_role_and_the_member_properties(): void
    {
        // The leasing agent may list tenancy files, legal documents and agent CVs, but not receipts or expenses.
        $leasing = $this->member('Leasing / CRM Agent');
        $tenancies = TenantManagement::forBusiness($this->owner->id)->orderBy('id')->get();
        $mine = $tenancies->first();
        $other = $tenancies->firstWhere('complex_id', '!=', $mine->complex_id) ?? $tenancies->last();
        $hiddenComplex = Complex::withoutGlobalScopes()->whereKeyNot($mine->complex_id)->value('id');
        DB::table('tenant_management')->where('id', $other->id)->update(['complex_id' => $hiddenComplex]);

        // Limit them to the first tenancy's property only; others are handed to an agent so they are not team-wide.
        DB::table('complex_user')->where('user_id', $leasing->id)->delete();
        DB::table('complex_user')->insert(['user_id' => $leasing->id, 'complex_id' => $mine->complex_id]);
        DB::table('complexes')->where('id', '!=', $mine->complex_id)->update(['management_type' => 'agent', 'assigned_agent_id' => Agent::value('id')]);

        foreach ([[$mine, 'Visible file.pdf'], [$other, 'Hidden file.pdf']] as [$t, $name]) {
            Storage::disk('public')->put('docs/' . Str::slug($name) . '.pdf', 'x');
            DB::table('tenant_management')->where('id', $t->id)->update(['uploaded_documents' => json_encode([['path' => 'docs/' . Str::slug($name) . '.pdf', 'original_name' => $name]])]);
        }

        $page = $this->actingAs($leasing)->get('/customer_relationship/document-center')->assertOk();
        $page->assertSee('Tenancy files')->assertSee('Agent CVs')->assertSee('Legal library')
            ->assertDontSee('Payment receipts')->assertDontSee('Expense evidence')
            ->assertSee('Visible file.pdf')->assertDontSee('Hidden file.pdf');

        $this->get(route('document-center.file', ['source' => 'tenancy', 'id' => $mine->id, 'index' => 0]))->assertOk();
        $this->get(route('document-center.file', ['source' => 'tenancy', 'id' => $other->id, 'index' => 0]))->assertNotFound();
        $this->get('/customer_relationship/document-center?source=receipt')->assertOk()->assertSee('All documents'); // an unknown tab falls back to all

        // Without the CRM right the page is refused; downloads also need the source's own right.
        $this->actingAs($this->member('Caretaker'))->get('/customer_relationship/document-center')->assertForbidden();
        $finance = $this->member('Finance Officer'); // no CRM right in the starter roles
        $this->actingAs($finance)->get(route('document-center.file', ['source' => 'tenancy', 'id' => $mine->id, 'index' => 0]))->assertForbidden();
    }
}
