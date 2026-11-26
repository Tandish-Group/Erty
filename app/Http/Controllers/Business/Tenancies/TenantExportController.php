<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Controllers\Controller;
use App\Services\Rent\CustomExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Custom exports of tenants, payments and notices (CSV or Excel). */
class TenantExportController extends Controller
{
    public function __construct(
        private CustomExportService $customExportService,
    ) {
        $this->middleware('auth');
    }

    /**
     * Custom export with advanced filtering and data selection
     */
    public function customExport(Request $request)
    {
        try {
            $userId = business_owner_id();
            
            // Validate request
            $request->validate([
                'export_types' => 'required|array|min:1',
                'export_types.*' => 'in:tenants,payments,notices',
                'export_format' => 'required|in:excel,csv',
                'tenant_options' => 'nullable|array',
                'tenant_profile_options' => 'nullable|array',
                'tenancy_detail_options' => 'nullable|array',
                'date_range' => 'nullable|string',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
            ]);

            $exportTypes = $request->export_types;
            $includeOptions = [
                'tenant_options' => $request->tenant_options ?? [],
                'tenant_profile_options' => $request->tenant_profile_options ?? [],
                'tenancy_detail_options' => $request->tenancy_detail_options ?? [],
                'payment_options' => $request->payment_options ?? [],
                'notice_options' => $request->notice_options ?? [],
            ];
    
            // Build the comprehensive dataset
            $allSheets = [];
    
            // Process each export type
            if (in_array('tenants', $exportTypes)) {
                $tenantData = $this->customExportService->buildTenantExportData($userId, $request, $includeOptions['tenant_options']);
                
                // Check if it's multi-sheet data
                if (is_array($tenantData) && isset($tenantData['Tenant_Profiles']) || isset($tenantData['Tenancy_Details'])) {
                    $allSheets = array_merge($allSheets, $tenantData);
                } else {
                    $allSheets['Tenants'] = $tenantData;
                }
            }
    
            if (in_array('payments', $exportTypes)) {
                $paymentData = $this->customExportService->buildPaymentExportData($userId, $request, $includeOptions['payment_options']);
                
                // Check if payment data returns multiple sheets or single sheet
                if (is_array($paymentData) && isset($paymentData['billing'])) {
                    // Multiple sheets returned
                    $allSheets = array_merge($allSheets, $paymentData);
                } else {
                    // Single sheet returned (backward compatibility)
                    $allSheets['Payments'] = $paymentData;
                }
            }
    
            if (in_array('notices', $exportTypes)) {
                $noticeData = $this->customExportService->buildNoticeExportData($userId, $request, $includeOptions['notice_options']);
                $allSheets['Notices'] = $noticeData;
            }
    
            // A data set with nothing ticked comes back empty: leave it out.
            $allSheets = array_filter($allSheets, fn ($sheet) => !empty($sheet));
            if (empty($allSheets)) {
                return response()->json([
                    'message' => 'Nothing to export. Choose at least one set of details to include.'
                ], 422);
            }
    
            // Generate filename
            $filename = $this->customExportService->generateExportFilename($exportTypes, $request->export_format);
    
            // Return file based on format
            if ($request->export_format === 'csv') {
                // For CSV with multiple sheets, create ZIP file
                if (count($allSheets) > 1) {
                    return $this->customExportService->generateMultiCsvExport($allSheets, $filename);
                } else {
                    // Single sheet CSV
                    $sheetData = array_values($allSheets)[0];
                    return $this->customExportService->generateCsvExport($sheetData['data'], $filename);
                }
            } else {
                // Use multi-sheet Excel export
                return $this->customExportService->generateMultiSheetExcelExport($allSheets, $filename);
            }
    
        } catch (\Exception $e) {
            Log::error('Custom export failed: ' . $e->getMessage(), [
                'user_id' => business_owner_id(),
                'request_data' => $request->all(),
                'error' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => 500,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
