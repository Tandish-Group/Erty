<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LimitRedirectSizeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Check if the response is a redirect and the URL length exceeds 2500 characters
        if ($response instanceof RedirectResponse && strlen($response->getTargetUrl()) > 2500) {
            // Log the attempt
            Log::warning('Large redirect detected', ['url' => $response->getTargetUrl()]);

            // Redirect to a safe page or return an error response
            // return redirect('/error')->with('error', 'Invalid redirect detected.');
            return response()->json(['error' => 'Invalid redirect detected.'], 400);
        }

        return $response;
    }
}
