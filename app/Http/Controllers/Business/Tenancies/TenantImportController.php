<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkUploadJob;
use App\Models\Legal;
use App\Models\TenantInvoice;
use App\Models\TenantManagement;
use App\Models\TenantNotice;
use App\Models\TenantProfile;
use App\Services\Tenancy\TenantStoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Bulk import of tenants, payments and notices: templates, upload, column mapping, then save or cancel. */
class TenantImportController extends Controller
{
    public function __construct(
        private TenantStoreService $tenantStoreService,
    ) {
        $this->middleware('auth');
    }

    /**
     * Download tenant, payment, notice bulk upload template
     */
    /** Columns of the tenants import (one row per tenancy), in template order. */
    public const TENANT_COLUMNS = ['tenant_type', 'tenant_name', 'tenant_email', 'tenant_phone', 'tenant_id_number',
        'business_name', 'business_registration', 'business_contact_person', 'business_email', 'business_phone',
        'property', 'unit_number', 'occupied_sq_area', 'monthly_rent', 'payment_frequency', 'start_date', 'end_date',
        'deposit', 'deposit_held', 'balance_owed', 'currency', 'service_charge_per_sq_ft', 'emergency_contact_name', 'emergency_contact_phone', 'notes'];

    /**
     * The tenants import template: one row per tenancy that already exists outside Erty, with the tenant, the
     * property and unit, the rent and when it started, the deposit held and the balance owed today
     * (App\Services\Tenancy\TenantImport). The example rows use made-up properties: delete them before uploading.
     */
    public function downloadTenantBulkUploadTemplate()
    {
        $start = now()->subMonths(8)->startOfMonth();
        $sampleData = [
            ['Individual', 'Jane Wanjiru (example)', 'jane.example@example.com', '+254712000001', '12345678', '', '', '', '', '',
                'Example Gardens', 'A-101', '', '45000', 'Monthly', $start->toDateString(), $start->copy()->addYear()->subDay()->toDateString(),
                '45000', '45000', '0', 'KES', '', 'Peter Wanjiru', '+254712000002', 'Example: delete the example rows before uploading.'],
            ['Individual', 'Otieno Ouma (example)', 'otieno.example@example.com', '+254712000003', '23456789', '', '', '', '', '',
                'Example Gardens', 'B-204', '', '30000', 'Monthly', $start->copy()->addMonths(3)->toDateString(), '',
                '30000', '30000', '15000', 'KES', '', '', '', 'Example: owes 15,000 today; billing continues from the next period'],
            ['Business', '', '', '', '', 'Example Traders Ltd', 'PVT-EXAMPLE1', 'Mary Achieng', 'accounts@example.com', '+254701000004',
                'Example Business Park', 'G-01', '800', '96000', 'Quarterly', $start->toDateString(), $start->copy()->addYears(3)->subDay()->toDateString(),
                '192000', '192000', '-5000', 'KES', '15', '', '', 'Example: commercial space, 800 sq ft; 5,000 in credit'],
        ];

        return $this->generateCsvTemplate('erty-tenants-template.csv', self::TENANT_COLUMNS, $sampleData);
    }

    /**
     * The payments import template: one row per payment received. Each payment settles the tenant's oldest unpaid
     * invoice first and anything extra stays as credit (TenantAccountService::record), so no invoice or allocation
     * columns are needed. The example rows use made-up references: delete them before uploading.
     */
    public function downloadPaymentBulkUploadTemplate()
    {
        $headers = ['tenancy_ref', 'tenant_profile_ref', 'tenant_email', 'payment_date', 'amount', 'payment_method',
            'transaction_reference', 'invoice_number', 'cleared', 'payment_notes'];
        $month = now()->startOfMonth();
        $sampleData = [
            ['TMEXAMPLE01', '', '', $month->copy()->addDays(2)->toDateString(), '45000', 'mobile_money', 'SKE4H7XY2P', '', 'yes', 'Example: rent paid by M-Pesa. Delete the example rows before uploading.'],
            ['TMEXAMPLE02', '', '', $month->copy()->addDays(4)->toDateString(), '20000', 'bank_transfer', 'FT2611ABC123', '', 'yes', 'Example: part payment; settles the oldest unpaid invoice first'],
            ['', 'TPEXAMPLE03', '', $month->copy()->addDays(5)->toDateString(), '30000', 'check', 'CHQ 004512', '', 'no', 'Example: cheque not cleared yet; applied when you confirm it'],
            ['', '', 'tenant@example.com', $month->copy()->addDays(6)->toDateString(), '5000', 'cash', '', 'INV-EXAMPLE-0004', 'yes', 'Example: pays a particular invoice first'],
        ];

        return $this->generateCsvTemplate('erty-payments-template.csv', $headers, $sampleData);
    }

