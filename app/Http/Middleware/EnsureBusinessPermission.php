<?php

namespace App\Http\Middleware;

use App\Services\Access\BusinessPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessPermission
{
    public function handle(Request $request, Closure $next, string $group, string $action = 'view'): Response
    {
        $user = $request->user();

        if (!$user || !app(BusinessPermissionService::class)->can($user, $group, $action)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to perform this action.',
                    'permission' => [$group, $action],
                ], 403);
            }

            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
