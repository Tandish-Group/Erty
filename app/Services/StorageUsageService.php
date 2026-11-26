<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Tracks how much file storage each business uses (shown in account settings; never limited). */
class StorageUsageService
{
    /**
     * Get the business record for the current user.
     */
    private function getBusiness($userId = null): ?Business
    {
        $userId = $userId ?? business_owner_id();
        return Business::where('user_id', $userId)->first();
    }

    /**
     * Get current storage used by a user (from cached business record).
     */
    public function getStorageUsed($userId = null): int
    {
        $business = $this->getBusiness($userId);
        return $business ? (int) $business->storage_used : 0;
    }

    /**
     * Increment storage used after a successful upload.
     *
     * @param int $bytes Number of bytes to add
     * @param int|null $userId
     */
    public function incrementUsage(int $bytes, $userId = null): void
    {
        $business = $this->getBusiness($userId);
        if ($business) {
            $business->increment('storage_used', $bytes);
        }
    }

    /**
     * Decrement storage used after a file deletion.
     *
     * @param int $bytes Number of bytes to subtract
     * @param int|null $userId
     */
    public function decrementUsage(int $bytes, $userId = null): void
    {
        $business = $this->getBusiness($userId);
        if ($business) {
            $newValue = max(0, (int) $business->storage_used - $bytes);
            $business->update(['storage_used' => $newValue]);
        }
    }

    /**
     * Recalculate actual storage used by scanning the filesystem.
     * This is useful for syncing the database value with reality.
     *
     * @param int|null $userId
     * @return int Total bytes used
     */
    public function recalculateUsage($userId = null): int
    {
        $userId = $userId ?? business_owner_id();
        $business = $this->getBusiness($userId);

        if (!$business) return 0;

        $businessName = $business->business_name;
        $disk = Storage::disk('public');
        $totalBytes = 0;

        try {
            // Scan the user's storage directory on the public disk
            // (covers images, plans, avatars, tenants, legal)
            $userPath = 'users/' . $businessName;
            if ($disk->exists($userPath)) {
                $totalBytes += $this->getDirectorySize($disk->path($userPath));
            }

            // Scan agent files (photos + resumes, also on public disk)
            $agentPaths = ['agents/photos', 'agents/resumes'];
            foreach ($agentPaths as $agentPath) {
                if ($disk->exists($agentPath)) {
                    $totalBytes += $this->getDirectorySize($disk->path($agentPath));
                }
            }
        } catch (\Exception $e) {
            Log::warning('StorageUsageService: Error scanning directory for user ' . $userId . ': ' . $e->getMessage());
        }

        // Update the cached value
        if ($business) {
            $business->update(['storage_used' => $totalBytes]);
        }

        return $totalBytes;
    }

    /**
     * Recursively calculate the size of a directory in bytes.
     */
    private function getDirectorySize(string $path): int
    {
        $totalSize = 0;

        if (!is_dir($path)) return 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        return $totalSize;
    }

    /**
     * Storage the business uses, for display. Erty does not cap storage; the figure is informational.
     */
    public function getStorageOverview($userId = null): array
    {
        $used = $this->getStorageUsed($userId);

        return [
            'used' => $used,
            'used_formatted' => file_size($used),
            'breakdown' => $this->getStorageBreakdown($userId),
        ];
    }

    /**
     * Get storage breakdown by category.
     *
     * All user uploads live on the "public" disk regardless of the default
     * FILESYSTEM_DRIVER setting, so we always scan that disk explicitly.
     */
    private function getStorageBreakdown($userId = null): array
    {
        $userId = $userId ?? business_owner_id();
        $business = $this->getBusiness($userId);

        if (!$business) return [];

        $businessName = $business->business_name;
        $disk = Storage::disk('public');
        $breakdown = [];

        $categories = [
            ['label' => 'Listing Images', 'path' => 'users/' . $businessName . '/images',  'icon' => 'ki-picture',  'color' => 'primary'],
            ['label' => 'Floor Plans',    'path' => 'users/' . $businessName . '/plans',   'icon' => 'ki-map',      'color' => 'info'],
            ['label' => 'Avatars',        'path' => 'users/' . $businessName . '/avatars', 'icon' => 'ki-user',     'color' => 'warning'],
            ['label' => 'Agent Files',    'path' => 'agents',                               'icon' => 'ki-people',   'color' => 'success'],
            ['label' => 'Tenant Docs',    'path' => 'users/' . $businessName . '/tenants', 'icon' => 'ki-document', 'color' => 'danger'],
            ['label' => 'Legal Files',    'path' => 'users/' . $businessName . '/legal',   'icon' => 'ki-shield',   'color' => 'dark'],
        ];

        foreach ($categories as $cat) {
            $size = 0;

            try {
                if ($disk->exists($cat['path'])) {
                    $size = $this->getDirectorySize($disk->path($cat['path']));
                }
            } catch (\Exception $e) {
                // Silently skip
            }

            $breakdown[] = [
                'label' => $cat['label'],
                'size' => $size,
                'size_formatted' => file_size($size),
                'icon' => $cat['icon'],
                'color' => $cat['color'],
            ];
        }

        return $breakdown;
    }
}
