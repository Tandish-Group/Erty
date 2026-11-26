<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform administration: only signed-in, active users with the "admin" role. Everyone else gets 404,
 * so the area is not advertised to business users.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isPlatformAdmin() && !$user->is_suspended, 404);

        return $next($request);
    }
}