    /** The notices import template: notices already given outside Erty, kept on the tenancy for the record. */
    public function downloadNoticeBulkUploadTemplate()
    {
        $headers = ['tenancy_ref', 'tenant_profile_ref', 'tenant_email', 'notice_type', 'notice_date', 'effective_date',
            'notice_subject', 'notice_details', 'notice_period', 'delivery_methods'];
        $sampleData = [
            ['TMEXAMPLE01', '', '', 'lease_termination', now()->subDays(20)->toDateString(), now()->addDays(10)->toDateString(),
                'Notice to vacate', 'Example: the tenant gave 30 days notice. Delete the example rows before uploading.', '30', 'email,physical'],
            ['', 'TPEXAMPLE02', '', 'rent_increase', now()->subDays(40)->toDateString(), now()->addDays(50)->toDateString(),
                'Rent review', 'Example: rent rises from the next lease year.', '90', 'physical'],
        ];

        return $this->generateCsvTemplate('erty-notices-template.csv', $headers, $sampleData);
    }

    /**
    * Process bulk upload with field mappings
    */
    public function processBulkUpload(Request $request)
    {
        $this->assertWholeBusinessImport();
        $request->validate([
            'upload_type' => 'required|in:tenants,payments,notices',
            'data_file' => 'required|file|mimes:csv,txt|max:10240',
            'field_mappings' => 'required|json'
        ]);
    
        try {
            $uploadType = $request->upload_type;
            $fieldMappings = json_decode($request->field_mappings, true);
            $file = $request->file('data_file');
            $userId = business_owner_id();
    
            // Validate field mappings JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid field mappings JSON: ' . json_last_error_msg());
            }
    
            // Generate unique batch ID
            $batchId = 'BATCH_' . strtoupper($uploadType) . '_' . now()->format('YmdHis') . '_' . $userId;
    
            // Store the uploaded file temporarily for job processing with explicit disk
            $filePath = $file->store('bulk_uploads/temp', 'local');
            
            // Verify file was actually stored
            if (!Storage::disk('local')->exists($filePath)) {
                throw new \Exception('Failed to store uploaded file');
            }
    
            Log::info("Bulk upload file stored and job dispatched", [
                'batch_id' => $batchId,
                'user_id' => $userId,
                'upload_type' => $uploadType,
                'file_path' => $filePath,
                'file_size' => Storage::disk('local')->size($filePath),
                'field_mappings_count' => count($fieldMappings)
            ]);
    
            // Create initial batch record in transaction
            DB::transaction(function () use ($batchId, $userId, $uploadType) {
                DB::table('bulk_upload_batches')->insert([
                    'batch_id' => $batchId,
                    'user_id' => $userId,
                    'upload_type' => $uploadType,
                    'status' => 'processing',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            });
    
            // Dispatch the job to handle the bulk upload
            ProcessBulkUploadJob::dispatch($uploadType, $filePath, $fieldMappings, $batchId, $userId);
    
            Log::info("ProcessBulkUploadJob dispatched successfully", [
                'batch_id' => $batchId,
                'job_class' => ProcessBulkUploadJob::class
            ]);
    
            return response()->json([
                'status' => 200,
                'message' => "Bulk upload started! Your {$uploadType} data is being processed in the background.",
                'batch_id' => $batchId,
                'processing' => true
                // Remove the route reference for now
                // 'check_status_url' => route('tenant_management.bulk_upload_status', $batchId)
            ]);
    
        } catch (\Exception $e) {
            // Clean up stored file if it exists
            if (isset($filePath) && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
    
            Log::error('Bulk upload processing failed: ' . $e->getMessage(), [
                'upload_type' => $request->upload_type ?? 'unknown',
                'user_id' => business_owner_id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => 500,
                'message' => 'Processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
    /** Review imports: every staged row, checked the way it will be imported, with what to fix. */
    public function showMappingPage()
    {
        $this->assertWholeBusinessImport();
        $owner = business_owner_id();

        return view('pages.business.tenant.mapping', [
            'tenants' => app(\App\Services\Tenancy\TenantImport::class)->staged($owner),
            'payments' => app(\App\Services\Rent\PaymentImport::class)->staged($owner),
            'notices' => app(\App\Services\Tenancy\NoticeImport::class)->staged($owner),
        ]);
    }

    /**
     * Save field mapping configuration
     */
    public function saveMappingData(Request $request)
    {
        $this->assertWholeBusinessImport();
        $request->validate([
            'type' => 'required|in:tenant,payment,notice',
            'selected_ids' => 'required|array|min:1',
            'id_batch_map' => 'required|array',
        ]);

    
        $type = $request->type;
        $selectedIds = $request->selected_ids;
        $idBatchMap = $request->id_batch_map;
    
        // Group selected IDs by batch_id
        $batchGroups = [];
        foreach ($selectedIds as $id) {
            $batchId = $idBatchMap[$id] ?? null;
            if ($batchId) {
                $batchGroups[$batchId][] = $id;
            }
        }
    
        try {
            $result = DB::transaction(function () use ($type, $batchGroups) {
                $summary = ['success' => 0, 'failed' => 0, 'errors' => []];
                foreach ($batchGroups as $batchId => $ids) {
                    switch ($type) {
                        case 'tenant':
                            $res = $this->processTempTenants($batchId, $ids);
                            break;
                        case 'payment':
                            $res = $this->processTempPayments($batchId, $ids);
                            break;
                        case 'notice':
                            $res = $this->processTempNotices($batchId, $ids);
                            break;
                        default:
                            throw new \Exception('Invalid type');
                    }
                    // Aggregate results
                    $summary['success'] += $res['success'] ?? 0;
                    $summary['failed'] += $res['failed'] ?? 0;
                    if (!empty($res['errors'])) {
                        $summary['errors'] = array_merge($summary['errors'], $res['errors']);
                    }
                }
                return $summary;
            });
    
            $message = $result['failed'] > 0
                ? "{$result['success']} imported, {$result['failed']} not imported: " . implode(' ', array_slice($result['errors'], 0, 5))
                : "{$result['success']} " . ($type === 'payment' ? 'payments' : "{$type} records") . ' imported.';

            return response()->json([
                'status' => 200,
                'message' => $message,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error("Bulk import failed: " . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => "Bulk import failed: " . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Cancel mapping and clear temp data
     */
    public function cancelMappingData(Request $request)
    {
        $this->assertWholeBusinessImport();
        $request->validate([
            'type' => 'required|in:tenant,payment,notice',
            'batch_id' => 'required|string',
        ]);
    
        $type = $request->type;
        $batchId = $request->batch_id;
    
        try {
            $result = DB::transaction(function () use ($type, $batchId) {
                switch ($type) {
                    case 'tenant':
                        $this->cancelTempTenants($batchId);
                        break;
    
                    case 'payment':
                        $this->cancelTempPayments($batchId);
                        break;
    
                    case 'notice':
                        $this->cancelTempNotices($batchId);
                        break;
    
                    default:
                        throw new \Exception('Invalid type provided.');
                }
    
                return [
                    'status' => 200,
                    'message' => ucfirst($type) . ' mapping data canceled successfully.',
                ];
            });
    
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to cancel mapping data: ' . $e->getMessage(), [
                'type' => $type,
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);
    
            return response()->json([
                'status' => 500,
                'message' => 'Failed to cancel mapping data. Please try again.',
            ], 500);
        }
    }
    private function generateCsvTemplate($filename, $headers, $sampleData)
    {
        // Create a temporary file in the system temp directory with a proper filename
        $tempPath = sys_get_temp_dir() . '/' . uniqid('csv_template_') . '.csv';
        
        // Create and write CSV data
        $file = fopen($tempPath, 'w');
        
        // Add BOM for better Excel compatibility
        fwrite($file, "\xEF\xBB\xBF");
        
        // Write headers
        fputcsv($file, $headers);
        
        // Write sample data
        foreach ($sampleData as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        // Return download response with proper headers
        return response()->download($tempPath, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ])->deleteFileAfterSend(true);
    }
    /** Bulk upload stages rows for the whole business: not for people limited to assigned properties. */
    private function assertWholeBusinessImport(): void
    {
        abort_if(auth()->user()?->limitedToAssignedProperties(), 403, 'Bulk upload is for people who work with every property.');
    }
    /** Import the chosen tenant rows: each becomes a tenant (new or existing) with their tenancy. */
    private function processTempTenants($batchId, $selectedIds)
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        $import = app(\App\Services\Tenancy\TenantImport::class);
        $rows = DB::table('temp_tenant_profiles')->where('user_id', business_owner_id())->where('batch_id', $batchId)
            ->whereIn('id', $selectedIds)->orderBy('row_number')->get();
        $tenancies = DB::table('temp_tenant_management')->whereIn('temp_tenant_profile_id', $rows->pluck('id'))->get()->keyBy('temp_tenant_profile_id');

        foreach ($rows as $row) {
            try {
                $import->import($row, $tenancies[$row->id] ?? null);
                DB::table('temp_tenant_management')->where('temp_tenant_profile_id', $row->id)->delete();
                DB::table('temp_tenant_profiles')->where('id', $row->id)->delete();
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $message = $e instanceof \Illuminate\Validation\ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();
                $errors[] = 'Row ' . $row->row_number . ': ' . $message;
                DB::table('temp_tenant_profiles')->where('id', $row->id)->update(['status' => 'failed', 'error_message' => $message, 'updated_at' => now()]);
            }
        }
        Log::info('Tenancies imported', ['batch_id' => $batchId, 'imported' => $success, 'failed' => $failed]);

        return compact('success', 'failed', 'errors');
    }

    /** Import the chosen staged payments; each is checked again and recorded on the tenant's account. */
    private function processTempPayments($batchId, $selectedIds)
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        $import = app(\App\Services\Rent\PaymentImport::class);
        $rows = DB::table('temp_tenant_payments')->where('user_id', business_owner_id())->where('batch_id', $batchId)
            ->whereIn('id', $selectedIds)->orderBy('row_number')->get();

        foreach ($rows as $row) {
            try {
                $import->import($row);
                DB::table('temp_tenant_payments')->where('id', $row->id)->delete();
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $message = $e instanceof \Illuminate\Validation\ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();
                $errors[] = 'Row ' . $row->row_number . ': ' . $message;
                DB::table('temp_tenant_payments')->where('id', $row->id)
                    ->update(['processing_status' => 'failed', 'error_message' => $message, 'updated_at' => now()]);
            }
        }
        Log::info('Payments imported', ['batch_id' => $batchId, 'imported' => $success, 'failed' => $failed]);

        return compact('success', 'failed', 'errors');
    }

    /** Import the chosen notices onto their tenancies, for the record (not sent again). */
    private function processTempNotices($batchId, $selectedIds)
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        $import = app(\App\Services\Tenancy\NoticeImport::class);
        $rows = DB::table('temp_tenant_notices')->where('user_id', business_owner_id())->where('batch_id', $batchId)
            ->whereIn('id', $selectedIds)->orderBy('row_number')->get();

        foreach ($rows as $row) {
            try {
                $import->import($row);
                DB::table('temp_tenant_notices')->where('id', $row->id)->delete();
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Row ' . $row->row_number . ': ' . $e->getMessage();
                DB::table('temp_tenant_notices')->where('id', $row->id)->update(['processing_status' => 'failed', 'error_message' => $e->getMessage(), 'updated_at' => now()]);
            }
        }

        return compact('success', 'failed', 'errors');
    }

    private function cancelTempTenants($batchId)
    {
        DB::table('temp_tenant_profiles')->where('user_id', business_owner_id())->where('batch_id', $batchId)->delete();
        DB::table('temp_tenant_management')->where('user_id', business_owner_id())->where('batch_id', $batchId)->delete();
    
        Log::info("Canceled tenant mapping data for batch: {$batchId}");
    }
    private function cancelTempPayments($batchId)
    {
        DB::table('temp_tenant_payments')->where('user_id', business_owner_id())->where('batch_id', $batchId)->delete();
    
        Log::info("Canceled payment mapping data for batch: {$batchId}");
    }
    private function cancelTempNotices($batchId)
    {
        DB::table('temp_tenant_notices')->where('user_id', business_owner_id())->where('batch_id', $batchId)->delete();
    
        Log::info("Canceled notice mapping data for batch: {$batchId}");
    }
}
