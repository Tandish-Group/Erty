<?php

namespace Tests\Feature;

use App\Models\BusinessRole;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SafeHtml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Artisan, DB, Mail, Storage};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Support tickets end to end through the real routes: posting from the modal, files, "My support tickets",
 * and the platform administrators' inbox with replies, status changes and notifications.
 */
class SupportTicketTest extends TestCase
{
    private User $admin;
    private User $staff;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'support_test', 'database.connections.support_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'mail.default' => 'array', 'app.support_email' => 'help@example.test']);
        Artisan::call('migrate', ['--force' => true]);
        $this->artisan('erty:install', ['--email' => 'owner@example.test', '--business' => 'Demo Homes'])->assertSuccessful();
        Storage::fake('local');
        Mail::fake();

        $this->admin = User::where('email', 'owner@example.test')->firstOrFail();
        $this->admin->forceFill(['must_change_password' => false, 'first_name' => 'Olive', 'last_name' => 'Owner'])->save();
        $this->staff = $this->member('cara@example.test', 'Cara', 'Caretaker');
        $this->other = $this->member('finn@example.test', 'Finn', 'Finance Officer');
    }

    private function member(string $email, string $name, string $roleName): User
    {
        $user = User::forceCreate([
            'first_name' => $name, 'last_name' => 'Staff', 'full_name_slug' => strtolower($name) . '-staff', 'email' => $email, 'mobile' => 0, 'account_type' => 'Business', 'password' => bcrypt('irrelevant-' . $name),
            'is_sub_account' => true, 'main_account_user_id' => $this->admin->id,
            'business_role_id' => BusinessRole::where('business_id', $this->admin->id)->where('name', $roleName)->value('id'),
            'must_change_password' => false,
        ]);
        $user->assignRole(Role::findByName('business', 'web'));

        return $user;
    }

    private function postTicket(User $as, string $description = '<p>The <strong>rent</strong> page is blank</p><script>alert(1)</script>'): SupportTicket
    {
        $topic = DB::table('supports')->orderBy('id')->value('id');
        $this->actingAs($as)->postJson('/support', ['values' => ['support_topic' => $topic, 'support_description' => $description]])
            ->assertOk()->assertJsonStructure(['reference', 'url', 'ticket_id']);

        return SupportTicket::latest('id')->firstOrFail();
    }

    // Not-found pages redirect back with a "not available" message instead of showing a 404 page
    // (see App\Exceptions\Handler), so that is what a refused page looks like here.

    public function test_posting_a_ticket_gives_it_a_reference_cleans_the_text_and_tells_the_admins(): void
    {
        $ticket = $this->postTicket($this->staff);

        $this->assertMatchesRegularExpression('/^SUP-\d{4}-\d{4}$/', $ticket->reference);
        $this->assertSame('open', $ticket->status);
        $this->assertSame('<p>The <strong>rent</strong> page is blank</p>', $ticket->description, 'scripts are removed');
        $this->assertSame(1, $this->admin->notifications()->where('data', 'like', '%support.opened%')->count());
        Mail::assertSent(\App\Mail\SupportTicketMail::class, fn ($m) => $m->kind === 'opened' && $m->hasTo('owner@example.test') && $m->hasTo('help@example.test'));
    }

    public function test_the_other_topic_keeps_what_people_typed(): void
    {
        $other = DB::table('supports')->where('topic', 'like', '%other%')->value('id');
        $this->actingAs($this->staff)->postJson('/support', ['values' => [
            'support_topic' => $other, 'support_description' => 'Idea', 'custom_topic' => 'Dark mode',
        ]])->assertOk();

        $this->assertSame('Dark mode', SupportTicket::latest('id')->value('custom_topic'));
    }

    public function test_files_must_be_allowed_types_and_at_most_five_per_ticket_and_only_on_your_own_ticket(): void
    {
        $ticket = $this->postTicket($this->staff);
        $upload = fn (User $as, string $name) => $this->actingAs($as)->post('/support-upload-files', ['ticket_id' => $ticket->id, 'files' => [UploadedFile::fake()->create($name, 20)]], ['Accept' => 'application/json']);

        $upload($this->other, 'a.png')->assertNotFound();
        $upload($this->staff, 'run.php')->assertStatus(422);
        foreach (range(1, 5) as $i) {
            $upload($this->staff, "shot{$i}.png")->assertOk();
        }
        $upload($this->staff, 'six.png')->assertStatus(422);

        $this->assertSame(5, $ticket->attachments()->count());
        $file = $ticket->attachments()->first();
        $this->assertSame('local', $file->disk);
        Storage::disk('local')->assertExists($file->path);

        $this->actingAs($this->staff)->get(route('support.tickets.file', [$ticket->reference, $file->id]))->assertOk();
        $this->actingAs($this->other)->get(route('support.tickets.file', [$ticket->reference, $file->id]))->assertSessionHas('not_available');
        $this->actingAs($this->admin)->get(route('platform.support.file', [$ticket->reference, $file->id]))->assertOk();
    }

    public function test_people_see_only_their_own_tickets(): void
    {
        $mine = $this->postTicket($this->staff, 'Mine');
        $theirs = $this->postTicket($this->other, 'Theirs');

        $this->actingAs($this->staff)->get(route('support.tickets.index'))->assertOk()->assertSee($mine->reference)->assertDontSee($theirs->reference);
        $this->actingAs($this->staff)->get(route('support.tickets.show', $mine->reference))->assertOk();
        $this->actingAs($this->staff)->get(route('support.tickets.show', $theirs->reference))->assertSessionHas('not_available');
    }

    public function test_only_platform_admins_open_the_inbox(): void
    {
        $ticket = $this->postTicket($this->staff);

        $this->actingAs($this->staff)->get(route('platform.support.index'))->assertSessionHas('not_available');
        $this->actingAs($this->staff)->get(route('platform.support.show', $ticket->reference))->assertSessionHas('not_available');
        $this->actingAs($this->admin)->get(route('platform.support.index'))->assertOk()->assertSee($ticket->reference);
        $this->actingAs($this->admin)->get(route('platform.support.show', $ticket->reference))->assertOk()->assertSee('rent');
    }

    public function test_an_admin_reply_waits_on_the_user_and_tells_them_and_their_reply_reopens_it(): void
    {
        $ticket = $this->postTicket($this->staff);

        $this->actingAs($this->admin)->post(route('platform.support.reply', $ticket->reference), ['body' => 'Which property?'])->assertRedirect();
        $ticket->refresh();
        $this->assertSame('waiting_on_user', $ticket->status);
        $this->assertSame(1, $this->staff->notifications()->where('data', 'like', '%support.reply%')->count());
        Mail::assertSent(\App\Mail\SupportTicketMail::class, fn ($m) => $m->kind === 'updated' && $m->hasTo('cara@example.test'));

        $this->actingAs($this->admin)->post(route('platform.support.update', $ticket->reference), ['status' => 'resolved', 'priority' => 'high', 'assigned_to' => $this->admin->id])->assertRedirect();
        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame('high', $ticket->priority);

        $this->actingAs($this->staff)->post(route('support.tickets.reply', $ticket->reference), ['body' => 'Still blank on Acacia Court'])->assertRedirect();
        $ticket->refresh();
        $this->assertSame('open', $ticket->status, 'a reply on a resolved ticket opens it again');
        $this->assertNull($ticket->resolved_at);
        $this->assertSame(1, $this->admin->notifications()->where('data', 'like', '%support.user_reply%')->count(), 'the assigned admin hears about it');
        $this->assertSame(['waiting_on_user', 'resolved', 'open'], $ticket->messages()->whereNotNull('status_to')->pluck('status_to')->all());
    }

    public function test_an_admin_can_make_someone_a_support_agent_who_then_works_the_inbox_only(): void
    {
        $ticket = $this->postTicket($this->staff);
        $this->actingAs($this->other)->get(route('platform.support.index'))->assertSessionHas('not_available');

        $this->actingAs($this->admin)->post(route('platform.people.support', $this->other->id), ['enabled' => 1])->assertRedirect();
        $this->assertTrue($this->other->fresh()->answersSupport());
        $this->assertFalse($this->other->fresh()->isPlatformAdmin());

        $agent = $this->other->fresh();
        $this->actingAs($agent)->get(route('platform.support.index'))->assertOk()->assertSee($ticket->reference)->assertDontSee('Team &amp; business', false);
        $this->actingAs($agent)->get(route('platform.index'))->assertSessionHas('not_available'); // not platform administration
        $this->actingAs($agent)->post(route('platform.support.reply', $ticket->reference), ['body' => 'Looking into it'])->assertRedirect();
        $this->assertSame('waiting_on_user', $ticket->fresh()->status);

        // New tickets reach support agents too.
        $this->postTicket($this->staff, 'Another one');
        $this->assertSame(1, $agent->notifications()->where('data', 'like', '%support.opened%')->count());

        $this->actingAs($this->admin)->post(route('platform.people.support', $agent->id), ['enabled' => 0])->assertRedirect();
        $this->assertFalse($agent->fresh()->answersSupport());
        $this->actingAs($agent->fresh())->get(route('platform.support.index'))->assertSessionHas('not_available');
    }

    public function test_closed_tickets_take_no_replies_from_the_poster(): void
    {
        $ticket = $this->postTicket($this->staff);
        $this->actingAs($this->admin)->post(route('platform.support.update', $ticket->reference), ['status' => 'closed', 'priority' => 'normal']);

        $this->actingAs($this->staff)->post(route('support.tickets.reply', $ticket->reference), ['body' => 'One more thing'])->assertSessionHasErrors('body');
        $this->assertSame(0, $ticket->messages()->whereNotNull('body')->count());
    }

    public function test_deleting_a_topic_keeps_its_tickets(): void
    {
        $ticket = $this->postTicket($this->staff);
        DB::table('supports')->where('id', $ticket->support_id)->delete();

        $this->assertNull($ticket->fresh()->support_id);
    }

    public function test_safe_html_keeps_formatting_and_drops_everything_else(): void
    {
        $this->assertSame('<p>Hi <em>there</em></p>', SafeHtml::clean('<p onclick="x()">Hi <em style="color:red">there</em></p>'));
        $this->assertSame('click me', SafeHtml::clean('<a href="javascript:alert(1)">click me</a>'));
        $this->assertSame('', SafeHtml::clean('<img src=x onerror=alert(1)>'));
        $this->assertSame("line one<br>\nrent &lt; 5000 &amp; &lt;b&gt;", SafeHtml::fromText("line one\nrent < 5000 & <b>"), 'replies are plain text');
        $this->assertSame("plain<br>\ntext", SafeHtml::clean("plain\ntext"));
    }
}
