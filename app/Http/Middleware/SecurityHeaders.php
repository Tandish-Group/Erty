<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Add HSTS header
        // $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // Add Referrer-Policy header
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');

        // Add Permissions-Policy header
        // $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}