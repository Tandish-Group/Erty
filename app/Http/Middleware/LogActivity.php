<?php

namespace App\Http\Middleware;

use App\Services\Access\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Writes the activity log entry for a request after the response has been sent (see ActivityLogger). */
class LogActivity
{
    public function __construct(private ActivityLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->logger->request($request, $response);
    }
}
