<?php

namespace App\Services\Access;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the activity log: pages people open, changes and downloads they make in the business app,
 * requests they are refused, and sign-ins. Never stores form contents, and a logging failure never
 * affects the request.
 *
 * Not logged: background requests a page makes for its own data (JSON), polling (notifications), and
 * anything outside the business app, the support inbox and platform administration.
 */
class ActivityLogger
{
    /** Routes that are noise (the page that uses them is logged). */
    private const SKIP_ROUTES = ['notifications.*', 'support.index', 'support.upload', 'health*', 'complexes.search', 'complexes.stats'];

    private const SKIP_PATHS = ['notifications/*', 'storage/overview'];

    public function __construct(private BusinessAccessResolver $resolver)
    {
    }

    public function request(Request $request, Response $response): void
    {
        try {
            if (!config('erty.activity_log.enabled', true)) {
                return;
            }
            $user = $request->user();
            $route = $request->route();
            if (!$user || !$route instanceof Route || !$this->tracked($route) || $route->named(self::SKIP_ROUTES) || $request->is(self::SKIP_PATHS)) {
                return;
            }
            $method = strtoupper($request->method());
            $status = $response->getStatusCode();
            $download = str_contains((string) $response->headers->get('Content-Disposition'), 'attachment');
            if ($method === 'GET' && !$download && $status < 400 && ($request->expectsJson() || $request->ajax())) {
                return; // a page's own data calls
            }
            $rule = $this->resolver->ruleFor($route);

            $this->write($user, [
                'event' => $this->event($route, $method, $status, $download, $request),
                'area' => $rule['group'] ?? $this->areaFor($route),
                'action' => $rule['action'] ?? null,
                'route' => Str::limit((string) ($route->getName() ?: $route->uri()), 118, ''),
                'method' => $method,
                'path' => Str::limit('/' . ltrim($request->path(), '/') . $this->query($request), 498, ''),
                'subject' => $this->subject($route),
                'status' => $status,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Activity not logged: ' . $e->getMessage());
        }
    }

    /** Sign-ins, sign-outs and failed sign-ins (from the authentication events). */
    public function auth(string $event, ?User $user, ?string $email = null, ?string $detail = null): void
    {
        if (!config('erty.activity_log.enabled', true)) {
            return;
        }
        try {
            $request = request();
            $this->write($user, [
                'event' => $event,
                'area' => 'auth',
                'subject' => ($email || $detail) ? Str::limit(trim(($detail ? $detail . ' ' : '') . ($email ? 'email ' . mb_strtolower($email) : '')), 250, '') : null,
                'path' => '/' . ltrim($request->path(), '/'),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ], $user ? null : $this->businessForEmail($email));
        } catch (\Throwable $e) {
            Log::warning('Sign-in activity not logged: ' . $e->getMessage());
        }
    }

    private function write(?User $user, array $row, ?int $businessId = null): void
    {
        ActivityLog::create($row + [
            'user_id' => $user?->id,
            'business_id' => $businessId ?? $user?->businessOwnerId(),
            'created_at' => now(),
        ]);
    }

    /** The business app, the support inbox and platform administration. */
    private function tracked(Route $route): bool
    {
        $middleware = $route->gatherMiddleware();

        return in_array('business.access', $middleware, true) || in_array('platform.admin', $middleware, true) || in_array('support.agent', $middleware, true);
    }

    private function areaFor(Route $route): ?string
    {
        $middleware = $route->gatherMiddleware();

        return match (true) {
            in_array('support.agent', $middleware, true) => 'support_inbox',
            in_array('platform.admin', $middleware, true) => 'platform',
            default => null,
        };
    }

    private function event(Route $route, string $method, int $status, bool $download, Request $request): string
    {
        // Not found is answered with a redirect back and a "not available" message (App\Exceptions\Handler).
        $notAvailable = $status >= 300 && $status < 400 && $request->hasSession() && $request->session()->has('not_available');
        if ($notAvailable || in_array($status, [401, 403, 404, 419], true)) {
            return 'refused';
        }
        if ($download) {
            return 'downloaded';
        }
        if ($method === 'GET' || $method === 'HEAD') {
            return 'viewed';
        }
        $name = strtolower((string) $route->getActionMethod());

        return match (true) {
            $method === 'DELETE' || str_contains($name, 'destroy') || str_contains($name, 'delete') => 'deleted',
            in_array($name, ['store', 'create'], true) || str_starts_with($name, 'store') => 'created',
            default => 'changed',
        };
    }

    /** The record the request was about, from the route parameters (encrypted ids are shown as #id). */
    private function subject(Route $route): ?string
    {
        $parts = [];
        foreach ($route->parameters() as $name => $value) {
            if (is_object($value) && method_exists($value, 'getRouteKey')) {
                $value = $value->getRouteKey();
            }
            if (!is_scalar($value)) {
                continue;
            }
            $value = (string) $value;
            if (strlen($value) > 60 && str_starts_with($value, 'eyJ')) {
                try {
                    $value = '#' . Crypt::decrypt($value);
                } catch (\Throwable) {
                    $value = '(encrypted)';
                }
            }
            $parts[] = str_replace('_', ' ', $name) . ' ' . Str::limit($value, 60, '');
        }

        return $parts ? Str::limit(implode(', ', $parts), 250, '') : null;
    }

    /** Filters in the address are kept (period, property, search); anything that looks like a secret is not. */
    private function query(Request $request): string
    {
        $query = collect($request->query())
            ->reject(fn ($v, $k) => preg_match('/token|password|code|otp|secret|signature/i', (string) $k))
            ->filter(fn ($v) => is_scalar($v))
            ->map(fn ($v) => Str::limit((string) $v, 60, ''));

        return $query->isEmpty() ? '' : '?' . http_build_query($query->all());
    }

    private function businessForEmail(?string $email): ?int
    {
        $user = $email ? User::whereRaw('lower(email) = ?', [mb_strtolower($email)])->first() : null;

        return $user?->businessOwnerId();
    }
}
