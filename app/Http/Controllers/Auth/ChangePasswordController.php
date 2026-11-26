<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Required password change for accounts created with a temporary password (the seeded administrator).
 * Reached right after the two-step sign-in; everything else redirects here until it is done.
 */
class ChangePasswordController extends Controller
{
    public function show(Request $request)
    {
        if (!$request->user()->must_change_password) {
            return redirect('/');
        }
        return view('auth.change-password');
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ], [], ['password' => 'new password']);

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a password different from the temporary one.']);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        return redirect('/')->with('success', 'Your password is set. Welcome to ' . config('app.name') . '.');
    }
}
