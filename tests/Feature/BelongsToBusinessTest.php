<?php

namespace Tests\Feature;

use App\Models\{LegalFolder, TenantProfile, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class BelongsToBusinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'scope_test', 'database.connections.scope_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::create('legal_folders', function (Blueprint $t) { $t->id(); $t->integer('user_id')->nullable(); $t->string('folder_name')->nullable(); $t->timestamps(); });
        Schema::create('tenant_profiles', function (Blueprint $t) { $t->id(); $t->integer('managed_by')->nullable(); $t->timestamps(); });
        DB::table('legal_folders')->insert([['user_id' => 10], ['user_id' => 10], ['user_id' => 20], ['user_id' => null]]);
        DB::table('tenant_profiles')->insert([['managed_by' => 10], ['managed_by' => 20]]);
    }

    private function user(int $id, ?int $mainAccount = null): User
    {
        return (new User)->forceFill(['id' => $id, 'is_sub_account' => $mainAccount !== null, 'main_account_user_id' => $mainAccount]);
    }

    public function test_scopes_to_the_signed_in_business_and_staff_act_for_their_owner(): void
    {
        $this->actingAs($this->user(10));
        $this->assertSame(2, LegalFolder::forBusiness()->count());

        $this->actingAs($this->user(11, 10));
        $this->assertSame(2, LegalFolder::forBusiness()->count(), 'staff see their owner\'s records');
        $this->assertSame(1, TenantProfile::forBusiness()->count(), 'models can name their owner column');
    }

    public function test_an_explicit_owner_wins_and_no_owner_matches_nothing(): void
    {
        $this->assertSame(1, LegalFolder::forBusiness(20)->count());
        $this->assertSame(0, LegalFolder::forBusiness()->count(), 'nobody signed in: nothing, not the ownerless row');
        $this->assertSame(0, LegalFolder::forBusiness('')->count());
    }
}
