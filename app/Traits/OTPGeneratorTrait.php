<?php

namespace App\Traits;

use Carbon\Carbon;

trait OTPGeneratorTrait
{
    protected function generateOTP()
    {
        // A random 6-digit code per sign-in. (A shared TOTP secret gave everyone signing in during the same
        // 30 seconds the same code.) Codes are stored per user and expire after 10 minutes.
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = Carbon::now()->addMinutes(\App\Models\Otp::EXPIRY_MINUTES);

        return [
            'otp' => $otp,
            'expiry' => $expiry,
        ];
    }
    
}
