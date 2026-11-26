<?php
namespace App\Http\Middleware;

use Closure;
use App\Models\BlockedIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BlockIp
{
    public function handle(Request $request, Closure $next)
    {
        // Check if the request IP is in the blocked IPs list
        if (BlockedIp::where('ip_address', $request->ip())->exists()) {
            // Optionally log the blocked attempt
            Log::warning('Blocked IP: ' . $request->ip() . ' tried to access the application.');
            Log::info('Check from BlockIp middleware');

            // Return the blocked view
            return response()->view('errors.blocked');
        }

        return $next($request);
    }
}