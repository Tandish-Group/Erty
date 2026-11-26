<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Properties\ComplexHistoryController;
use App\Models\Complex;
use App\Models\ComplexHistory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PropertyHistoryWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'history_test', 'database.connections.history_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Schema::create('complexes', function (Blueprint $table) {
            $table->id(); $table->integer('user_id'); $table->string('slug'); $table->string('title');
            $table->string('county')->nullable(); $table->string('type')->nullable();
        });
        foreach (['complex_residential_units', 'commercial_units'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->integer('complex_id'); $table->string('unit_number');
            });
        }
        Schema::create('history_categories', function (Blueprint $table) {
            $table->id(); $table->string('tag'); $table->string('name'); $table->boolean('is_active');
        });
        Schema::create('complex_histories', function (Blueprint $table) {
            $table->id(); $table->integer('complex_id');
            $table->integer('complex_residential_unit_id')->nullable();
            $table->integer('commercial_unit_id')->nullable();
            $table->integer('history_category_id')->nullable();
            $table->string('category_tag')->nullable(); $table->text('data')->nullable();
            $table->text('files')->nullable(); $table->text('notes')->nullable();
            $table->integer('created_by')->nullable(); $table->timestamps();
        });
        DB::table('complexes')->insert([
            ['id' => 1, 'user_id' => 10, 'slug' => 'ours', 'title' => 'Our property'],
            ['id' => 2, 'user_id' => 20, 'slug' => 'theirs', 'title' => 'Other property'],
        ]);
        DB::table('complex_residential_units')->insert([
            ['id' => 1, 'complex_id' => 1, 'unit_number' => 'R1'],
            ['id' => 2, 'complex_id' => 2, 'unit_number' => 'R2'],
        ]);
        DB::table('history_categories')->insert([
            ['id' => 1, 'tag' => 'structural', 'name' => 'Structural', 'is_active' => true],
            ['id' => 2, 'tag' => 'ownership', 'name' => 'Ownership', 'is_active' => true],
        ]);
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('first_name')->nullable(); $table->string('last_name')->nullable(); });
        DB::table('users')->insert(['id' => 11, 'first_name' => 'Ann', 'last_name' => 'Staff']);
        $user = new User;
        $user->forceFill(['id' => 11, 'is_sub_account' => true, 'main_account_user_id' => 10,
            'business_permissions' => ['listing_history' => ['view', 'create']]]);
        $user->setRelation('businessRole', null);
        $this->actingAs($user);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['complex' => 'ours', 'scope' => 'property', 'confirmed' => '1',
            'history_category' => 'structural', 'structural_type' => 'extension',
            'structural_description' => 'Added room', 'structural_date' => '2026-10-18'], $changes);
    }

    private function assertInvalid(array $changes, string $field, array $files = []): void
    {
        try {
            (new ComplexHistoryController)->store(Request::create('/history', 'POST', $this->payload($changes), [], $files));
            $this->fail('Expected validation failure');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
            $this->assertSame(0, DB::table('complex_histories')->count());
        }
    }

    public function test_subaccount_prefills_only_parent_property(): void
    {
        $data = (new ComplexHistoryController)->index(Request::create('/history', 'GET', ['complex_filter' => 'ours']))->getData();
        $this->assertSame([1], $data['complexes']->pluck('id')->all());
        $this->assertSame('ours', $data['selectedProperty']->slug);
    }

    public function test_confirmation_and_scope_are_required(): void
    {
        $this->assertInvalid(['confirmed' => '0'], 'confirmed');
        $this->assertInvalid(['scope' => ''], 'scope');
        $this->assertInvalid(['scope' => 'residential'], 'residential_unit_id');
    }

    public function test_view_permission_does_not_allow_creation(): void
    {
        auth()->user()->business_permissions = ['listing_history' => ['view']];
        $controller = new ComplexHistoryController;
        $middleware = $controller->getMiddleware()[0]['middleware'];
        $request = Request::create('/history', 'POST');
        $request->setUserResolver(fn () => auth()->user());
        $request->setRouteResolver(fn () => new \Illuminate\Routing\Route('POST', 'history', [
            'uses' => ComplexHistoryController::class . '@store',
            'controller' => ComplexHistoryController::class . '@store',
        ]));
        try {
            $middleware($request, fn () => response('allowed'));
            $this->fail('Expected creation permission denial');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public function test_foreign_property_selection_is_rejected(): void
    {
        try {
            (new ComplexHistoryController)->index(Request::create('/history', 'GET', ['complex_filter' => 'theirs']));
            $this->fail('Expected inaccessible property');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(404, $error->getStatusCode());
        }
    }

    public function test_foreign_units_and_conflicting_scope_are_rejected(): void
    {
        $this->assertInvalid(['scope' => 'residential', 'residential_unit_id' => 2], 'residential_unit_id');
        $this->assertInvalid(['residential_unit_id' => 1], 'residential_unit_id');
        $this->assertInvalid(['scope' => 'residential', 'residential_unit_id' => 1, 'commercial_unit_id' => 1], 'commercial_unit_id');
    }

    public function test_category_and_evidence_validate_before_writes(): void
    {
        $this->assertInvalid(['structural_description' => ''], 'structural_description');
        $this->assertInvalid([], 'structural_docs', ['structural_docs' => UploadedFile::fake()->create('report.pdf', 1)]);
        $this->assertInvalid([], 'structural_docs.0', ['structural_docs' => [UploadedFile::fake()->create('report.pdf', 10241, 'application/pdf')]]);
    }

    public function test_delete_preserves_record(): void
    {
        DB::table('complex_histories')->insert(['id' => 1, 'complex_id' => 1]);
        $response = (new ComplexHistoryController)->destroy(1);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(1, DB::table('complex_histories')->count());
    }

    public function test_confirmed_save_records_parent_property_unit_and_employee_creator(): void
    {
        $request = Request::create('/history', 'POST', $this->payload(['scope' => 'residential', 'residential_unit_id' => 1]));
        $request->headers->set('Accept', 'application/json');
        $response = (new ComplexHistoryController)->store($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData()->success);
        $record = ComplexHistory::firstOrFail();
        $this->assertSame(1, $record->complex_id);
        $this->assertSame(1, $record->complex_residential_unit_id);
        $this->assertNull($record->commercial_unit_id);
        $this->assertSame(11, $record->created_by);
        $this->assertSame('Added room', $record->data['description']);
        $this->assertSame('structural', $record->category_tag);
    }

    public function test_a_form_save_opens_the_new_record(): void
    {
        $response = (new ComplexHistoryController)->store(Request::create('/history', 'POST', $this->payload()));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/history/' . ComplexHistory::firstOrFail()->id, $response->getTargetUrl());
    }

    public function test_only_the_chosen_record_type_is_stored_and_unknown_types_are_refused(): void
    {
        $this->assertInvalid(['history_category' => 'gossip'], 'history_category');
        $this->assertInvalid(['history_category' => 'ownership', 'previous_owner' => 'J. Mwangi', 'ownership_type' => 'freehold', 'ownership_method' => 'purchase',
            'transfer_date' => '2020-01-01', 'ownership_end_date' => '2019-01-01'], 'ownership_end_date');
        $request = Request::create('/history', 'POST', $this->payload(['previous_owner' => 'Someone else']));
        $request->headers->set('Accept', 'application/json');
        (new ComplexHistoryController)->store($request);
        $this->assertEqualsCanonicalizing(['structural_type', 'description', 'completion_date', 'property_value_impact'], array_keys(ComplexHistory::firstOrFail()->data), 'no previous_owner from another type');
    }

    public function test_records_read_back_as_plain_sentences(): void
    {
        $record = new ComplexHistory(['category_tag' => 'ownership', 'data' => ['previous_owner' => 'J. Mwangi', 'ownership_type' => 'leasehold', 'ownership_method' => 'public_auction', 'transfer_date' => '2019-03-04']]);
        $this->assertSame('Leasehold, from J. Mwangi', \App\Support\PropertyHistory::title($record));
        $this->assertSame('2019-03-04', \App\Support\PropertyHistory::eventDate($record)->toDateString());
        $facts = collect(\App\Support\PropertyHistory::facts($record)['facts'])->pluck(1, 0)->all();
        $this->assertSame(['Previous owner or landlord' => 'J. Mwangi', 'Ownership type' => 'Leasehold', 'How ownership passed' => 'Public auction', 'Transfer date' => '4 Mar 2019'], $facts);
        $sale = new ComplexHistory(['category_tag' => 'sales_rental', 'data' => ['record_type' => 'sale_record', 'price_rate' => 75000000, 'date' => '2026-11-18']]);
        $this->assertSame('Sale · KES 75,000,000', \App\Support\PropertyHistory::title($sale));
    }

    public function test_the_timeline_is_ordered_by_when_things_happened_and_filters(): void
    {
        DB::table('complex_histories')->insert([
            ['id' => 1, 'complex_id' => 1, 'category_tag' => 'structural', 'history_category_id' => 1, 'data' => json_encode(['structural_type' => 'extension', 'description' => 'Back wing', 'completion_date' => '2015-06-01']), 'created_at' => '2026-11-01'],
            ['id' => 2, 'complex_id' => 1, 'category_tag' => 'ownership', 'history_category_id' => 2, 'data' => json_encode(['previous_owner' => 'A', 'ownership_type' => 'freehold', 'transfer_date' => '2021-01-01']), 'created_at' => '2026-10-01'],
            ['id' => 3, 'complex_id' => 2, 'category_tag' => 'ownership', 'history_category_id' => 2, 'data' => json_encode(['previous_owner' => 'Theirs', 'transfer_date' => '2024-01-01']), 'created_at' => '2026-10-01'],
        ]);
        $data = fn (array $q) => (new ComplexHistoryController)->index(Request::create('/history', 'GET', $q))->getData();
        $this->assertSame([2, 1], $data([])['records']->pluck('id')->all(), 'newest event first, and never another business\'s');
        $this->assertSame([2], $data(['category' => 'ownership'])['records']->pluck('id')->all());
        $this->assertSame([1], $data(['q' => 'back wing'])['records']->pluck('id')->all());
        $this->assertSame([1], $data(['to' => '2016-01-01'])['records']->pluck('id')->all());
        $this->assertSame(2, $data([])['stats']['total']);
    }
}
