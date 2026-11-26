<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Jobs\SendOTPJob;
use App\Models\Otp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Business sign-in is always two-step: the password is checked, then a one-time code is created and sent
 * immediately (not queued), and the user is not signed in until the code is entered. There is no demo bypass.
 */
class BusinessSignInTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'signin_test', 'database.connections.signin_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('email')->unique(); $t->string('password');
            $t->string('account_type')->nullable(); $t->boolean('is_suspended')->default(false); $t->boolean('is_sub_account')->default(false);
            $t->boolean('delete_account')->default(false); $t->integer('incorrect_attempts')->default(0); $t->timestamp('last_login_at')->nullable(); $t->string('remember_token')->nullable(); $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create('model_has_roles', function (Blueprint $t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        Schema::create('model_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('otps', function (Blueprint $t) { $t->id(); $t->string('otp'); $t->timestamp('expiry')->nullable(); $t->string('status'); $t->unsignedBigInteger('user_id'); $t->timestamps(); });
        Schema::create('businesses', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->string('business_name')->nullable(); $t->timestamp('suspended_at')->nullable(); $t->timestamps(); });

        DB::table('roles')->insert([['id' => 1, 'name' => 'business', 'guard_name' => 'web'], ['id' => 2, 'name' => 'renter', 'guard_name' => 'web']]);
        DB::table('users')->insert([
            ['id' => 10, 'first_name' => 'Owner', 'email' => 'owner@x.test', 'password' => Hash::make('correct-horse'), 'account_type' => 'Business'],
            ['id' => 11, 'first_name' => 'Renter', 'email' => 'renter@x.test', 'password' => Hash::make('correct-horse'), 'account_type' => 'Renter'],
        ]);
        DB::table('model_has_roles')->insert([
            ['role_id' => 1, 'model_type' => \App\Models\User::class, 'model_id' => 10],
            ['role_id' => 2, 'model_type' => \App\Models\User::class, 'model_id' => 11],
        ]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Bus::fake([SendOTPJob::class, \App\Jobs\ClearOTPAndNotifyUser::class]);
    }

    private function signIn(string $email, string $password)
    {
        $request = Request::create('/login', 'POST', ['email' => $email, 'password' => $password]);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);
        return app(LoginController::class)->login($request);
    }

    public function test_the_right_password_sends_a_code_immediately_and_does_not_sign_in(): void
    {
        $response = $this->signIn('owner@x.test', 'correct-horse');

        $this->assertTrue($response->isRedirect());
        $this->assertSame(url('/auth'), $response->headers->get('Location'), 'nothing about the user goes in the URL');
        $this->assertSame(10, session('otp.user_id'), 'the pending sign-in is kept in the session');
        $otp = Otp::where('user_id', 10)->where('status', 'Pending')->sole();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp->otp, '6-digit code');
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, \Carbon\Carbon::parse($otp->expiry)->timestamp, 5, 'valid for 10 minutes');
        Bus::assertDispatchedSync(SendOTPJob::class);
        $this->assertCount(0, Bus::dispatched(SendOTPJob::class), 'the code is sent immediately, not queued');
        $this->assertFalse(auth()->check(), 'signed in only after the code is entered');
    }

    public function test_a_wrong_password_sends_no_code_and_counts_towards_lockout(): void
    {
        try {
            $this->signIn('owner@x.test', 'wrong');
            $this->fail('a wrong password was accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
        $this->assertSame(0, Otp::count());
        Bus::assertNothingDispatched();
        $this->assertSame(1, RateLimiter::attempts('owner@x.test|127.0.0.1'));
    }

    public function test_non_business_accounts_cannot_sign_in(): void
    {
        $response = $this->signIn('renter@x.test', 'correct-horse');

        $this->assertTrue($response->isRedirect());
        $this->assertSame(0, Otp::count());
        Bus::assertNothingDispatched();
        $this->assertFalse(auth()->check());
    }

    // ─── The code step ───────────────────────────────────────────────────────

    private function code(array $digits, array $extra = [])
    {
        $values = array_combine(['opt_one', 'opt_two', 'opt_three', 'opt_four', 'opt_five', 'opt_six'], str_split(implode('', $digits)));
        $request = Request::create('/validateOTP', 'POST', ['values' => $values + $extra]);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);
        return app(OtpController::class)->store($request);
    }

    private function pendingCode(): string
    {
        $this->signIn('owner@x.test', 'correct-horse');
        return Otp::where('user_id', 10)->latest('id')->value('otp');
    }

    public function test_the_right_code_signs_in_and_clears_the_pending_sign_in(): void
    {
        $code = $this->pendingCode();

        $response = $this->code(str_split($code));

        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame(10, auth()->id());
        $this->assertNull(session('otp.user_id'));
        $this->assertSame(0, Otp::where('user_id', 10)->count(), 'a code works once');
    }

    public function test_the_code_step_uses_the_session_user_not_a_user_id_from_the_browser(): void
    {
        // Another user (11) has a valid code; this browser has a pending sign-in for user 10.
        Otp::create(['otp' => '111111', 'expiry' => now()->addMinutes(10), 'status' => 'Pending', 'user_id' => 11]);
        $this->pendingCode();

        $response = $this->code(str_split('111111'), ['user_id' => 11]);

        $this->assertFalse($response->getData(true)['success']);
        $this->assertFalse(auth()->check());
    }

    public function test_without_a_pending_sign_in_the_code_page_and_check_refuse(): void
    {
        $request = Request::create('/auth', 'GET');
        $request->setLaravelSession(app('session.store'));
        $page = app(OtpController::class)->index($request);
        $this->assertTrue($page->isRedirect(url('/login')));

        $this->assertSame(419, $this->code(str_split('123456'))->getStatusCode());
    }

    public function test_three_wrong_codes_suspend_the_account_and_send_the_notice_immediately(): void
    {
        $wrong = $this->pendingCode() === '000000' ? '111111' : '000000';
        foreach (range(1, 3) as $i) {
            $this->code(str_split($wrong));
        }

        $this->assertTrue((bool) DB::table('users')->where('id', 10)->value('is_suspended'));
        Bus::assertDispatchedSync(\App\Jobs\ClearOTPAndNotifyUser::class);
        $this->assertFalse(auth()->check());
        $this->assertNull(session('otp.user_id'), 'the suspended sign-in cannot carry on');
    }

    public function test_a_wrong_code_says_how_many_tries_are_left(): void
    {
        $wrong = $this->pendingCode() === '000000' ? '111111' : '000000';

        $first = $this->code(str_split($wrong))->getData(true);
        $second = $this->code(str_split($wrong))->getData(true);

        $this->assertSame([2, 1], [$first['attempts_left'], $second['attempts_left']]);
        $this->assertStringContainsString('2 tries left', $first['message']);
        $this->assertStringContainsString('1 try left', $second['message']);
    }

    public function test_the_code_page_shows_a_masked_address_and_the_expiry(): void
    {
        $this->signIn('owner@x.test', 'correct-horse');
        $request = Request::create('/auth', 'GET');
        $request->setLaravelSession(app('session.store'));

        $view = app(OtpController::class)->index($request);

        $this->assertSame('ow•••@x.test', $view->getData()['maskedEmail']);
        $this->assertSame(10, $view->getData()['expiresIn']);
    }

    public function test_a_new_code_can_be_requested_at_most_once_a_minute(): void
    {
        $this->pendingCode();
        $resend = function () {
            $request = Request::create('/resend', 'POST', ['user_id' => 11]); // a user_id in the request is ignored
            $request->setLaravelSession(app('session.store'));
            return app(OtpController::class)->resendOTP($request);
        };

        $this->assertTrue($resend()->getData(true)['success']);
        $this->assertSame(0, Otp::where('user_id', 11)->count(), 'never for another user');
        $this->assertSame(429, $resend()->getStatusCode());
    }
}
