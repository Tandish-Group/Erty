<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * GET /health for load balancers and uptime monitors: 200 {"healthy": true} when the database answers and file
 * storage is writable, otherwise 503. It reveals nothing else; run `php artisan health:check` for the details.
 */
class HealthCheckController extends Controller
{
    public function index(Request $request)
    {
        $healthy = $this->healthy();

        if ($request->header('User-Agent') === 'HAProxy Health Check') {
            return response($healthy ? 'OK' : 'UNHEALTHY', $healthy ? 200 : 503)->header('Content-Type', 'text/plain');
        }

        return new JsonResponse(['healthy' => $healthy], $healthy ? 200 : 503, ['Cache-Control' => 'no-store']);
    }

    private function healthy(): bool
    {
        try {
            DB::select('select 1');
            $disk = Storage::disk('local');
            $disk->put('.health', (string) now()->timestamp);
            return true;
        } catch (\Throwable $e) {
            Log::error('Health check failed: ' . $e->getMessage());
            return false;
        }
    }
}
