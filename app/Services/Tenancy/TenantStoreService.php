<?php

namespace App\Services\Tenancy;

use App\Services\Access\PropertyAccess;

use App\Models\Legal;
use App\Models\Complex;
use App\Models\LegalFolder;
use App\Models\TenantProfile;
use App\Models\TenantManagement;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendTenantWelcomeEmailJob;
use App\Services\StorageUsageService;

class TenantStoreService
{
    public function generateTenantProfileReference()
    {
        $prefix = 'TP';
        $randomString = $this->generateRandomString(8);
        $profileRef = $prefix . $randomString;
        
        // Ensure uniqueness
        while (app(PropertyAccess::class)->unrestricted(fn () => TenantProfile::where('tenant_ref', $profileRef)->exists())) {
            $randomString = $this->generateRandomString(8);
            $profileRef = $prefix . $randomString;
        }
        
        return strtoupper($profileRef);
    }

    public function generateTenancyReference()
    {
        $prefix = 'TM';
        $randomString = $this->generateRandomString(8);
        $tenancyRef = $prefix . $randomString;
        
        // Ensure uniqueness
        while (app(PropertyAccess::class)->unrestricted(fn () => TenantManagement::where('tenancy_ref', $tenancyRef)->exists())) {
            $randomString = $this->generateRandomString(8);
            $tenancyRef = $prefix . $randomString;
        }
        
        return strtoupper($tenancyRef);
    }

    public function sendWelcomeEmail($tenantManagement, $tenantProfile, $legalFiles)
    {
        $user_id = business_owner_id();
        $business_name = auth()->user()->business->business_name;
        $complex = Complex::where('id', $tenantManagement->complex_id)->first();

        $TenantEmail = $tenantProfile->tenant_type === 'Business'
            ? $tenantProfile->business_email
            : $tenantProfile->tenant_email;

        // $tenant_unit = $tenantManagement->unit_number ?? $tenantManagement->specific_unit_number ?? 'N/A';
    
        if (!$TenantEmail) {
            throw new \Exception('No email address found for the tenant.');
        }
    
        $tenantName = $tenantProfile->tenant_type === 'Business'
            ? $tenantProfile->business_name
            : $tenantProfile->tenant_name;
    
        $attachments = [];
        if (!empty($legalFiles) && is_array($legalFiles)) {
            foreach ($legalFiles as $file) {
                $file = Legal::where([['user_id', $user_id],['file_name', $file]] )->first();
                $folderData = LegalFolder::where([['user_id', $user_id],['id', $file->legal_folder_id]])->first();

                $attachments[] = 'users/'.$business_name.'/legal/' . $business_name .'/'. $folderData->name .'/'. $file->file_name;
                
            }
        }

        try{
            Log::info("all dispatch detils, TenantEmail: {$TenantEmail}, tenantName: {$tenantName}, attachments: " . implode(', ', $attachments));
            dispatch_sync(new SendTenantWelcomeEmailJob($TenantEmail, $tenantName, $tenantProfile, $complex, $attachments, $business_name));
        } catch (\Exception $e) {
            Log::error("Failed to dispatch welcome email job: " . $e->getMessage());
            // Optionally, you can choose to throw the exception or handle it as needed
        }
       
    
        Log::info("Sent welcome email to tenant : {$TenantEmail}");
    }
    
    private function generateRandomString($length)
    {
        $key = '';
        $keys = array_merge(range(0, 9), range('a', 'z'));

        for ($i = 0; $i < $length; $i++) {
            $key .= $keys[array_rand($keys)];
        }

        return $key;
    }

    public function handleTenantDocumentUpload($files, $tenantRef, $tenantName)
    {
        $businessName = auth()->user()->business->business_name;
        
        // Create tenant folder name using both ref and name (sanitized)
        $sanitizedTenantName = $this->sanitizeFolderName($tenantName);
        $tenantFolderName = $tenantRef . '_' . $sanitizedTenantName;
        
        // Create the storage path: users/{business}/tenants/{tenant_ref_name}/documents
        $storagePath = 'users/' . $businessName . '/tenants/' . $tenantFolderName . '/documents';
        
        $documentPaths = [];
        
        foreach ($files as $index => $file) {
            // Get file info
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = pathinfo($originalName, PATHINFO_FILENAME);
            
            // Create unique filename to avoid conflicts
            $timestamp = now()->format('Y-m-d_H-i-s');
            $fileNameToStore = $this->sanitizeFileName($filename) . '_' . $timestamp . '.' . $extension;
            
            try {
                // Store the file
                $filePath = $file->storeAs($storagePath, $fileNameToStore, 'public');
                
                $documentPaths[] = [
                    'original_name' => $originalName,
                    'stored_name' => $fileNameToStore,
                    'path' => $filePath,
                    'relative_path' => $storagePath . '/' . $fileNameToStore, // For easier access
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_at' => now()->toISOString(),
                    'file_type' => $this->categorizeFileType($extension),
                ];

                // Track storage usage
                app(StorageUsageService::class)->incrementUsage($file->getSize());
                
                Log::info("Tenant document uploaded: {$originalName} -> {$filePath}");
                
            } catch (\Exception $e) {
                Log::error("Failed to upload tenant document {$originalName}: " . $e->getMessage());
                // Continue with other files instead of failing completely
            }
        }
        
        return $documentPaths;
    }
    
    private function sanitizeFolderName($name)
    {
        // Remove special characters and spaces, convert to lowercase
        $sanitized = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
        // Replace spaces with underscores
        $sanitized = str_replace(' ', '_', $sanitized);
        // Remove multiple underscores
        $sanitized = preg_replace('/_{2,}/', '_', $sanitized);
        // Limit length
        return substr($sanitized, 0, 30);
    }
    
    private function sanitizeFileName($filename)
    {
        // Remove special characters, keep only alphanumeric, hyphens, and underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $filename);
        // Remove multiple underscores
        $sanitized = preg_replace('/_{2,}/', '_', $sanitized);
        // Limit length
        return substr($sanitized, 0, 50);
    }
   
    private function categorizeFileType($extension)
    {
        $extension = strtolower($extension);
        
        $categories = [
            'pdf' => 'PDF Document',
            'doc' => 'Word Document',
            'docx' => 'Word Document', 
            'jpg' => 'Image',
            'jpeg' => 'Image',
            'png' => 'Image',
            'gif' => 'Image',
            'txt' => 'Text Document',
            'rtf' => 'Rich Text Document',
        ];
        
        return $categories[$extension] ?? 'Unknown';
    }
    
}