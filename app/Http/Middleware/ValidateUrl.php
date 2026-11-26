<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\BlockedIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\BlockedIpNotification;

class ValidateUrl
{
    protected $maxAttempts;
    protected $decayMinutes;
    protected $notificationEmail;

    public function __construct()
    {
        $this->maxAttempts = (int) config('security.url_guard.max_attempts', 2);
        $this->decayMinutes = (int) config('security.url_guard.decay_minutes', 1);
        $this->notificationEmail = config('mail.notification_email'); // NOTIFICATION_EMAIL; no alert email when empty
    }

    public function handle(Request $request, Closure $next)
    {
        // List of static extensions
        $staticExtensions = [
            'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'map', 'otf', 'swap', 'ico',
            'bmp', 'webp', // Images
            'mp4', 'webm', 'ogg', 'avi', 'mov', 'mkv', 'm4v', // Videos
            'json', 'xml', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv' // Documents
        ];

        // List of whitelisted paths
        $whitelistedPaths = [
            'css/*',
            'js/*',
            'storage/*',
            'include/*',
            'robots.txt',   // Allow robots.txt access
        ];

        // Check if the request path matches any whitelisted path
        foreach ($whitelistedPaths as $whitelistedPath) {
            if ($request->is($whitelistedPath)) {
                return $next($request);
            }
        }

        // Get the request path and extension
        $path = $request->path();
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Check if the request path is for a static asset
        if (in_array($extension, $staticExtensions)) {
            return $next($request);
        }

        // Check if the request path matches any route
        $isValidRoute = false;
        foreach (Route::getRoutes() as $route) {
            if ($route->matches($request)) {
                $isValidRoute = true;
                break;
            }
        }

        if (!$isValidRoute) {
            $ip = $request->ip();
            $cacheKey = 'invalid-url-attempts:' . $ip;

            // Increment the invalid attempt count
            $attempts = Cache::increment($cacheKey);

            if ($attempts == 1) {
                Cache::put($cacheKey, $attempts, $this->decayMinutes * 60);
            }

            // Check for potential attack patterns
            $isAttack = $this->checkForAttackPatterns($path, $ip);

            if ($isAttack) {
                // Check if the IP has exceeded the maximum attempts
                if ($attempts > $this->maxAttempts) {
                    // Check if the IP is already blocked
                    if (!BlockedIp::where('ip_address', $ip)->exists()) {
                        // Save the IP in the database
                        BlockedIp::create(['ip_address' => $ip, 'path' => $path]);

                        // Send notification email
                        if ($this->notificationEmail) {
                            Mail::to($this->notificationEmail)->send(new BlockedIpNotification($ip, $path));
                        }
                    }

                    // Log the blocked attempt
                    Log::warning('Blocked IP: ' . $ip . ' tried to access invalid URL: ' . $path);

                    // Return the blocked view
                    return response()->view('errors.blocked');
                }

                // Log the invalid attempt
                Log::info('Invalid URL access attempt from IP: ' . $ip . ' to URL: ' . $path);
            } else {
                // Redirect to /home for non-attack invalid URLs
                return redirect('/home');
            }
        }

        return $next($request);
    }

    protected function checkForAttackPatterns($path, $ip)
    {
        $patterns = [
            'SQL Injection' => '/(select|insert|update|delete|drop|union|--|#)/i',
            'XSS' => '/(<script>|javascript:|onload=|onerror=)/i',
            'Path Traversal' => '/(\.\.\/|\.\.\\\\|%2e%2e%2f|%2e%2e%5c)/i',
            'Command Injection' => '/(;|&&|\|)/',
            'File Inclusion' => '/(php:\/\/|data:\/\/|input|output|ftp:\/\/|file:\/\/|http:\/\/|https:\/\/)/i',
            'Remote Code Execution (RCE)' => '/(\brun\b|\bexec\b|\bsystem\b|\bpopen\b|\bpclose\b|\bpassthru\b|\bshell_exec\b)/i',
            'CRLF Injection' => '/(%0d%0a|%0a|%0d|\r\n|\n|\r)/i',
            'Authentication Bypass' => '/(\bor\b|\band\b|\=|\!=|\<|\>|\>|\-\-|\#)/i',
            'HTTP Response Splitting' => '/(%0d%0a|%0a|%0d|\r\n|\n|\r)/i',
            'Shell Injection' => '/(\bsh\b|\bbash\b|\bcmd\b|\bpowershell\b|\bterminal\b|\bexec\b)/i',
            'XXE' => '/(<!ENTITY|SYSTEM\s+["\']|SYSTEM\s+["\'])/i',
            'Code Injection' => '/(eval\(|assert\(|preg_replace\(|create_function\(|call_user_func\()/i',
            'Buffer Overflow' => '/(A{100,}|%n)/i',
            'SSI (Server-Side Includes)' => '/(<\!--#include|<\!--#exec|<\!--#echo|<\!--#set)/i',
            // FIXED: Removed \u0000 which isn't supported by PCRE2
            'Null Byte Injection' => '/(%00|\\x00)/i'
        ];

        foreach ($patterns as $attackType => $pattern) {
            if (preg_match($pattern, $path)) {
                Log::alert("Potential $attackType attack from IP: $ip on URL: $path");
                return true;
            }
        }

        return false; 
    }

}