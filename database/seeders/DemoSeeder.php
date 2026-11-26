<?php

namespace Database\Seeders;

use App\Http\Controllers\Business\Properties\ComplexController;
use App\Http\Controllers\Business\Properties\ComplexWorkflowController;
use App\Http\Controllers\Business\Properties\CommercialUnitController;
use App\Models\Agent;
use App\Models\AgentDepartment;
use App\Models\AgentRole;
use App\Models\AgentWorkLocation;
use App\Models\CommercialUnit;
use App\Models\Complex;
use App\Models\ComplexHistory;
use App\Models\ComplexResidentialUnit;
use App\Models\HistoryCategory;
use App\Models\Lead;
use App\Models\LeadProfile;
use App\Models\TenantManagement;
use App\Models\User;
use App\Services\Access\StarterRoles;
use App\Services\Commission\CommissionAgreementService;
use App\Services\Crm\LeadWorkflowService;
use App\Services\Listings\UnitListingService;
use App\Services\Properties\ComplexSetupService;
use App\Services\Properties\MaintenanceService;
use App\Services\Rent\TenantAccountService;
use App\Services\Sales\PropertySaleService;
use App\Services\Tenancy\TenancyOnboardingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * A demo business to explore Erty with: three properties (an apartment block, an estate of houses and shops, a
 * commercial block), listings, agents with commission agreements, leads with tasks, tenancies with rent paid,
 * a repair, an expense, a house sale on instalments, property history and a team member per starter role.
 *
 *   php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder
 *
 * Runs only in the local and testing environments, on a database without a business. Everything is created
 * through Erty's own services, so it behaves like data entered in the app. Times are relative to today.
 */
