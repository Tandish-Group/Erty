<?php

namespace App\Http\Controllers\Auth;

use App\Models\Otp;
use App\Models\User;
use App\Jobs\SendOTPJob;
use Illuminate\Http\Request;
use App\Traits\OTPGeneratorTrait;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers, OTPGeneratorTrait;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /** Business owners and team members start on the dashboard (or their first permitted section). */
    protected function redirectTo()
    {
        return auth()->user()?->hasRole('business') ? '/dashboard' : RouteServiceProvider::HOME;
    }
    

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        // Retrieve the user attempting to log in
        $user = User::where('email', $request->email)->first();

        // Check is user staged for deletion
        if ($user && $user->delete_account) {
            // If the user is staged for deletion, throw an exception
            throw ValidationException::withMessages([
                'email' => 'Your account is scheduled for deletion. Please contact support if this is an error.',
            ]);
        }

        // Only business accounts can sign in; the renter area was removed.
        if ($user && !$user->hasRole('business')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Renter accounts are not available on this platform.',
            ]);
        }

        // Suspended team members (and accounts) cannot sign in.
        if ($user && $user->is_suspended && $user->is_sub_account) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been suspended. Contact your account administrator.',
            ]);
        }

        // Business sign-in is always two-step: password, then a one-time code sent by email.
        if ($user) {
            if ($user->is_suspended) {
                $this->fireLockoutEvent($request);
                return $this->sendLockoutResponse($request);
            }

            if ($this->hasTooManyLoginAttempts($request)) {
                $this->fireLockoutEvent($request);
                return $this->sendLockoutResponse($request);
            }

            if ($this->isAccountSuspended($user)) {
                throw ValidationException::withMessages([
                    'email' => 'Your account has been temporarily suspended. Please check your email for more details.',
                ]);
            }

            if (!$this->guard()->validate($this->credentials($request))) {
                $this->incrementLoginAttempts($request);
                return $this->sendFailedLoginResponse($request);
            }

            $this->clearLoginAttempts($request);

            $otpData = $this->generateOTP();
            Otp::create(['otp' => $otpData['otp'], 'expiry' => $otpData['expiry'], 'status' => 'Pending', 'user_id' => $user->id]);

            // Sent immediately (not queued): a sign-in code must not wait for a queue worker.
            SendOTPJob::dispatchSync($user, $otpData['otp']);

            // The code page and code check use this session value only; nothing about the user goes in the URL.
            $request->session()->put('otp.user_id', $user->id);
            return redirect()->route('auth');
        }
        

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            if ($request->hasSession()) {
                $request->session()->put('auth.password_confirmed_at', time());
            }

            return $this->sendLoginResponse($request);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    /** Wrong email or password: recorded in the activity log, then the usual error. */
    protected function sendFailedLoginResponse(Request $request)
    {
        $email = (string) $request->input($this->username());
        $user = User::whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
        app(\App\Services\Access\ActivityLogger::class)->auth('sign_in_failed', $user, $email, 'wrong email or password');

        throw ValidationException::withMessages([$this->username() => [trans('auth.failed')]]);
    }

    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        // Update last login timestamp
        $userId = $this->guard()->user()->id;
        if ($userId) {
            User::where('id', $userId)->update(['last_login_at' => now()]);
        }

        if ($response = $this->authenticated($request, $this->guard()->user())) {
            return $response;
        }

        return $request->wantsJson()
                    ? new JsonResponse([], 204)
                    : redirect()->intended($this->redirectPath());
    }

    /**
     * Check if the user account is suspended.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function isAccountSuspended($user)
    {
        return $user->is_suspended;
    }
}