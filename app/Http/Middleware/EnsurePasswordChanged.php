<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account created with a temporary password (users.must_change_password) can do nothing else
 * until it has chosen its own password.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->must_change_password && !$request->routeIs('password.change', 'password.change.update', 'logout')) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Choose a new password before continuing.', 'redirect' => route('password.change')], 409)
                : redirect()->route('password.change');
        }

        return $next($request);
    }
}
