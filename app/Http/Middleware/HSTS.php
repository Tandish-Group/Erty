<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HSTS
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

        $hstsMaxAge = config('security.hsts.max_age', 31536000);
        $hstsIncludeSubDomains = config('security.hsts.include_subdomains', true) ? 'includeSubDomains' : '';
        $hstsPreload = config('security.hsts.preload', true) ? 'preload' : '';
        $response->headers->set('Strict-Transport-Security', "max-age={$hstsMaxAge}; {$hstsIncludeSubDomains}; {$hstsPreload}");

        return $response;
    }
}