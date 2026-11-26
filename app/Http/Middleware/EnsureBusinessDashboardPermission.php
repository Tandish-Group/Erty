<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessDashboardPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        return app(EnsureBusinessPermission::class)
            ->handle($request, $next, 'dashboard_access', 'view');
    }
}
