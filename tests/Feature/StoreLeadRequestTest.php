<?php

namespace Tests\Feature;

use App\Http\Requests\StoreLeadRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class StoreLeadRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'lead_req', 'database.connections.lead_req' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::create('lead_profiles', function (Blueprint $t) { $t->id(); $t->string('email')->nullable(); $t->string('business_email')->nullable(); $t->timestamps(); });
        Schema::create('leads', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('lead_profile_id'); $t->softDeletes(); $t->timestamps(); });
        DB::table('lead_profiles')->insert([['id' => 1, 'email' => 'amina@x.test'], ['id' => 2, 'email' => 'mine@x.test']]);
        DB::table('leads')->insert([['user_id' => 20, 'lead_profile_id' => 1], ['user_id' => 10, 'lead_profile_id' => 2]]);
        $this->actingAs((new User)->forceFill(['id' => 10, 'is_sub_account' => false]));
    }

    private function errors(array $data): array
    {
        $request = StoreLeadRequest::createFrom(Request::create('/customer_relationship/lead_management', 'POST', $data + [
            'lead_type' => 'Individual', 'source' => 'walk_in', 'property_type' => 'Apartment',
        ]));
        $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => auth()->user());
        try {
            $request->validateResolved();
            return [];
        } catch (HttpResponseException $e) {
            $this->assertSame(422, $e->getResponse()->getStatusCode());
            return array_keys($e->getResponse()->getData(true)['errors']);
        }
    }

    public function test_an_email_another_business_uses_is_fine_but_not_one_of_our_own(): void
    {
        $person = ['profile_mode' => 'new', 'first_name' => 'Amina', 'last_name' => 'K', 'phone' => '0700'];
        $this->assertSame([], $this->errors($person + ['email' => 'amina@x.test']));
        $this->assertSame(['email'], $this->errors($person + ['email' => 'mine@x.test']));
    }

    public function test_an_existing_profile_needs_no_personal_details_but_must_be_ours(): void
    {
        $this->assertSame([], $this->errors(['profile_mode' => 'existing', 'existing_profile_id' => 2]));
        $this->assertSame(['existing_profile_id'], $this->errors(['profile_mode' => 'existing', 'existing_profile_id' => 1]));
    }
}
