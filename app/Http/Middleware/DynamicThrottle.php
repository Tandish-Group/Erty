<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class DynamicThrottle
{
    protected $rateLimits;

    public function __construct()
    {
        $this->rateLimits = config('throttle.rate_limits', []);
    }

    public function handle($request, Closure $next)
    {
        $staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'map', 'otf', 'ico', 'json', 'xml', 'pdf'];
        $extension = pathinfo($request->path(), PATHINFO_EXTENSION);

        if (in_array($extension, $staticExtensions)) {
            return $next($request);
        }

        $routePattern = $this->getMatchingRoutePattern($request);
        $ip = $request->ip();
        $user = $request->user();

        if ($routePattern !== '*') {
            [$maxAttempts, $decayMinutes] = $this->rateLimits[$routePattern];
            $rateLimiterKey = $ip . '|' . $routePattern;
        } else {
            [$maxAttempts, $decayMinutes] = config($user ? 'throttle.signed_in' : 'throttle.guest', [60, 1]);
            $rateLimiterKey = ($user ? 'user:' . $user->getAuthIdentifier() : $ip) . '|*';
        }

        RateLimiter::for($rateLimiterKey, function () use ($maxAttempts, $decayMinutes) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinutes($decayMinutes, $maxAttempts);
        });

        if (RateLimiter::tooManyAttempts($rateLimiterKey, $maxAttempts)) {
            $resetTime = now()->addMinutes($decayMinutes)->timestamp;
            Log::warning('Rate limit exceeded', ['ip' => $ip, 'route' => $request->path(), 'routePattern' => $routePattern]);

            return response('Too many requests, please slow down.', 429)
                ->header('X-RateLimit-Limit', $maxAttempts)
                ->header('X-RateLimit-Remaining', RateLimiter::remaining($rateLimiterKey, $maxAttempts))
                ->header('X-RateLimit-Reset', $resetTime)
                ->header('Retry-After', $resetTime - now()->timestamp);
        }

        RateLimiter::hit($rateLimiterKey, $decayMinutes * 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', RateLimiter::remaining($rateLimiterKey, $maxAttempts));
        $response->headers->set('X-RateLimit-Reset', now()->addMinutes($decayMinutes)->timestamp);

        return $response;
    }

    protected function getMatchingRoutePattern($request): string
    {
        foreach (array_keys($this->rateLimits) as $pattern) {
            if ($request->is($pattern)) {
                return $pattern;
            }
        }
        return '*';
    }
}
