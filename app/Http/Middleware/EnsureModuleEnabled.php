<?php

namespace App\Http\Middleware;

use App\Services\Access\ModuleService;
use Closure;
use Illuminate\Http\Request;

/**
 * Usage: `module:listings` (installation + the signed-in user's business)
 *        `module:listings,installation` (installation switch only — public pages).
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module, ?string $scope = null): mixed
    {
        $modules = app(ModuleService::class);

        $enabled = $scope === 'installation'
            ? $modules->installationEnabled($module)
            : $modules->enabled($module, $request->user());

        if ($enabled) {
            return $next($request);
        }

        $label = ModuleService::MODULES[$module]['label'] ?? ucfirst($module);
        $message = "The {$label} module is not enabled.";

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        $user = $request->user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('business')) {
            return redirect('/dashboard')->with('error', $message);
        }

        // A signed-in non-business user (e.g. a renter) has nowhere to go when the module is off:
        // sign them out rather than bouncing between /login and /home.
        if ($user) {
            auth()->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return redirect('/login')->withErrors(['email' => $message]);
    }
}
