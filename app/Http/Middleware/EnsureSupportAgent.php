<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The support inbox: platform administrators and support agents only (User::answersSupport). Everyone else
 * gets 404, as for the rest of the platform area.
 */
class EnsureSupportAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->answersSupport(), 404);

        return $next($request);
    }
}
