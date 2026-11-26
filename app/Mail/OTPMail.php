<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OTPMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $user;
    protected $otp;

    /**
     * Create a new message instance.
     *
     * @param  User  $user
     * @param  string  $otp
     * @return void
     */
    public function __construct(User $user, string $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your ' . config('app.name') . ' sign-in code')
            ->view('emails.authentication.otp')
            ->with([
                'user' => $this->user,
                'otp' => $this->otp,
            ]);
    }
}
