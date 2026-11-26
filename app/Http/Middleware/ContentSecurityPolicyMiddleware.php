<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;

class ContentSecurityPolicyMiddleware
{
    private const CSP_VERSION = '1.0.8'; // Version bump for Overpass API

    public function handle(Request $request, Closure $next)
    {
        $nonce = base64_encode(random_bytes(16));
        Session::put('cspNonce', $nonce);

        // Define allowed domains
        $allowedDomains = [
            // Original domains
            'unpkg.com',
            'cdnjs.cloudflare.com',
            'code.jquery.com',
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'polyfill.io',
            'api.mapbox.com',
            'events.mapbox.com',
            '*.mapbox.com',
            'cdn.plyr.io',
            'maxst.icons8.com',
            'nominatim.openstreetmap.org',
            'tile.openstreetmap.org',
            '*.tile.openstreetmap.org',
            'overpass-api.de',
            '*.overpass-api.de',
            '*.basemaps.cartocdn.com',
            'cdn.jsdelivr.net',
            '*.openstreetmap.org',
            'unpkg.com',
            'cartocdn.com',
            'static.cloudflareinsights.com',
            'cloudflareinsights.com',

            // Google (maps, fonts, APIs). Analytics trackers were removed.
            '*.google.com',
            '*.googleapis.com',
            '*.gstatic.com',

            // SaaS Hub
            'www.saashub.com',
            '*.saashub.com',

            // Video platforms
            'www.youtube.com',
            'youtube.com',
            'www.youtube-nocookie.com',
            '*.youtube.com',
            'youtube-nocookie.com',
            '*.ytimg.com',
            'player.vimeo.com',
            'vimeo.com',
            '*.vimeocdn.com',
            'www.dailymotion.com',
            'dailymotion.com',
            'noembed.com',

            // Local development
            Config::get('app.env') !== 'production' ? 'localhost:*' : null,
        ];

        // Process domains for CSP
        $domains = implode(' ', array_map(function ($domain) {
            return $domain ? "https://{$domain}" : '';
        }, array_filter($allowedDomains)));

        // Socket.IO connection sources: CSP_WEBSOCKET_SOURCES when set, otherwise this site's own host (from APP_URL).
        $socketIoConnectSources = Config::get('app.websocket_sources');
        if (!$socketIoConnectSources) {
            $host = parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost';
            $socketIoConnectSources = Config::get('app.env') !== 'production'
                ? "http://{$host}:5000 wss://{$host}:5000 ws://{$host}:5000"
                : "wss://{$host} wss://{$host}:5000";
        }

        // CSP directives
        $cspDirectives = [
            "default-src" => "'self' {$domains}",
            "script-src" => "'self' 'unsafe-inline' 'unsafe-eval' {$domains} https://*.google.com https://www.youtube.com/iframe_api https://s.ytimg.com https://static.cloudflareinsights.com https://www.saashub.com https://*.saashub.com",
            "script-src-elem" => "'self' 'unsafe-inline' 'unsafe-eval' {$domains} https://*.google.com https://www.youtube.com/iframe_api https://s.ytimg.com https://static.cloudflareinsights.com",
            "style-src" => "'self' 'unsafe-inline' {$domains} https://maxst.icons8.com",
            "style-src-elem" => "'self' 'unsafe-inline' {$domains} https://maxst.icons8.com",
            "img-src" => "'self' data: blob: {$domains} https://*.tile.openstreetmap.org https://i.ytimg.com",
            "font-src" => "'self' data: {$domains} https://maxst.icons8.com",
            "connect-src" => "'self' {$domains} https://*.mapbox.com https://api.mapbox.com https://events.mapbox.com https://nominatim.openstreetmap.org https://overpass-api.de https://*.overpass-api.de https://noembed.com {$socketIoConnectSources}",
            "media-src" => "'self' blob: {$domains}",
            "frame-src" => "'self' {$domains} https://www.youtube.com https://youtube.com https://www.youtube-nocookie.com https://youtube-nocookie.com https://player.vimeo.com https://www.dailymotion.com",
            "object-src" => "'none'",
            "base-uri" => "'self'",
            "form-action" => "'self'",
            "worker-src" => "'self' blob:",
            "manifest-src" => "'self'",
            "upgrade-insecure-requests" => "",
            "block-all-mixed-content" => "",
            "report-uri" => Config::get('app.env') !== 'production' ? "/api/csp-report" : null,
            "report-to" => Config::get('app.env') !== 'production' ? "csp-endpoint" : null,
        ];

        // Construct CSP header
        $cspHeader = implode('; ', array_map(function ($key, $value) {
            return $value === "" ? $key : "{$key} {$value}";
        }, array_keys(array_filter($cspDirectives)), array_values(array_filter($cspDirectives))));

        $response = $next($request);

        // Set security headers
        $response->headers->set('Content-Security-Policy', $cspHeader);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), microphone=(), camera=()');
        $response->headers->set('X-CSP-Version', self::CSP_VERSION);

        return $response;
    }
}