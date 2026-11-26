<?php

namespace Tests\Feature;

use App\Models\{Complex, ComplexResidentialUnit, ComplexUnit, Listing};
use App\Services\Access\ModuleService;
use App\Services\Listings\UnitListingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Cache, DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Listings per unit type: drafts follow the property's unit types and commercial spaces, availability
 * is counted from units and tenancies, and the public API shows only what renters may see.
 */
class UnitListingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'listings.api.rate_limit' => 1000, 'database.default' => 'unit_listing_test', 'database.connections.unit_listing_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        app()->forgetScopedInstances();
        Cache::flush();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('first_name')->nullable(); $t->string('email')->nullable(); $t->string('account_type')->nullable();
            $t->boolean('is_suspended')->default(false); $t->boolean('delete_account')->default(false); $t->boolean('is_sub_account')->default(false);
            $t->unsignedBigInteger('main_account_user_id')->nullable(); $t->timestamps();
        });
        Schema::create('site_sections', function (Blueprint $t) { $t->id(); $t->string('key')->unique(); $t->string('label')->nullable(); $t->string('description')->nullable(); $t->boolean('is_enabled')->default(true); $t->timestamps(); });
        Schema::create('blocked_ips', function (Blueprint $t) { $t->id(); $t->string('ip_address'); $t->timestamps(); }); // global BlockIp middleware
        Schema::create('businesses', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->json('modules')->nullable(); $t->timestamp('suspended_at')->nullable(); $t->timestamps(); });
        Schema::create('complexes', function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->string('slug')->nullable(); $t->string('ref_code')->nullable(); $t->string('type')->nullable();
            $t->string('email')->nullable(); $t->string('mobile')->nullable(); $t->string('principal')->nullable();
            $t->string('county')->nullable(); $t->string('county_specific')->nullable(); $t->text('location_description')->nullable();
            $t->string('latitude')->nullable(); $t->string('longitude')->nullable(); $t->string('setup_status')->default('draft');
            $t->unsignedBigInteger('user_id'); $t->timestamps();
        });
        Schema::create('complex_amenities', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('complex_id'); $t->string('amenity_type'); $t->timestamps(); });
        Schema::create('complex_units', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('complex_id'); $t->string('unit_category')->default('apartment'); $t->string('unit_type')->nullable();
            $t->integer('bedrooms')->nullable(); $t->integer('bathrooms')->nullable(); $t->integer('unit_subdivision')->nullable();
            $t->integer('unit_count'); $t->decimal('sq_ft'); $t->timestamps();
            $t->integer('storeys')->nullable(); $t->decimal('plot_size')->nullable(); $t->string('plot_unit')->nullable(); $t->string('offer')->default('rent'); $t->decimal('sale_price')->nullable(); $t->json('features')->nullable();
        });
        Schema::create('complex_residential_units', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('complex_id'); $t->foreignId('complex_unit_id')->constrained('complex_units')->cascadeOnDelete();
            $t->string('unit_number')->nullable(); $t->string('unit_category')->default('apartment'); $t->integer('bedrooms')->default(0); $t->integer('bathrooms')->default(0);
            $t->string('status')->default('available'); $t->timestamps();
            $t->string('house_type')->nullable(); $t->string('offer')->default('rent'); $t->decimal('sale_price')->nullable();
        });
        Schema::create('commercial_units', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('complex_id'); $t->string('unit_number'); $t->decimal('unit_sq_ft'); $t->integer('max_subdivisions')->default(0);
            $t->decimal('min_sq_ft'); $t->string('floors')->nullable(); $t->string('custom_floors')->nullable(); $t->decimal('monthly_rent')->nullable();
            $t->string('status'); $t->text('description')->nullable(); $t->timestamps();
        });
        Schema::create('tenant_management', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('complex_id')->nullable(); $t->unsignedBigInteger('residential_unit_id')->nullable(); $t->unsignedBigInteger('commercial_unit_id')->nullable();
            $t->string('status'); $t->decimal('occupied_sq_area')->nullable(); $t->decimal('sq_area')->nullable(); $t->timestamps();
        });
        Schema::create('listings', function (Blueprint $t) {
            $t->id(); $t->text('name'); $t->string('ref')->unique(); $t->text('slug'); $t->text('category'); $t->text('type'); $t->text('county')->nullable();
            $t->text('class')->nullable(); $t->text('furnishing')->nullable(); $t->string('bedrooms')->nullable(); $t->integer('bathrooms')->nullable(); $t->integer('sq_area')->nullable();
            $t->integer('amount')->nullable(); $t->integer('deposit')->nullable(); $t->text('property_description')->nullable(); $t->text('status')->default('Draft');
            $t->timestamp('published_at')->nullable(); $t->text('availability')->nullable(); $t->unsignedBigInteger('complex_id')->nullable();
            $t->foreignId('complex_unit_id')->nullable()->unique()->constrained('complex_units')->cascadeOnDelete();
            $t->foreignId('commercial_unit_id')->nullable()->unique()->constrained('commercial_units')->cascadeOnDelete();
            $t->unsignedBigInteger('user_id')->nullable(); $t->string('currency')->default('KES'); $t->integer('units')->default(1); $t->timestamps();
            $t->string('offer')->default('rent'); $t->decimal('sale_price')->nullable();
        });
        Schema::create('image_uploads', function (Blueprint $t) { $t->id(); $t->string('image'); $t->string('type')->nullable(); $t->unsignedBigInteger('listing_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps(); });

        DB::table('users')->insert([['id' => 10, 'first_name' => 'Owner', 'email' => 'owner@x.test'], ['id' => 20, 'first_name' => 'Other', 'email' => 'other@x.test']]);
        DB::table('businesses')->insert([['user_id' => 10], ['user_id' => 20]]);
    }

    /** A confirmed property with a 2-bedroom type (3 flats), a 4-bedroom type (2 flats) and two commercial spaces. */
    private function property(int $owner = 10, string $status = 'ready'): Complex
    {
        $id = DB::table('complexes')->insertGetId([
            'title' => 'Garden Court', 'slug' => 'garden-court-' . $owner, 'type' => 'Apartment, Commercial Unit', 'county' => 'Nairobi', 'county_specific' => 'Kilimani',
            'location_description' => 'Off Argwings Kodhek Road', 'latitude' => '-1.29', 'longitude' => '36.78', 'email' => 'manager@private.test', 'mobile' => '+254700000000',
            'principal' => 'Private Person', 'setup_status' => $status, 'user_id' => $owner,
        ]);
        foreach ([[2, 2, 3, 850], [4, 3, 2, 1600]] as [$bed, $bath, $count, $sqft]) {
            $type = DB::table('complex_units')->insertGetId(['complex_id' => $id, 'bedrooms' => $bed, 'bathrooms' => $bath, 'unit_count' => $count, 'sq_ft' => $sqft]);
            foreach (range(1, $count) as $n) {
                DB::table('complex_residential_units')->insert(['complex_id' => $id, 'complex_unit_id' => $type, 'unit_number' => "{$bed}B-{$n}", 'bedrooms' => $bed, 'bathrooms' => $bath]);
            }
        }
        DB::table('commercial_units')->insert([
            ['complex_id' => $id, 'unit_number' => 'G01', 'unit_sq_ft' => 1000, 'min_sq_ft' => 1000, 'max_subdivisions' => 0, 'monthly_rent' => 90000, 'status' => 'Available'],
            ['complex_id' => $id, 'unit_number' => 'G02', 'unit_sq_ft' => 500, 'min_sq_ft' => 500, 'max_subdivisions' => 0, 'monthly_rent' => null, 'status' => 'Available'],
        ]);
        DB::table('complex_amenities')->insert([['complex_id' => $id, 'amenity_type' => 'parking'], ['complex_id' => $id, 'amenity_type' => 'security']]);
        return Complex::withoutGlobalScopes()->find($id);
    }

    private function service(): UnitListingService
    {
        return app(UnitListingService::class);
    }

    private function listingFor(int $bedrooms): Listing
    {
        $types = ComplexUnit::withoutGlobalScopes()->where('bedrooms', $bedrooms)->pluck('id');
        return Listing::withoutGlobalScopes()->whereIn('complex_unit_id', $types)->firstOrFail();
    }

    private function ready(Listing $listing, array $extra = []): Listing
    {
        $listing->forceFill($extra + ['amount' => 45000, 'deposit' => 45000, 'property_description' => 'Bright corner flats.'])->save();
        $this->service()->publish($listing->refresh());
        return $listing->refresh();
    }

    // ─── Drafts ──────────────────────────────────────────────────────────────

    public function test_a_confirmed_property_gets_one_draft_per_unit_type_and_commercial_space(): void
    {
        $complex = $this->property();

        $this->assertSame(4, $this->service()->sync($complex));
        $this->assertSame(0, $this->service()->sync($complex), 'syncing again creates nothing');

        $listings = Listing::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame(['2-bedroom, 2-bathroom apartment', '4-bedroom, 3-bathroom apartment', 'Commercial space G01', 'Commercial space G02'], $listings->pluck('name')->all());
        $this->assertSame([3, 2, 1, 1], $listings->pluck('units')->all());
        $this->assertTrue($listings->every(fn ($l) => $l->status === Listing::DRAFT && $l->user_id === 10 && $l->complex_id === $complex->id));
        $this->assertSame(90000, $listings[2]->amount, 'a space\'s recorded rent is the starting rent');
        $this->assertCount(4, $listings->pluck('ref')->unique());
    }

    public function test_draft_properties_get_no_listings_and_staff_entries_survive_a_resync(): void
    {
        $this->assertSame(0, $this->service()->sync($this->property(10, 'draft')));
        $this->assertSame(0, Listing::withoutGlobalScopes()->count());

        $complex = $this->property(20);
        $this->service()->sync($complex);
        $listing = $this->listingFor(2);
        $listing->forceFill(['amount' => 50000, 'deposit' => 0, 'property_description' => 'Ours'])->save();

        DB::table('complex_units')->where('id', $listing->complex_unit_id)->update(['unit_count' => 4, 'sq_ft' => 900]);
        $this->service()->sync($complex);

        $listing->refresh();
        $this->assertSame([4, 900, 50000, 0, 'Ours'], [$listing->units, $listing->sq_area, $listing->amount, $listing->deposit, $listing->property_description]);
    }

    public function test_removing_a_unit_type_removes_its_listing(): void
    {
        $this->service()->sync($this->property());
        $listing = $this->listingFor(4);

        DB::table('complex_units')->where('id', $listing->complex_unit_id)->delete();

        $this->assertNull(Listing::withoutGlobalScopes()->find($listing->id));
    }

    // ─── Availability ────────────────────────────────────────────────────────

    public function test_availability_counts_vacant_units_of_the_type_only(): void
    {
        $complex = $this->property();
        $this->service()->sync($complex);
        $two = $this->listingFor(2);
        $units = DB::table('complex_residential_units')->where('complex_unit_id', $two->complex_unit_id)->orderBy('id')->pluck('id');

        $this->assertSame(3, $this->service()->availableUnits($two));

        DB::table('tenant_management')->insert(['complex_id' => $complex->id, 'residential_unit_id' => $units[0], 'status' => 'Active']);
        DB::table('tenant_management')->insert(['complex_id' => $complex->id, 'residential_unit_id' => $units[1], 'status' => 'Terminated']);
        DB::table('complex_residential_units')->where('id', $units[2])->update(['status' => 'maintenance']);

        $this->assertSame(1, $this->service()->availableUnits($two), 'one let, one under maintenance; an ended tenancy does not hold a unit');
        $this->assertSame(2, $this->service()->availableUnits($this->listingFor(4)));
    }

    public function test_a_commercial_space_is_available_while_it_has_room(): void
    {
        $complex = $this->property();
        $this->service()->sync($complex);
        $space = DB::table('commercial_units')->where('unit_number', 'G01')->first();
        $listing = Listing::withoutGlobalScopes()->where('commercial_unit_id', $space->id)->first();

        $this->assertSame(1, $this->service()->availableUnits($listing));
        DB::table('tenant_management')->insert(['complex_id' => $complex->id, 'commercial_unit_id' => $space->id, 'status' => 'Active', 'occupied_sq_area' => 1000]);
        $this->assertSame(0, $this->service()->availableUnits($listing));
    }

    public function test_the_sql_filter_agrees_with_the_counts(): void
    {
        $complex = $this->property();
        $this->service()->sync($complex);
        $g01 = DB::table('commercial_units')->where('unit_number', 'G01')->value('id');
        DB::table('tenant_management')->insert(['complex_id' => $complex->id, 'commercial_unit_id' => $g01, 'status' => 'Active', 'occupied_sq_area' => 1000]);
        $four = $this->listingFor(4);
        DB::table('complex_residential_units')->where('complex_unit_id', $four->complex_unit_id)->update(['status' => 'occupied']);

        $all = Listing::withoutGlobalScopes()->get();
        $counted = collect($this->service()->availability($all))->filter()->keys()->sort()->values()->all();
        $filtered = $this->service()->whereAvailable(Listing::withoutGlobalScopes())->orderBy('id')->pluck('id')->all();

        $this->assertSame($counted, $filtered);
        $this->assertCount(2, $filtered, 'the 2-bedroom type and G02');
    }

    // ─── Publishing ──────────────────────────────────────────────────────────

    public function test_publishing_needs_rent_deposit_and_a_description(): void
    {
        $this->service()->sync($this->property());
        $listing = $this->listingFor(2);

        try {
            $this->service()->publish($listing);
            $this->fail('an incomplete listing was published');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('monthly rent', $e->errors()['listing'][0]);
            $this->assertStringContainsString('deposit', $e->errors()['listing'][0]);
            $this->assertStringContainsString('description', $e->errors()['listing'][0]);
        }

        $listing = $this->ready($listing, ['deposit' => 0]);
        $this->assertTrue($listing->isPublished(), 'a deposit of 0 is allowed');
        $this->assertNotNull($listing->published_at);

        $this->service()->unpublish($listing);
        $this->assertSame(Listing::UNPUBLISHED, $listing->refresh()->status);
    }


    // ─── Public API ──────────────────────────────────────────────────────────

    public function test_the_api_lists_only_published_listings_with_free_units_and_no_private_data(): void
    {
        $complex = $this->property();
        $this->service()->sync($complex);
        $published = $this->ready($this->listingFor(2), ['furnishing' => 'Furnished']);
        $this->ready($this->listingFor(4));
        DB::table('complex_residential_units')->where('complex_unit_id', $this->listingFor(4)->complex_unit_id)->update(['status' => 'occupied']); // none free
        DB::table('image_uploads')->insert(['image' => 'users/10/listings/x/a.jpg', 'type' => 'listing', 'listing_id' => $published->id, 'user_id' => 10]);

        $response = $this->getJson('/api/v1/listings')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ref', $published->ref)
            ->assertJsonPath('data.0.title', '2-bedroom, 2-bathroom apartment')
            ->assertJsonPath('data.0.category', 'residential')
            ->assertJsonPath('data.0.unit.bedrooms', 2)
            ->assertJsonPath('data.0.unit.furnishing', 'Furnished')
            ->assertJsonPath('data.0.rent.amount', 45000)
            ->assertJsonPath('data.0.available_units', 3)
            ->assertJsonPath('data.0.property.name', 'Garden Court')
            ->assertJsonPath('data.0.property.area', 'Kilimani')
            ->assertJsonPath('data.0.property.facilities', ['Parking', 'Security'])
            ->assertJsonPath('meta.total', 1);
        $this->assertStringEndsWith('/storage/users/10/listings/x/a.jpg', $response->json('data.0.photos.0.url'));

        $body = $response->getContent();
        foreach (['manager@private.test', '+254700000000', 'Private Person', 'owner@x.test', '"user_id"', '"complex_id"', '"id"'] as $private) {
            $this->assertStringNotContainsString($private, $body, "the API must not expose {$private}");
        }
    }

    public function test_the_api_hides_drafts_draft_properties_switched_off_and_suspended_businesses(): void
    {
        $this->service()->sync($this->property());
        $listing = $this->ready($this->listingFor(2));
        $this->getJson('/api/v1/listings/' . $listing->ref)->assertOk()->assertJsonPath('data.ref', $listing->ref);

        $this->getJson('/api/v1/listings/' . $this->listingFor(4)->ref)->assertNotFound(); // a draft

        DB::table('complexes')->update(['setup_status' => 'draft']);
        $this->getJson('/api/v1/listings')->assertJsonCount(0, 'data');
        DB::table('complexes')->update(['setup_status' => 'ready']);

        app(ModuleService::class)->setForBusiness(10, ModuleService::LISTINGS, false);
        Cache::flush(); app()->forgetScopedInstances();
        $this->getJson('/api/v1/listings')->assertJsonCount(0, 'data');
        app(ModuleService::class)->setForBusiness(10, ModuleService::LISTINGS, true);
        Cache::flush(); app()->forgetScopedInstances();

        DB::table('users')->where('id', 10)->update(['is_suspended' => true]);
        $this->getJson('/api/v1/listings')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/listings/' . $listing->ref)->assertNotFound();
    }

    public function test_the_api_filters_and_validates(): void
    {
        $this->service()->sync($this->property());
        $this->ready($this->listingFor(2), ['amount' => 40000]);
        $this->ready($this->listingFor(4), ['amount' => 90000]);
        $g02 = Listing::withoutGlobalScopes()->where('name', 'Commercial space G02')->first();
        $this->ready($g02, ['amount' => 60000]);

        $titles = fn (string $query) => collect($this->getJson('/api/v1/listings?' . $query)->assertOk()->json('data'))->pluck('title')->all();

        $this->assertSame(['Commercial space G02'], $titles('category=commercial'));
        $this->assertSame(['4-bedroom, 3-bathroom apartment'], $titles('min_bedrooms=3'));
        $this->assertSame(['2-bedroom, 2-bathroom apartment', 'Commercial space G02'], $titles('max_rent=60000&sort=rent_asc'));
        $this->assertSame([], $titles('county=Mombasa'));
        $this->assertCount(3, $titles('area=kilim'));
        $this->assertSame(2, $this->getJson('/api/v1/listings?per_page=2')->json('meta.last_page'));

        $this->getJson('/api/v1/listings?category=villa')->assertStatus(422);
        $this->getJson('/api/v1/listings?per_page=500')->assertStatus(422);
    }

    /** An estate with a maisonette type (3 houses, for rent or sale) and a villa (1 house, for sale only). */
    private function estate(): Complex
    {
        $id = DB::table('complexes')->insertGetId([
            'title' => 'Runda Estate', 'slug' => 'runda', 'type' => 'House', 'county' => 'Nairobi', 'county_specific' => 'Runda',
            'location_description' => 'Runda Drive', 'latitude' => '-1.21', 'longitude' => '36.8', 'email' => 'm@x.test', 'mobile' => '+254700000001',
            'principal' => 'Owner', 'setup_status' => 'ready', 'user_id' => 10,
        ]);
        foreach ([['Maisonette', 4, 3, 'both', 25000000], ['Villa', 5, 1, 'sale', 60000000]] as [$kind, $bed, $count, $offer, $price]) {
            $type = DB::table('complex_units')->insertGetId(['complex_id' => $id, 'unit_category' => 'house', 'unit_type' => $kind, 'bedrooms' => $bed, 'bathrooms' => 3, 'unit_count' => $count, 'sq_ft' => 2400, 'offer' => $offer, 'sale_price' => $price, 'features' => json_encode(['garden'])]);
            foreach (range(1, $count) as $n) {
                DB::table('complex_residential_units')->insert(['complex_id' => $id, 'complex_unit_id' => $type, 'unit_number' => "{$kind}-{$n}", 'unit_category' => 'house', 'house_type' => $kind, 'bedrooms' => $bed, 'bathrooms' => 3, 'offer' => $offer, 'sale_price' => $price]);
            }
        }
        return Complex::withoutGlobalScopes()->find($id);
    }

    public function test_house_listings_carry_their_offer_and_the_api_filters_houses_for_sale(): void
    {
        $this->service()->sync($this->estate());
        $maisonette = Listing::withoutGlobalScopes()->where('name', '4-bedroom Maisonette')->firstOrFail();
        $villa = Listing::withoutGlobalScopes()->where('name', '5-bedroom Villa')->firstOrFail();
        $this->assertSame(['House', 'both', 25000000.0], [$maisonette->type, $maisonette->offer, $maisonette->sale_price]);
        $this->assertSame('sale', $villa->offer);

        $villa->forceFill(['property_description' => 'A villa on an acre.', 'sale_price' => null])->save();
        $this->assertSame(['the asking price'], $villa->refresh()->missingForPublishing(), 'a house for sale needs a price, not rent or a deposit');
        $villa->forceFill(['sale_price' => 60000000])->save();
        $this->service()->publish($villa->refresh());
        $this->ready($maisonette, ['amount' => 150000]);

        // One maisonette is let: it no longer counts as free to let, and it is not for sale either.
        DB::table('tenant_management')->insert(['complex_id' => $maisonette->complex_id, 'residential_unit_id' => ComplexResidentialUnit::withoutGlobalScopes()->where('unit_number', 'Maisonette-1')->value('id'), 'status' => 'Active']);
        $this->assertSame(['rent' => 2, 'sale' => 2], $this->service()->offerCounts([$maisonette])[$maisonette->id]);

        $titles = fn (string $query) => collect($this->getJson('/api/v1/listings?' . $query)->assertOk()->json('data'))->pluck('title')->all();
        $this->assertSame(['4-bedroom Maisonette', '5-bedroom Villa'], $titles('offer=sale&sort=price_asc'));
        $this->assertSame(['4-bedroom Maisonette'], $titles('offer=rent'));
        $this->assertSame(['5-bedroom Villa'], $titles('house_type=villa'));
        $this->assertSame(['5-bedroom Villa'], $titles('property_type=house&min_price=30000000'));

        $row = collect($this->getJson('/api/v1/listings?property_type=house')->json('data'))->keyBy('title');
        $this->assertSame(['house', 'sale', null], [$row['5-bedroom Villa']['property_type'], $row['5-bedroom Villa']['offer'], $row['5-bedroom Villa']['rent']]);
        $this->assertSame(['price' => 60000000, 'currency' => 'KES'], $row['5-bedroom Villa']['sale']);
        $this->assertSame(['Villa', ['Garden']], [$row['5-bedroom Villa']['unit']['house_type'], $row['5-bedroom Villa']['unit']['features']]);
        $this->assertSame([2, 2], [$row['4-bedroom Maisonette']['available_to_let'], $row['4-bedroom Maisonette']['available_for_sale']]);
        $this->assertSame(150000, $row['4-bedroom Maisonette']['rent']['amount'], 'the rent key stays for older clients');

        // With Sales switched off for the business, houses for sale only are not listed and sale prices are not shown.
        DB::table('businesses')->where('user_id', 10)->update(['modules' => json_encode(['sales' => false])]);
        app()->forgetInstance(ModuleService::class); // business switches are remembered per request
        $this->assertSame(['4-bedroom Maisonette'], $titles('property_type=house'));
        $this->assertNull(collect($this->getJson('/api/v1/listings')->json('data'))->firstWhere('title', '4-bedroom Maisonette')['sale']);
    }

    public function test_the_api_is_rate_limited(): void
    {
        config(['listings.api.rate_limit' => 2]);
        $this->getJson('/api/v1/listings')->assertOk();
        $this->getJson('/api/v1/listings')->assertOk();
        $this->getJson('/api/v1/listings')->assertStatus(429);
    }

    public function test_the_api_is_off_when_the_installation_has_no_listings(): void
    {
        app(ModuleService::class)->setInstallation(ModuleService::LISTINGS, false);
        Cache::flush(); app()->forgetScopedInstances();

        $this->getJson('/api/v1/listings')->assertNotFound();
    }
}
