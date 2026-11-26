<?php

namespace App\Http\Controllers\Business\Account;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\StorageUsageService;

class StorageController extends Controller
{
    protected $storageService;

    public function __construct(StorageUsageService $storageService)
    {
        $this->middleware('auth');
        $this->storageService = $storageService;
    }

    /**
     * Get storage overview data via AJAX.
     */
    public function getOverview()
    {
        try {
            $overview = $this->storageService->getStorageOverview();

            return response()->json([
                'status' => 200,
                'data' => $overview,
            ]);
        } catch (\Exception $e) {
            Log::error('StorageController: Error fetching overview - ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Failed to load storage information.',
            ], 500);
        }
    }

    /**
     * Recalculate storage usage by scanning the filesystem.
     */
    public function recalculate()
    {
        try {
            $totalBytes = $this->storageService->recalculateUsage();

            return response()->json([
                'status' => 200,
                'message' => 'Storage usage recalculated successfully.',
                'storage_used' => $totalBytes,
                'storage_used_formatted' => file_size($totalBytes),
            ]);
        } catch (\Exception $e) {
            Log::error('StorageController: Error recalculating storage - ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Failed to recalculate storage usage.',
            ], 500);
        }
    }
}
