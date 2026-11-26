<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BusinessRole;
use App\Models\User;
use Illuminate\Support\Facades\{Artisan, DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The activity log records what people open, change and download, refusals and sign-ins, and only people
 * whose role allows it can read or download it.
 */
class ActivityLogTest extends TestCase
{
    private User $owner;
    private User $caretaker;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'activity_test', 'database.connections.activity_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'mail.default' => 'array']);
        Artisan::call('migrate', ['--force' => true]);
        $this->artisan('erty:install', ['--email' => 'owner@example.test'])->assertSuccessful();
        Mail::fake();
        $this->owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->caretaker = $this->member('care@example.test', 'Caretaker');
        $this->viewer = $this->member('view@example.test', 'Viewer');
    }

    private function member(string $email, string $role): User
    {
        $user = User::forceCreate([
            'first_name' => $role, 'last_name' => 'Person', 'full_name_slug' => strtolower($role) . '-person', 'email' => $email, 'mobile' => 0,
            'account_type' => 'Business', 'password' => bcrypt('not-used-' . $role), 'is_sub_account' => true,
            'main_account_user_id' => $this->owner->id, 'must_change_password' => false,
            'business_role_id' => BusinessRole::where('business_id', $this->owner->id)->where('name', $role)->value('id'),
        ]);
        $user->assignRole(Role::findByName('business', 'web'));

        return $user;
    }

    public function test_pages_opened_are_logged_with_their_area_and_right_but_a_pages_own_data_calls_are_not(): void
    {
        $this->actingAs($this->caretaker)->get('/maintenance')->assertOk();
        $this->actingAs($this->caretaker)->getJson('/notifications/unread')->assertOk();

        $entries = ActivityLog::where('user_id', $this->caretaker->id)->get();
        $this->assertCount(1, $entries);
        $entry = $entries->first();
        $this->assertSame(['viewed', 'maintenance_management', 'view', '/maintenance', $this->owner->id],
            [$entry->event, $entry->area, $entry->action, $entry->path, (int) $entry->business_id]);
    }

    public function test_refusals_and_changes_are_logged(): void
    {
        $this->actingAs($this->caretaker)->get('/complex-expenses')->assertForbidden();
        $this->assertSame('refused', ActivityLog::where('user_id', $this->caretaker->id)->latest('id')->value('event'));

        $this->actingAs($this->owner)->postJson('/business-roles/store', ['name' => 'Night guard', 'permissions' => ['dashboard_access' => ['view' => 'on']]]);
        $change = ActivityLog::where('user_id', $this->owner->id)->where('method', 'POST')->latest('id')->first();
        $this->assertNotNull($change);
        $this->assertSame(['created', 'role_management'], [$change->event, $change->area]);
    }

    public function test_only_roles_with_the_activity_log_right_can_read_or_download_it(): void
    {
        $this->actingAs($this->caretaker)->get('/maintenance');

        $this->actingAs($this->caretaker)->get('/activity')->assertForbidden();
        $this->actingAs($this->viewer)->get('/activity?user=' . $this->caretaker->id)->assertOk()->assertSee('/maintenance');
        $this->actingAs($this->viewer)->get('/activity/export')->assertForbidden(); // viewers read; downloading is its own right

        $csv = $this->actingAs($this->owner)->get('/activity/export?user=' . $this->caretaker->id);
        $csv->assertOk();
        $this->assertStringContainsString('/maintenance', $csv->streamedContent());
        $this->assertSame('downloaded', ActivityLog::where('user_id', $this->owner->id)->latest('id')->value('event'));
    }

    public function test_failed_sign_ins_are_logged_without_the_password(): void
    {
        $this->post('/login', ['email' => 'care@example.test', 'password' => 'Wrong-password-1']);

        $entry = ActivityLog::where('event', 'sign_in_failed')->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertSame($this->caretaker->id, (int) $entry->user_id);
        $this->assertStringContainsString('care@example.test', $entry->subject);
        $this->assertStringNotContainsString('Wrong-password-1', json_encode(ActivityLog::all()->toArray()));
    }

    public function test_old_entries_are_pruned(): void
    {
        DB::table('activity_logs')->insert([
            ['business_id' => $this->owner->id, 'user_id' => $this->owner->id, 'event' => 'viewed', 'created_at' => now()->subDays(400)],
            ['business_id' => $this->owner->id, 'user_id' => $this->owner->id, 'event' => 'viewed', 'created_at' => now()->subDays(10)],
        ]);
        $this->artisan('activity:prune', ['--days' => 365])->assertSuccessful();

        $this->assertSame(1, ActivityLog::count());
    }
}
