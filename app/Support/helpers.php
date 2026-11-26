<?php

use App\Models\User;

if (!function_exists('business_owner_id')) {
    /**
     * The business account that owns the data the signed-in user works on.
     * Staff (sub-accounts) act for their main account; owners act for themselves.
     * Use this for data scoping and owner columns; use auth()->id() only for "who did it" fields.
     */
    function business_owner_id(?User $user = null): ?int
    {
        $user ??= auth()->user();
        return $user ? $user->businessOwnerId() : null;
    }
}

if (!function_exists('default_currency')) {
    /** The installation's currency code (ERTY_CURRENCY), used wherever a record has no currency of its own. */
    function default_currency(): string
    {
        return (string) (config('property.currency') ?: 'KES');
    }
}

if (!function_exists('money')) {
    /** "KES 12,500.00": an amount with its currency code (the default currency when none is given). */
    function money(int|float|string|null $amount, ?string $currency = null, int $decimals = 2): string
    {
        return ($currency ?: default_currency()) . ' ' . number_format((float) $amount, $decimals);
    }
}

if (!function_exists('file_size')) {
    /** "1.5 MB": a byte count for people. */
    function file_size(int|float|null $bytes): string
    {
        $bytes = (float) $bytes;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}

/*
 | Profile links: the page for an agent, lead, tenancy or tenant, or null when the signed-in
 | person can't open it (so names show as plain text instead of a link that would be refused).
 */
if (!function_exists('agent_profile_url')) {
    function agent_profile_url($agent): ?string
    {
        $user = auth()->user();
        if (!$agent || !$user || !$user->hasBusinessPermission('agent_management', 'view')) {
            return null;
        }
        return route('agent_management.profile', $agent->profile_route_token);
    }
}

if (!function_exists('lead_profile_url')) {
    function lead_profile_url($lead): ?string
    {
        $user = auth()->user();
        if (!$lead || !$user || !$user->hasBusinessPermission('lead_management', 'view')) {
            return null;
        }
        return route('lead_management.show', \Illuminate\Support\Facades\Crypt::encrypt($lead->id));
    }
}

if (!function_exists('tenancy_profile_url')) {
    function tenancy_profile_url($tenancy): ?string
    {
        $user = auth()->user();
        if (!$tenancy || !$tenancy->tenancy_ref || !$user || !$user->hasBusinessPermission('tenant_management', 'view')) {
            return null;
        }
        return url('/tenant_management/tenancy/' . rawurlencode($tenancy->tenancy_ref) . '/view');
    }
}

if (!function_exists('tenant_profile_url')) {
    function tenant_profile_url($profile): ?string
    {
        $user = auth()->user();
        if (!$profile || !$profile->tenant_ref || !$user || !$user->hasBusinessPermission('tenant_management', 'view')) {
            return null;
        }
        return url('/tenant_management/tenant/' . rawurlencode($profile->tenant_ref) . '/view');
    }
}

if (!function_exists('profile_link')) {
    /** A name as a link to its profile, or plain (escaped) text when there is no link. */
    function profile_link(?string $url, ?string $label, string $class = ''): \Illuminate\Support\HtmlString
    {
        $text = e($label ?? '');
        return new \Illuminate\Support\HtmlString($url
            ? '<a href="' . e($url) . '"' . ($class ? ' class="' . e($class) . '"' : '') . '>' . $text . '</a>'
            : $text);
    }
}

if (!function_exists('can_open')) {
    /**
     * Whether the signed-in person may open a URL of the business app, by the same access rules the
     * routes use (config/business_access.php). Use it to show a link only to people it works for.
     */
    function can_open(string $url, string $method = 'GET'): bool
    {
        static $cache = [];
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $key = $user->id . ' ' . $method . ' ' . $path;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create($path, $method));
        } catch (\Throwable) {
            return $cache[$key] = false;
        }
        $resolver = app(\App\Services\Access\BusinessAccessResolver::class);
        $rule = $resolver->ruleFor($route);

        return $cache[$key] = $rule === null ? !in_array('business.access', $route->gatherMiddleware(), true) : $resolver->allows($user, $rule);
    }
}
