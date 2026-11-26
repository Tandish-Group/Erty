<?php

namespace App\Http\Middleware;

use App\Services\Access\BusinessAccessResolver;
use App\Services\Access\PropertyAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every business dashboard route for team members (sub-accounts):
 * suspended accounts are signed out, owner-only routes are refused, and
 * everything else needs the permission mapped in config/business_access.php.
 * Unmapped routes are refused for staff (deny by default).
 */
class AuthorizeBusinessRoute
{
    public function __construct(private BusinessAccessResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        if ($user->is_suspended) {
            auth()->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            $message = 'Your account has been suspended. Contact your account administrator.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 401)
                : redirect()->route('login')->withErrors(['email' => $message]);
        }

        if ($user->isBusinessOwner()) {
            return $next($request);
        }

        $route = $request->route();
        $rule = $route ? $this->resolver->ruleFor($route) : null;

        if ($rule === null) {
            Log::warning('Business route has no access rule; refused for team member.', ['route' => $route?->getActionName(), 'uri' => $request->path(), 'user' => $user->id]);
            return $this->deny($request, null);
        }

        if ($this->resolver->allows($user, $rule)) {
            // Roles limited to assigned properties only see those properties for the rest of the request.
            app(PropertyAccess::class)->activate($user);
            return $next($request);
        }

        // A team member without dashboard access lands on the first section they can use.
        if ($request->isMethod('GET') && !$request->expectsJson() && ($rule['group'] ?? null) === 'dashboard_access') {
            if ($landing = $this->resolver->landingUrl($user)) {
                return redirect($landing);
            }
        }

        return $this->deny($request, $rule);
    }

    private function deny(Request $request, ?array $rule): Response
    {
        $section = isset($rule['group']) ? $this->resolver->label($rule['group']) : null;
        $message = match ($rule['type'] ?? null) {
            BusinessAccessResolver::OWNER_ONLY => 'Only the account owner can use this page.',
            'permission' => "Your role does not allow you to {$this->verb($rule['action'])} {$section}.",
            default => 'Your role does not allow access to this page.',
        };

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'permission' => $rule], 403);
        }

        return response()->view('pages.business.errors.forbidden', [
            'message' => $message,
            'landing' => $this->resolver->landingUrl($request->user()),
        ], 403);
    }

    private function verb(string $action): string
    {
        return match ($action) {
            'view' => 'view',
            'create' => 'add to',
            'edit' => 'change',
            'delete' => 'delete from',
            'export' => 'export from',
            'import' => 'import into',
            'download' => 'download from',
            'approve' => 'approve in',
            default => str_replace('_', ' ', $action) . ' in',
        };
    }
}
