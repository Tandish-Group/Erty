<?php

namespace App\Jobs;

use App\Services\Tenancy\BulkUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessBulkUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $uploadType;
    protected $fileContent; // Store content instead of path
    protected $fieldMappings;
    protected $batchId;
    protected $userId;

    /**
     * Job timeout in seconds
     */
    public $timeout = 1800; // 30 minutes

    /**
     * Number of times the job may be attempted
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($uploadType, $filePath, $fieldMappings, $batchId, $userId)
    {
        $this->uploadType = $uploadType;
        $this->fieldMappings = $fieldMappings;
        $this->batchId = $batchId;
        $this->userId = $userId;
        
        // Read and store file content immediately to avoid file access issues
        if (Storage::disk('local')->exists($filePath)) {
            $this->fileContent = Storage::disk('local')->get($filePath);
            
            // Clean up the original file after reading
            Storage::disk('local')->delete($filePath);
            
            Log::info("File content stored for job", [
                'batch_id' => $batchId,
                'content_length' => strlen($this->fileContent),
                'original_path' => $filePath
            ]);
        } else {
            throw new \Exception("File not found at path: {$filePath}");
        }
    }

    /**
     * Execute the job.
     */
    public function handle(BulkUploadService $bulkUploadService): void
    {
        Log::info("Starting bulk upload job for {$this->uploadType}", [
            'batch_id' => $this->batchId,
            'user_id' => $this->userId,
            'content_length' => strlen($this->fileContent),
            'field_mappings' => $this->fieldMappings
        ]);

        try {
            DB::transaction(function () use ($bulkUploadService) {
                // Create a temporary file from the stored content
                $tempFile = $this->createTempFileFromContent();
                
                // Log temp file details
                Log::info("Temporary file created from content", [
                    'batch_id' => $this->batchId,
                    'temp_file_path' => $tempFile->getRealPath(),
                    'temp_file_exists' => file_exists($tempFile->getRealPath()),
                    'temp_file_size' => file_exists($tempFile->getRealPath()) ? filesize($tempFile->getRealPath()) : 'N/A',
                    'temp_file_readable' => is_readable($tempFile->getRealPath())
                ]);

                // Parse CSV file using the service
                $csvData = $bulkUploadService->parseCsvFile($tempFile);

                Log::info("CSV parsing results", [
                    'batch_id' => $this->batchId,
                    'rows_count' => count($csvData),
                    'csvData_type' => gettype($csvData),
                    'sample_rows' => array_slice($csvData, 0, 5) // Log first 5 rows as sample
                ]);
                
                if (empty($csvData)) {
                    Log::warning("CSV data is empty, checking file content", [
                        'batch_id' => $this->batchId,
                        'file_first_100_chars' => file_exists($tempFile->getRealPath()) 
                            ? substr(file_get_contents($tempFile->getRealPath()), 0, 100) 
                            : 'File not found'
                    ]);
                }

                // Process the bulk upload using the service
                $results = $bulkUploadService->storeBulkDataInTempTable(
                    $this->uploadType,
                    $csvData,
                    $this->fieldMappings,
                    $this->batchId,
                    $this->userId
                );

                // Clean up temporary file
                if (file_exists($tempFile->getRealPath())) {
                    unlink($tempFile->getRealPath());
                }

                Log::info("Bulk upload job completed successfully", [
                    'batch_id' => $this->batchId,
                    'user_id' => $this->userId,
                    'results' => $results
                ]);

                // Mark batch as completed in a status table (optional)
                $this->markBatchAsCompleted($results);
            });

        } catch (\Exception $e) {
            Log::error("Bulk upload job failed", [
                'batch_id' => $this->batchId,
                'user_id' => $this->userId,
                'upload_type' => $this->uploadType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update batch status to failed
            $this->markBatchAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Create temporary file from stored content
     */
    private function createTempFileFromContent()
    {
        Log::info("Creating temporary file from stored content", [
            'batch_id' => $this->batchId,
            'content_length' => strlen($this->fileContent)
        ]);

        if (empty($this->fileContent)) {
            throw new \Exception("No file content available for processing");
        }

        $tempPath = sys_get_temp_dir() . '/' . uniqid('bulk_upload_') . '.csv';
        
        if (file_put_contents($tempPath, $this->fileContent) === false) {
            throw new \Exception("Failed to create temporary file at: {$tempPath}");
        }
        
        Log::info("Temporary file written from content", [
            'batch_id' => $this->batchId,
            'temp_path' => $tempPath,
            'temp_file_size' => filesize($tempPath)
        ]);
        
        return new \SplFileInfo($tempPath);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Bulk upload job failed permanently", [
            'batch_id' => $this->batchId,
            'user_id' => $this->userId,
            'upload_type' => $this->uploadType,
            'error' => $exception->getMessage()
        ]);

        $this->markBatchAsFailed($exception->getMessage());
    }

    /**
     * Mark the batch as completed
     */
    private function markBatchAsCompleted($results): void
    {
        try {
            $tables = [
                'tenants' => 'temp_tenant_profiles',
                'payments' => 'temp_tenant_payments',
                'notices' => 'temp_tenant_notices'
            ];

            $tableName = $tables[$this->uploadType] ?? null;
            
            if ($tableName) {
                DB::table($tableName)
                    ->where('batch_id', $this->batchId)
                    ->where('user_id', $this->userId)
                    ->where('processing_status', 'pending')
                    ->update([
                        'processing_status' => 'processed',
                        'updated_at' => now()
                    ]);
            }

            // Optionally create a batch status record
            DB::table('bulk_upload_batches')->updateOrInsert(
                [
                    'batch_id' => $this->batchId,
                    'user_id' => $this->userId
                ],
                [
                    'upload_type' => $this->uploadType,
                    'status' => 'completed',
                    'successful_records' => $results['successful'],
                    'failed_records' => $results['failed'],
                    'total_records' => $results['successful'] + $results['failed'],
                    'completed_at' => now(),
                    'updated_at' => now()
                ]
            );

        } catch (\Exception $e) {
            Log::error("Failed to mark batch as completed: " . $e->getMessage());
        }
    }

    /**
     * Mark the batch as failed in the database
     */
    private function markBatchAsFailed($errorMessage): void
    {
        try {
            $tables = [
                'tenants' => 'temp_tenant_profiles',
                'payments' => 'temp_tenant_payments',
                'notices' => 'temp_tenant_notices'
            ];

            $tableName = $tables[$this->uploadType] ?? null;
            
            if ($tableName) {
                DB::table($tableName)
                    ->where('batch_id', $this->batchId)
                    ->where('user_id', $this->userId)
                    ->update([
                        'processing_status' => 'failed',
                        'error_message' => $errorMessage,
                        'updated_at' => now()
                    ]);
            }

            // Update batch status record
            DB::table('bulk_upload_batches')->updateOrInsert(
                [
                    'batch_id' => $this->batchId,
                    'user_id' => $this->userId
                ],
                [
                    'upload_type' => $this->uploadType,
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'failed_at' => now(),
                    'updated_at' => now()
                ]
            );

        } catch (\Exception $e) {
            Log::error("Failed to mark batch as failed: " . $e->getMessage());
        }
    }
}