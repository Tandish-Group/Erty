<?php

namespace App\Http\Controllers\Auth;

use Carbon\Carbon;
use App\Models\Otp;
use App\Models\User;
use App\Jobs\SendOTPJob;
use Illuminate\Http\Request;
use App\Traits\OTPGeneratorTrait;
use App\Jobs\ClearOTPAndNotifyUser;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class OtpController extends Controller
{
    /** Wrong codes allowed before the account is suspended. */
    public const MAX_CODE_ATTEMPTS = 3;

    use OTPGeneratorTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Only someone who has just entered the right password has a pending sign-in in their session.
        $user = $this->pendingUser($request);
        if (!$user) {
            return redirect()->route('login');
        }

        return view('auth.two-steps')->with([
            'maskedEmail' => $this->maskEmail((string) $user->email),
            'expiresIn' => \App\Models\Otp::EXPIRY_MINUTES,
        ]);
    }

    /** "ja•••@example.com": enough to recognise the address without showing it in full. */
    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($name, 0, min(2, max(1, mb_strlen($name) - 1)));
        return $visible . '•••' . ($domain !== '' ? '@' . $domain : '');
    }

    /** The user whose password was just accepted in this browser session (never taken from the request). */
    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('otp.user_id');
        return $id ? User::find($id) : null;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // The six entered digits; the user is the one with a pending sign-in in this session.
        $otp = implode('', array_map(fn ($box) => (string) ($request->input("values.$box") ?? ''),
            ['opt_one', 'opt_two', 'opt_three', 'opt_four', 'opt_five', 'opt_six']));

        $user = $this->pendingUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Your sign-in has expired. Please sign in again.'], 419);
        }
        $user_id = $user->id;

        if ($user) {
            // Retrieve the latest OTP for the user
            $latestOTP = Otp::where('user_id', $user->id)->orderBy('created_at', 'desc')->first();
           

            $matches = $latestOTP && strlen($otp) === 6 && hash_equals((string) $latestOTP->otp, $otp);
            $expired = $matches && !Carbon::parse($latestOTP->expiry)->isFuture();
            if ($matches) {
                if (!$expired) {
                    // OTP is valid
                    // Perform the desired action (e.g., log in the user, redirect, etc.)
                    Auth::login($user); // Log in the user
                    // A fresh session ID after sign-in, as the standard login does (prevents session fixation).
                    $request->session()->forget('otp.user_id');
                    $request->session()->regenerate();

                    // Delete the used OTP
                    $latestOTP->delete();

                    // Reset incorrect OTP attempts count (if any)
                    $this->resetIncorrectAttempts($user);

                    // Update last login time
                    User::where('id', $user_id)->update(['last_login_at' => now()]);

                    return response()->json(['success' => true]);
                }
            }

            // Increment incorrect OTP attempts count
            $this->incrementIncorrectAttempts($user);
            app(\App\Services\Access\ActivityLogger::class)->auth('sign_in_failed', $user, null, 'wrong or expired code');

            // Check if the maximum attempts limit has been reached
            if ($this->exceededMaxAttempts($user)) {
                // Handle the scenario when maximum attempts are reached
                $this->handleMaxAttemptsReached($user);
                $request->session()->forget('otp.user_id');
                return response()->json(['success' => false, 'status' => 429,
                    'message' => 'Too many wrong codes. For your security this account is now suspended. Ask your account owner or administrator to restore access.']);
            }

            $left = self::MAX_CODE_ATTEMPTS - (int) $user->fresh()->incorrect_attempts;
            $tries = $left === 1 ? '1 try left' : "{$left} tries left";
            return response()->json(['success' => false, 'attempts_left' => $left, 'message' => $expired
                ? "That code has expired. Use Resend to get a new one ({$tries})."
                : "That code isn't right. Check the latest email and try again ({$tries})."]);
        }

        return response()->json(['success' => false, 'message' => "That code isn't right. Please try again."]);
    }

    /**
     * Increment the incorrect OTP attempts count for the user.
     *
     * @param \App\Models\User $user
     * @return void
     */
    private function incrementIncorrectAttempts(User $user)
    {
        $attempts = $user->incorrect_attempts ?? 0;
        $user->update(['incorrect_attempts' => $attempts + 1]);
    }

    /**
     * Reset the incorrect OTP attempts count for the user.
     *
     * @param \App\Models\User $user
     * @return void
     */
    private function resetIncorrectAttempts(User $user)
    {
        $user->update(['incorrect_attempts' => 0]);
    }

    /**
     * Check if the user has exceeded the maximum incorrect OTP attempts.
     *
     * @param \App\Models\User $user
     * @return bool
     */
    private function exceededMaxAttempts(User $user)
    {
        return ($user->incorrect_attempts ?? 0) >= self::MAX_CODE_ATTEMPTS;
    }

    /**
     * Handle the scenario when the user exceeds the maximum incorrect OTP attempts.
     *
     * @param \App\Models\User $user
     * @return void
     */
    private function handleMaxAttemptsReached(User $user)
    {
        // Suspend the user's account
        $user->update(['is_suspended' => true]);

        // Clear the OTP for the account
        Otp::where('user_id', $user->id)->delete();

        // Send an email notification
        ClearOTPAndNotifyUser::dispatchSync($user); // sent immediately; no queue worker is needed
        $request = request();
        $request->session()->forget('otp.user_id');
        
    }


    /**
     * Resend user otp resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function resendOTP(Request $request)
    {
        $user = $this->pendingUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Your sign-in has expired. Please sign in again.'], 419);
        }
        $user_id = $user->id;

        // At most one new code per minute for a pending sign-in.
        $key = 'otp-resend:' . $user_id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['success' => false, 'message' => 'Please wait ' . RateLimiter::availableIn($key) . ' seconds before asking for another code.'], 429);
        }
        RateLimiter::hit($key, 60);

        Otp::where('user_id', $user_id)->orderBy('created_at', 'desc')->delete();

        $otpData = $this->generateOTP();
        $otp = $otpData['otp'];
        $expiry = $otpData['expiry'];

        // Save OTP under user 
        Otp::create(['otp' => $otp, 'expiry'=>$expiry, 'status' => 'Pending', 'user_id' => $user_id]);

        SendOTPJob::dispatchSync($user, $otp); // sent immediately; a sign-in code must not wait for a queue worker

        return response()->json(['success' => true, 'message' => 'A new code is on its way. Use the latest email; earlier codes no longer work.']);

    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
