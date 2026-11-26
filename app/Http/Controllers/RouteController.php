<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class RouteController extends Controller
{
    
    public function guard()
    { 
        if(Auth::check())
        { 
            $user = auth()->user();
            
            if($user->account_type === 'Business') {
                return Redirect::to('dashboard');
            }

            // The renter area was removed; only business accounts have somewhere to go.
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            return Redirect::to('login')->withErrors(['email' => 'Renter accounts are not available on this platform.']);
        }

        // There is no public site: guests sign in.
        return Redirect::to('login');
    }
}