class DemoSeeder extends Seeder
{
    private User $owner;

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoSeeder only runs in local or testing environments.');
            return;
        }
        if (DB::table('businesses')->exists()) {
            $this->command?->error('A business already exists. Run the demo on a fresh database: php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder');
            return;
        }

        $this->call(DatabaseSeeder::class);
        $password = Str::password(16, symbols: false);

        // db:seed switches mass-assignment protection off; the app runs with it on, so the services do here too.
        Model::reguard();

        DB::transaction(function () use ($password) {
            $this->owner = $this->createOwner($password);
            Auth::login($this->owner);

            [$riverside, $karura, $upperHill] = [$this->apartmentBlock(), $this->estate(), $this->commercialBlock()];
            $agents = $this->agents();
            $this->listings();
            $this->leads($riverside, $karura, $agents);
            $this->tenancies($riverside, $karura, $upperHill, $agents);
            $this->operations($riverside, $karura);
        });
        Auth::logout();
        Model::unguard();

        // One sign-in per starter role, with property assignments (development tool).
        Artisan::call('erty:test-team', ['--owner' => $this->owner->id]);

        $this->command?->newLine();
        $this->command?->warn('Demo business ready. This password is shown only once:');
        $this->command?->line('  Email:    ' . $this->owner->email);
        $this->command?->line('  Password: ' . $password);
        $this->command?->line('Sign-in sends a 6-digit code by email; with MAIL_MAILER=log it is written to storage/logs/laravel.log.');
    }

    // ─── People ─────────────────────────────────────────────────────────────

    private function createOwner(string $password): User
    {
        $owner = User::forceCreate([
            'first_name' => 'Amani', 'last_name' => 'Owner', 'full_name_slug' => 'amani-owner',
            'email' => 'owner@demo.erty.test', 'mobile' => '0700000001', 'account_type' => 'Business',
            'password' => Hash::make($password), 'must_change_password' => false, 'is_sub_account' => false,
        ]);
        DB::table('businesses')->insert([
            'user_id' => $owner->id, 'business_name' => 'Amani Homes (demo)', 'business_email' => $owner->email,
            'account_type' => 'company', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $owner->assignRole(Role::findOrCreate('business', 'web'));
        app(StarterRoles::class)->ensureFor($owner->id);

        return $owner->fresh();
    }

    /** @return Agent[] keyed by first name */
    private function agents(): array
    {
        $department = AgentDepartment::forceCreate(['name' => 'Leasing', 'description' => 'Letting and tenant placement', 'created_by' => $this->owner->id, 'is_active' => true]);
        $role = AgentRole::forceCreate(['name' => 'Leasing agent', 'level' => 'mid', 'department_id' => $department->id, 'created_by' => $this->owner->id, 'is_active' => true]);
        $office = AgentWorkLocation::forceCreate(['name' => 'Westlands office', 'type' => 'office', 'city' => 'Nairobi', 'country' => 'Kenya', 'created_by' => $this->owner->id, 'is_active' => true]);

        $agents = [];
        foreach ([['Wanjiku', 'Mwangi', 'wanjiku.agent@demo.erty.test'], ['Otieno', 'Ouma', 'otieno.agent@demo.erty.test']] as [$first, $last, $email]) {
            $agents[$first] = Agent::forceCreate([
                'first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => '07' . random_int(10000000, 99999999),
                'agent_type' => 'employee', 'employment_type' => 'full_time', 'start_date' => now()->subYear()->toDateString(),
                'department_id' => $department->id, 'role_id' => $role->id, 'work_location_id' => $office->id,
                'commission_type' => 'sales_value', 'commission_rate' => 5, 'status' => 'active', 'is_active' => true,
                'created_by' => $this->owner->id,
            ]);
        }

        // 5% of rent collected on the tenancies each agent places, effective from the start of the year.
        $agreements = app(CommissionAgreementService::class);
        $agreements->save($this->owner->id, ['entitlement' => 'agent', 'scope' => 'company', 'method' => 'percent_collected', 'rate' => 5, 'effective_from' => now()->startOfYear()->toDateString()]);

        return $agents;
    }

    // ─── Properties ─────────────────────────────────────────────────────────

    private function request(array $data = [], bool $json = true): Request
    {
        $request = Request::create('/demo', 'POST', $data);
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }
        $request->setUserResolver(fn () => Auth::user());
        return $request;
    }

    private function draft(string $title, array $kinds, string $area): Complex
    {
        (new ComplexWorkflowController)->draft($this->request([
            'title' => $title, 'ref_code' => strtoupper(Str::substr(Str::slug($title, ''), 0, 4)) . '-01', 'email' => 'office@demo.erty.test', 'mobile' => '+254700000002',
            'county' => 'Nairobi', 'county_specific' => $area, 'location_description' => "Off the main road in $area", 'description' => "$title, a demo property.",
            'latitude' => -1.26, 'longitude' => 36.8, 'principal' => 1, 'kinds' => $kinds,
        ]));
        return Complex::where('title', $title)->firstOrFail();
    }

    private function fingerprint(Complex $complex, string $step): string
    {
        return app(ComplexSetupService::class)->steps($complex->fresh())[$step]['fingerprint'];
    }

    private function confirm(Complex $complex): Complex
    {
        foreach (app(ComplexSetupService::class)->steps($complex->fresh()) as $key => $step) {
            if (!$step['valid']) {
                continue;
            }
            (new ComplexWorkflowController)->review($this->request(['acknowledged' => 1, 'fingerprint' => $step['fingerprint'],
                'decision' => !empty($step['optional']) ? 'deferred' : 'confirmed', 'note' => 'Reviewed for the demo.']), $complex->slug, $key);
        }
        (new ComplexController)->confirmSetup($this->request(['review_acknowledged' => 1], false), $complex->slug);
        return $complex->fresh();
    }

    private function apartmentBlock(): Complex
    {
        $complex = $this->draft('Riverside Apartments', ['apartments'], 'Westlands');
        $workflow = new ComplexWorkflowController;
        $workflow->generate($this->request(['bedrooms' => 2, 'bathrooms' => 2, 'sq_ft' => 950, 'unit_count' => 6, 'fingerprint' => $this->fingerprint($complex, 'residential')]), $complex->slug);
        $workflow->generate($this->request(['bedrooms' => 3, 'bathrooms' => 2, 'sq_ft' => 1300, 'unit_count' => 2, 'fingerprint' => $this->fingerprint($complex, 'residential')]), $complex->slug);
        foreach (ComplexResidentialUnit::where('complex_id', $complex->id)->orderBy('id')->get() as $i => $unit) {
            $workflow->refineResidential($this->request(['unit_number' => chr(65 + intdiv($i, 4)) . '-' . (101 + $i % 4), 'floor' => intdiv($i, 4) + 1, 'sq_ft' => $unit->sq_ft,
                'fingerprint' => $this->fingerprint($complex, 'residential')]), $complex->slug, $unit->id);
        }
        return $this->confirm($complex);
    }

    private function estate(): Complex
    {
        $complex = $this->draft('Karura Gardens', ['houses', 'commercial'], 'Runda');
        $workflow = new ComplexWorkflowController;
        foreach ([['Maisonette', 4, 'rent', null], ['Bungalow', 3, 'rent', null], ['Villa', 5, 'sale', 38000000]] as [$type, $bedrooms, $offer, $price]) {
            $workflow->generate($this->request(['category' => 'house', 'house_type' => $type, 'bedrooms' => $bedrooms, 'bathrooms' => 3, 'sq_ft' => 2400, 'unit_count' => 1,
                'offer' => $offer, 'sale_price' => $price, 'fingerprint' => $this->fingerprint($complex, 'houses')]), $complex->slug);
        }
        foreach (ComplexResidentialUnit::where('complex_id', $complex->id)->orderBy('id')->get() as $i => $house) {
            $workflow->refineResidential($this->request(['unit_number' => 'H' . ($i + 1), 'sq_ft' => $house->sq_ft, 'fingerprint' => $this->fingerprint($complex, 'houses')]), $complex->slug, $house->id);
        }
        (new CommercialUnitController)->store($this->request(['complex_id' => $complex->id, 'kt_commercial_units_repeater' => [
            ['unit_number' => 'S1', 'unit_sq_ft' => 600, 'min_sq_ft' => 300, 'max_subdivisions' => 1, 'floors' => 'Ground Floor', 'status' => 'Available'],
            ['unit_number' => 'S2', 'unit_sq_ft' => 900, 'min_sq_ft' => 300, 'max_subdivisions' => 2, 'floors' => 'Ground Floor', 'status' => 'Available'],
        ]]));
        return $this->confirm($complex);
    }

    private function commercialBlock(): Complex
    {
        $complex = $this->draft('Upper Hill Business Centre', ['commercial'], 'Upper Hill');
        (new CommercialUnitController)->store($this->request(['complex_id' => $complex->id, 'kt_commercial_units_repeater' => [
            ['unit_number' => 'G-01', 'unit_sq_ft' => 1500, 'min_sq_ft' => 500, 'max_subdivisions' => 3, 'floors' => 'Ground Floor', 'status' => 'Available'],
            ['unit_number' => '1-01', 'unit_sq_ft' => 2000, 'min_sq_ft' => 500, 'max_subdivisions' => 2, 'floors' => 'First Floor', 'status' => 'Available'],
            ['unit_number' => '1-02', 'unit_sq_ft' => 1200, 'min_sq_ft' => 400, 'max_subdivisions' => 1, 'floors' => 'First Floor', 'status' => 'Available'],
        ]]));
        return $this->confirm($complex);
    }

    private function listings(): void
    {
        $service = app(UnitListingService::class);
        $service->syncOwner($this->owner->id);
        foreach (\App\Models\Listing::where('user_id', $this->owner->id)->get() as $i => $listing) {
            $listing->forceFill(['amount' => [45000, 60000, 180000, 120000, 90000][$i % 5], 'deposit' => 45000,
                'property_description' => 'Bright, well kept and close to shops and transport. Demo listing.'])->save();
            if ($i < 3) {
                rescue(fn () => $service->publish($listing->fresh()), report: false);
            }
        }
    }

    // ─── CRM ────────────────────────────────────────────────────────────────

    private function leads(Complex $riverside, Complex $karura, array $agents): void
    {
        $people = [
            ['Mercy', 'Njeri', 'mercy@demo.erty.test', 'Apartment', $riverside, 'Wanjiku', 'hot'],
            ['Brian', 'Kiprop', 'brian@demo.erty.test', 'House', $karura, 'Otieno', 'warm'],
            ['Halima', 'Abdi', 'halima@demo.erty.test', 'Apartment', $riverside, null, 'cold'],
        ];
        foreach ($people as [$first, $last, $email, $type, $complex, $agent, $priority]) {
            $profile = LeadProfile::forceCreate(['lead_type' => 'Individual', 'first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => '0711' . random_int(100000, 999999)]);
            $lead = Lead::forceCreate([
                'user_id' => $this->owner->id, 'lead_profile_id' => $profile->id, 'source' => 'walk_in', 'property_type' => $type,
                'complex_id' => $complex->id, 'priority' => $priority, 'purpose' => 'rent', 'budget_min' => 40000, 'budget_max' => 120000,
                'assigned_agent_id' => $agent ? $agents[$agent]->id : null, 'status' => 'new', 'workflow_phase' => 'qualification',
            ]);
            if ($agent) {
                app(LeadWorkflowService::class)->createPhaseSystemTasks($lead, 'qualification'); // tasks need an agent
            }
        }
    }

    // ─── Tenancies and rent ─────────────────────────────────────────────────

    private function tenancies(Complex $riverside, Complex $karura, Complex $upperHill, array $agents): void
    {
        $onboarding = app(TenancyOnboardingService::class);
        $start = now()->subMonths(3)->startOfMonth()->toDateString();
        $apartments = ComplexResidentialUnit::where('complex_id', $riverside->id)->orderBy('id')->get();
        $houses = ComplexResidentialUnit::where('complex_id', $karura->id)->where('offer', 'rent')->orderBy('id')->get();
        $offices = CommercialUnit::where('complex_id', $upperHill->id)->orderBy('id')->get();

        $people = [
            ['Achieng Otieno', $riverside, $apartments[0], 32000, 'Wanjiku'],
            ['Kamau Njoroge', $riverside, $apartments[1], 32000, 'Wanjiku'],
            ['Fatuma Ali', $riverside, $apartments[4], 45000, 'Otieno'],
            ['Daniel Mutua', $karura, $houses[0], 150000, 'Otieno'],
        ];
        foreach ($people as $i => [$name, $complex, $unit, $rent, $agent]) {
            $onboarding->onboard($this->request([
                'onboarding_confirmed' => 1, 'tenant_option' => 'new', 'tenant_type' => 'Individual',
                'tenant_name' => $name, 'tenant_email' => Str::slug($name, '.') . '@demo.erty.test', 'tenant_phone' => '0722' . random_int(100000, 999999), 'tenant_id_number' => (string) random_int(20000000, 39999999),
                'listing_type' => $unit->unit_category === 'house' ? 'House' : 'Apartment', 'complex' => $complex->slug, 'residential_unit_id' => $unit->id,
                'agreement_type' => 'rental', 'start_date' => $start, 'monthly_payment' => $rent, 'security_deposit' => $rent, 'currency' => 'KES', 'payment_interval' => 'Monthly',
                'assigned_agent_id' => $agents[$agent]->id,
            ]));
        }
        $onboarding->onboard($this->request([
            'onboarding_confirmed' => 1, 'tenant_option' => 'new', 'tenant_type' => 'Business',
            'business_name' => 'Savanna Traders Ltd', 'business_registration' => 'PVT-' . random_int(1000, 9999), 'business_contact_person' => 'Grace Wambui',
            'business_email' => 'accounts@savanna.demo.erty.test', 'business_phone' => '0733' . random_int(100000, 999999),
            'listing_type' => 'Commercial Unit', 'complex' => $upperHill->slug, 'commercial_unit_id' => $offices[0]->id, 'occupied_sq_area' => 800,
            'agreement_type' => 'lease', 'start_date' => $start, 'end_date' => now()->addYears(2)->toDateString(), 'monthly_payment' => 120000, 'security_deposit' => 240000,
            'currency' => 'KES', 'payment_interval' => 'Monthly',
        ]));

        // Bill every month since the start, then record what each tenant paid.
        dispatch_sync(new \App\Jobs\ProcessTenantBilling);
        $account = app(TenantAccountService::class);
        foreach (TenantManagement::forBusiness($this->owner->id)->orderBy('id')->get() as $i => $tenancy) {
            $months = [4, 3, 4, 2, 3][$i] ?? 2;
            for ($m = 0; $m < $months; $m++) {
                $amount = $m === 0 ? (float) $tenancy->monthly_payment + (float) $tenancy->security_deposit : (float) $tenancy->monthly_payment;
                $account->record($tenancy, ['amount' => $amount, 'payment_date' => now()->subMonths(3 - $m)->startOfMonth()->addDays(2)->min(now())->toDateString(),
                    'payment_method' => $i % 2 ? 'bank_transfer' : 'mobile_money', 'transaction_reference' => 'DEMO' . $tenancy->id . $m]);
            }
        }
    }

    // ─── Repairs, expenses, a sale and history ──────────────────────────────

    private function operations(Complex $riverside, Complex $karura): void
    {
        $tenancy = TenantManagement::forBusiness($this->owner->id)->where('complex_id', $riverside->id)->first();
        $repair = app(MaintenanceService::class)->log(['complex_id' => $riverside->id, 'tenancy_id' => $tenancy->id, 'title' => 'Kitchen sink leaking',
            'description' => 'Water pools under the sink after use.', 'category' => 'plumbing', 'priority' => 'high', 'reported_by' => 'Tenant', 'reported_via' => 'phone'], $this->owner->id);
        app(MaintenanceService::class)->complete($repair, 'Replaced the trap and seal.', 3500, 'landlord');

        DB::table('complex_expenses')->insert([
            'user_id' => $this->owner->id, 'complex_id' => $riverside->id, 'description' => 'Security services for the month', 'listing_type' => 'Apartment',
            'transaction_type' => 'expense', 'amount' => 45000, 'expense_date' => now()->subMonth()->toDateString(), 'currency' => 'KES',
            'scope' => 'complex', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $villa = ComplexResidentialUnit::where('complex_id', $karura->id)->where('offer', 'sale')->first();
        if ($villa) {
            $sales = app(PropertySaleService::class);
            $sale = $sales->start($villa, ['buyer_name' => 'Joseph Kariuki', 'buyer_phone' => '+254711000000', 'buyer_email' => 'joseph@demo.erty.test',
                'price' => 38000000, 'agreement_date' => now()->subMonth()->toDateString(), 'deposit_amount' => 7600000, 'deposit_due_date' => now()->subMonth()->toDateString(),
                'plan' => 'equal', 'instalments' => 6, 'first_due_date' => now()->addDays(5)->toDateString(), 'frequency' => 'monthly']);
            $sales->recordPayment($sale, ['amount' => 7600000, 'paid_on' => now()->subMonth()->toDateString(), 'method' => 'bank_transfer', 'reference' => 'DEMO-DEP']);
        }

        $records = [
            [$riverside, 'renovation', ['renovation_type' => 'full_renovation', 'completion_date' => now()->subMonths(6)->toDateString(), 'cost_estimate' => 420000,
                'contractor_info' => 'Bora Painters Ltd'], 'Repainted the stairwells and corridors.'],
            [$karura, 'ownership', ['previous_owner' => 'Kariuki Family Trust', 'ownership_type' => 'freehold', 'ownership_method' => 'purchase',
                'transfer_date' => now()->subYears(2)->toDateString()], 'Bought from the developer.'],
        ];
        foreach ($records as [$complex, $tag, $data, $notes]) {
            ComplexHistory::forceCreate(['complex_id' => $complex->id, 'history_category_id' => HistoryCategory::where('tag', $tag)->value('id'),
                'category_tag' => $tag, 'data' => $data, 'notes' => $notes, 'created_by' => $this->owner->id]);
        }
    }
}
