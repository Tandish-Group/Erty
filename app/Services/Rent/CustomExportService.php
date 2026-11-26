<?php

namespace App\Services\Rent;

use App\Models\TenantNotice;
use App\Models\TenantBilling;
use App\Models\TenantInvoice;
use App\Models\TenantPayment;
use App\Models\TenantProfile;
use App\Models\TenantManagement;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CustomExportService
{

    /**
     * Build tenant export data based on selected options and filters
     */

    public function buildTenantExportData($userId, $request, $tenantOptions)
    {
        $exportData = [];
        
        // Check what data types are requested
        $exportProfiles = in_array('tenant_profiles', $tenantOptions);
        $exportTenancies = in_array('tenancy_details', $tenantOptions);
        
        // Get profile options and tenancy options
        $profileOptions = $request->input('tenant_profile_options', []);
        $tenancyOptions = $request->input('tenancy_detail_options', []);
        
        Log::info('Tenant export options', [
            'export_profiles' => $exportProfiles,
            'export_tenancies' => $exportTenancies,
            'profile_options' => $profileOptions,
            'tenancy_options' => $tenancyOptions
        ]);
        
        // Build tenant profiles data if requested
        if ($exportProfiles) {
            $exportData['Tenant_Profiles'] = $this->buildTenantProfilesData($userId, $request, $profileOptions);
        }
        
        // Build tenancy details data if requested  
        if ($exportTenancies) {
            $exportData['Tenancy_Details'] = $this->buildTenancyDetailsData($userId, $request, $tenancyOptions);
        }
        
        // If only one type requested, return in legacy format for backward compatibility
        if (count($exportData) === 1) {
            return array_values($exportData)[0];
        }
        
        return $exportData;
    }

    public function buildPaymentExportData($userId, $request, $paymentOptions)
    {
        // Check if specific financial components are requested
        $exportBilling = in_array('billing_records', $paymentOptions);
        $exportInvoices = in_array('invoice_details', $paymentOptions);
        $exportPayments = in_array('payment_records', $paymentOptions);
        
        // If no specific options selected, export all (backward compatibility)
        if (empty($paymentOptions) || in_array('payment_details', $paymentOptions)) {
            $exportBilling = $exportInvoices = $exportPayments = true;
        }
        
        $exportData = [];
        
        // Build billing records data
        if ($exportBilling) {
            $exportData['billing'] = $this->buildBillingRecordsData($userId, $request);
        }
        
        // Build invoice details data  
        if ($exportInvoices) {
            $exportData['invoices'] = $this->buildInvoiceDetailsData($userId, $request);
        }
        
        // Build payment records data
        if ($exportPayments) {
            $exportData['payments'] = $this->buildPaymentRecordsData($userId, $request);
        }
        
        // If only one type requested, return in legacy format for backward compatibility
        if (count($exportData) === 1) {
            return array_values($exportData)[0];
        }
        
        return $exportData;
    }

    public function buildNoticeExportData($userId, $request, $noticeOptions)
    {
        $query = TenantNotice::whereHas('tenant', function($q) use ($userId) {
            $q->where('managed_by', $userId);
        })->with(['tenant.tenantProfile', 'issuedBy']);

        // Apply notice-specific filters
        $query = $this->applyNoticeFilters($query, $request);

        // IMPORTANT: Handle specific tenant selection for notices
        if ($request->filled('specific_tenant') && is_array($request->specific_tenant)) {
            $tenantRefs = $request->specific_tenant;
            Log::info('Filtering notices by specific tenants', ['tenant_refs' => $tenantRefs]);
            
            $query->whereHas('tenant.tenantProfile', function($q) use ($tenantRefs) {
                $q->whereIn('tenant_ref', $tenantRefs);
            });
        }

        // Also apply general tenant filters if specified
        if ($request->tenant_status) {
            $query->whereHas('tenant', function($q) use ($request) {
                $q->where('status', $request->tenant_status);
            });
        }

        if ($request->tenant_type) {
            $query->whereHas('tenant.tenantProfile', function($q) use ($request) {
                $q->where('tenant_type', $request->tenant_type);
            });
        }

        $notices = $query->get();
        
        Log::info('Notice export query results', [
            'total_notices' => $notices->count(),
            'filters_applied' => [
                'date_range' => $request->date_range,
                'notice_type' => $request->notice_type,
                'delivery_status' => $request->notice_delivery_status,
                'specific_tenant' => $request->specific_tenant,
            ]
        ]);

        $headers = [];
        $data = [];

        foreach ($notices as $notice) {
            $tenant = $notice->tenant;
            $profile = $tenant->tenantProfile;
            
            $key = $tenant->tenancy_ref . '_notice_' . $notice->id;
            $row = ['tenancy_ref' => $tenant->tenancy_ref];

            // Basic notice information
            $row['Tenant Name'] = $profile ? ($profile->tenant_type === 'Business' ? $profile->business_name : $profile->tenant_name) : '';
            $row['Notice Type'] = ucwords(str_replace('_', ' ', $notice->notice_type));
            $row['Notice Date'] = $notice->notice_date->format('Y-m-d');
            $row['Effective Date'] = $notice->effective_date->format('Y-m-d');
            $basicHeaders = ['Tenant Name', 'Notice Type', 'Notice Date', 'Effective Date'];

            if (in_array('notice_content', $noticeOptions)) {
                $row['Notice Subject'] = $notice->notice_subject;
                $row['Notice Details'] = strip_tags($notice->notice_details);
                $basicHeaders = array_merge($basicHeaders, ['Notice Subject', 'Notice Details']);
            }

            if (in_array('delivery_status', $noticeOptions)) {
                $row['Delivery Status'] = ucfirst($notice->delivery_status);
                $row['Sent At'] = $notice->sent_at ? $notice->sent_at->format('Y-m-d H:i:s') : '';
                $basicHeaders = array_merge($basicHeaders, ['Delivery Status', 'Sent At']);
            }

            $headers = array_merge($headers, $basicHeaders);
            $data[$key] = $row;
        }

        return ['data' => $data, 'headers' => array_unique($headers)];
    }
    
    
    public function generateExportFilename($exportTypes, $format)
    {
        $typeString = implode('_', $exportTypes);
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        // Fix the file extension based on format
        $extension = $format === 'excel' ? 'xlsx' : $format;
        
        return "tenant_export_{$typeString}_{$timestamp}.{$extension}";
    }
    
    public function generateCsvExport($data, $filename)
    {
        $tempPath = sys_get_temp_dir() . '/' . uniqid('csv_export_') . '.csv';
        $file = fopen($tempPath, 'w');
        
        fwrite($file, "\xEF\xBB\xBF"); // BOM for UTF-8
        
        if (!empty($data)) {
            fputcsv($file, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }
        
        fclose($file);
        
        return response()->download($tempPath, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ])->deleteFileAfterSend(true);
    }

    public function generateMultiCsvExport($allSheets, $baseFilename)
    {
        $zip = new \ZipArchive();
        $zipPath = sys_get_temp_dir() . '/' . uniqid('csv_export_') . '.zip';
        
        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
            foreach ($allSheets as $sheetName => $sheetData) {
                $csvContent = $this->generateCsvContent($sheetData['data'], $sheetData['headers']);
                $zip->addFromString($sheetName . '.csv', $csvContent);
            }
            $zip->close();
            
            $zipFilename = str_replace('.csv', '.zip', $baseFilename);
            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
        }
        
        throw new \Exception('Failed to create ZIP file');
    }
    
    
    /**
     * Apply filters to tenant export query
     */

    private function generateCsvContent($data, $headers)
    {
        $output = "\xEF\xBB\xBF"; // BOM for UTF-8
        $output .= implode(',', array_map([$this, 'escapeCsvField'], $headers)) . "\n";
        
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($headers as $header) {
                $csvRow[] = $this->escapeCsvField($row[$header] ?? '');
            }
            $output .= implode(',', $csvRow) . "\n";
        }
        
        return $output;
    }
    
    private function escapeCsvField($field)
    {
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }

    
    private function applyExportFilters($query, $request)
    {
        // Date range filtering
        if ($request->date_range && $request->date_range !== '') {
            $query = $this->applyDateRangeFilter($query, $request->date_range, $request->from_date, $request->to_date, 'tenant_management.created_at');
        }

        // Status filtering
        if ($request->filled('tenant_status') && $request->tenant_status !== '') {
            $query->where('tenant_management.status', $request->tenant_status);
        }

        // Tenant type filtering
        if ($request->filled('tenant_type') && $request->tenant_type !== '') {
            $query->whereHas('tenantProfile', function($q) use ($request) {
                $q->where('tenant_type', $request->tenant_type);
            });
        }

        // Property type filtering
        if ($request->filled('property_type') && $request->property_type !== '') {
            $query->where('tenant_management.listing_type', $request->property_type);
        }

        // Complex filtering - handle both single and multiple values
        if ($request->filled('complex')) {
            $complexes = is_array($request->complex) 
                ? $request->complex 
                : [$request->complex];
            
            if (count($complexes) > 0 && !in_array('', $complexes)) {
                $query->whereIn('tenant_management.complex_id', $complexes);
            }
        }

        // Monthly payment range
        if ($request->min_monthly_payment || $request->max_monthly_payment) {
            if ($request->min_monthly_payment) {
                $query->where('tenant_management.monthly_payment', '>=', $request->min_monthly_payment);
            }
            if ($request->max_monthly_payment) {
                $query->where('tenant_management.monthly_payment', '<=', $request->max_monthly_payment);
            }
        }

        return $query;
    }
    
    private function applyPaymentFilters($query, $request)
    {
        // Apply date range to payment_date
        if ($request->date_range && $request->date_range !== '') {
            $query = $this->applyDateRangeFilter($query, $request->date_range, $request->from_date, $request->to_date, 'payment_date');
        }

        // Payment amount range
        if ($request->min_amount || $request->max_amount) {
            if ($request->min_amount) {
                $query->where('amount_paid', '>=', $request->min_amount);
            }
            if ($request->max_amount) {
                $query->where('amount_paid', '<=', $request->max_amount);
            }
        }

        // Payment method - handle both single and multiple values
        if ($request->filled('payment_method')) {
            $methods = is_array($request->payment_method) 
                ? $request->payment_method 
                : [$request->payment_method];
            
            if (count($methods) > 0 && !in_array('', $methods)) {
                $query->whereIn('payment_method', $methods);
            }
        }

        // Payment status - handle both single and multiple values
        if ($request->filled('payment_status')) {
            $statuses = is_array($request->payment_status) 
                ? $request->payment_status 
                : [$request->payment_status];
            
            if (count($statuses) > 0 && !in_array('', $statuses)) {
                $query->whereIn('payment_status', $statuses);
            }
        }

        return $query;
    }
    
    private function applyNoticeFilters($query, $request)
    {
        // Apply date range to notice_date
        if ($request->date_range && $request->date_range !== '') {
            $query = $this->applyDateRangeFilter($query, $request->date_range, $request->from_date, $request->to_date, 'notice_date');
        }

        // Notice type - handle both single and multiple values
        if ($request->filled('notice_type')) {
            $types = is_array($request->notice_type) 
                ? $request->notice_type 
                : [$request->notice_type];
            
            if (count($types) > 0 && !in_array('', $types)) {
                $query->whereIn('notice_type', $types);
            }
        }

        // Delivery status - handle both single and multiple values
        if ($request->filled('notice_delivery_status')) {
            $statuses = is_array($request->notice_delivery_status) 
                ? $request->notice_delivery_status 
                : [$request->notice_delivery_status];
            
            if (count($statuses) > 0 && !in_array('', $statuses)) {
                $query->whereIn('delivery_status', $statuses);
            }
        }

        return $query;
    }
    
    private function applyDateRangeFilter($query, $dateRange, $fromDate, $toDate, $column)
    {
        switch ($dateRange) {
            case 'today':
                $query->whereDate($column, today());
                break;
            case 'yesterday':
                $query->whereDate($column, now()->yesterday());
                break;
            case 'last_7_days':
                $query->where($column, '>=', now()->subDays(7));
                break;
            case 'last_30_days':
                $query->where($column, '>=', now()->subDays(30));
                break;
            case 'this_month':
                $query->whereMonth($column, now()->month)
                      ->whereYear($column, now()->year);
                break;
            case 'last_month':
                $query->whereMonth($column, now()->subMonth()->month)
                      ->whereYear($column, now()->subMonth()->year);
                break;
            case 'this_quarter':
                $quarter = ceil(now()->month / 3);
                $startMonth = ($quarter - 1) * 3 + 1;
                $endMonth = $quarter * 3;
                $query->whereMonth($column, '>=', $startMonth)
                      ->whereMonth($column, '<=', $endMonth)
                      ->whereYear($column, now()->year);
                break;
            case 'this_year':
                $query->whereYear($column, now()->year);
                break;
            case 'last_year':
                $query->whereYear($column, now()->subYear()->year);
                break;
            case 'custom':
                if ($fromDate && $toDate) {
                    $query->whereBetween($column, [$fromDate, $toDate]);
                } elseif ($fromDate) {
                    $query->where($column, '>=', $fromDate);
                } elseif ($toDate) {
                    $query->where($column, '<=', $toDate);
                }
                break;
        }
    
        return $query;
    }
    
    
    
    

    private function buildBillingRecordsData($userId, $request)
    {
        $query = TenantBilling::whereHas('tenant', function($q) use ($userId) {
            $q->where('managed_by', $userId);
        })->with([
            'tenant.tenantProfile',
            'tenant.complex',
            'tenancyBilling',
        ]);

        // Apply existing payment filters (reuse the existing method)
        $query = $this->applyBillingFilters($query, $request);
        
        // Apply tenant selection filters
        if ($request->filled('specific_tenant') && is_array($request->specific_tenant)) {
            $tenantRefs = $request->specific_tenant;
            $query->whereHas('tenant.tenantProfile', function($q) use ($tenantRefs) {
                $q->whereIn('tenant_ref', $tenantRefs);
            });
        }

        $billings = $query->get();
        
        $headers = [
            'Tenancy Reference',
            'Tenant Name',
            'Created Date', // Changed from 'Billing Date'
            'Billing Type',
            'Billing Period Start',
            'Billing Period End',
            'Tenancy Amount',
            'Total Amount',
            'Due Date',
            'Status',
            'Complex Name'
        ];
        
        $data = [];
        
        foreach ($billings as $billing) {
            $tenant = $billing->tenant;
            $profile = $tenant->tenantProfile;
            
            // Calculate amounts from the 3-table structure
            $tenancyAmount = $billing->tenancyBilling ? $billing->tenancyBilling->tenancy_billing_amount : 0;
            $totalAmount = $tenancyAmount;
            
            // Get billing periods
            $periodStart = $billing->tenancyBilling?->billing_period_start;
            $periodEnd = $billing->tenancyBilling?->billing_period_end;
            
            // Get due date
            $dueDate = $billing->tenancyBilling?->payment_due_date;
            
            $data[] = [
                'Tenancy Reference' => $tenant->tenancy_ref,
                'Tenant Name' => $profile ? ($profile->tenant_type === 'Business' ? $profile->business_name : $profile->tenant_name) : '',
                'Created Date' => $billing->created_at ? $billing->created_at->format('Y-m-d') : '', // Changed from billing_date
                'Billing Type' => $this->formatBillingType($billing->billing_type),
                'Billing Period Start' => $periodStart ? $periodStart->format('Y-m-d') : '',
                'Billing Period End' => $periodEnd ? $periodEnd->format('Y-m-d') : '',
                'Tenancy Amount' => number_format($tenancyAmount, 2),
                'Total Amount' => number_format($totalAmount, 2),
                'Due Date' => $dueDate ? $dueDate->format('Y-m-d') : '',
                'Status' => $this->calculateBillingStatus($billing),
                'Complex Name' => $tenant->complex ? $tenant->complex->title : ''
            ];
        }
        
        return ['data' => $data, 'headers' => $headers];
    }

    private function buildInvoiceDetailsData($userId, $request)
    {
        $query = TenantInvoice::whereHas('tenancy', function($q) use ($userId) {
            $q->where('managed_by', $userId);
        })->with([
            'tenancy.tenantProfile',
            'tenancy.complex',
            'payments'
        ]);
    
        // Apply date filters to created_at instead of billing_date since billing_date doesn't exist
        if ($request->date_range && $request->date_range !== '') {
            $query = $this->applyDateRangeFilter($query, $request->date_range, $request->from_date, $request->to_date, 'created_at');
        }
        
        // Apply tenant selection filters
        if ($request->filled('specific_tenant') && is_array($request->specific_tenant)) {
            $tenantRefs = $request->specific_tenant;
            $query->whereHas('tenancy.tenantProfile', function($q) use ($tenantRefs) {
                $q->whereIn('tenant_ref', $tenantRefs);
            });
        }
    
        $invoices = $query->get();
        
        $headers = [
            'Tenancy Reference',
            'Tenant Name',
            'Invoice Number',
            'Invoice Type',
            'Created Date',
            'Due Date',
            'Amount',
            'Amount Paid',
            'Balance',
            'Status',
            'Billing Period',
            'Combined Invoice Number',
            'Complex Name'
        ];
        
        $data = [];
        
        foreach ($invoices as $invoice) {
            $tenant = $invoice->tenancy;
            $profile = $tenant->tenantProfile;
            
            $amountPaid = $invoice->payments->sum('amount_paid');
            $balance = $invoice->amount - $amountPaid;
            
            $data[] = [
                'Tenancy Reference' => $tenant->tenancy_ref,
                'Tenant Name' => $profile ? ($profile->tenant_type === 'Business' ? $profile->business_name : $profile->tenant_name) : '',
                'Invoice Number' => $invoice->invoice_number,
                'Invoice Type' => ucwords(str_replace('_', ' ', $invoice->invoice_type)),
                'Created Date' => $invoice->created_at ? $invoice->created_at->format('Y-m-d') : '',
                'Due Date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '',
                'Amount' => number_format($invoice->amount, 2),
                'Amount Paid' => number_format($amountPaid, 2),
                'Balance' => number_format($balance, 2),
                'Status' => $this->calculateInvoiceStatus($invoice->amount, $amountPaid, $invoice->due_date),
                'Billing Period' => $this->formatInvoicePeriod($invoice),
                'Combined Invoice Number' => $invoice->combined_invoice_number ?? '',
                'Complex Name' => $tenant->complex ? $tenant->complex->title : ''
            ];
        }
        
        return ['data' => $data, 'headers' => $headers];
    }

    private function buildPaymentRecordsData($userId, $request)
    {
        // Use the existing payment query logic but with updated headers
        $query = TenantPayment::whereHas('tenant', function($q) use ($userId) {
            $q->where('managed_by', $userId);
        })->with(['tenant.tenantProfile', 'invoice', 'recordedBy', 'verifiedBy']);

        // Apply existing payment filters
        $query = $this->applyPaymentFilters($query, $request);

        // Apply tenant selection filters
        if ($request->filled('specific_tenant') && is_array($request->specific_tenant)) {
            $tenantRefs = $request->specific_tenant;
            $query->whereHas('tenant.tenantProfile', function($q) use ($tenantRefs) {
                $q->whereIn('tenant_ref', $tenantRefs);
            });
        }

        $payments = $query->get();
        
        $headers = [
            'Tenancy Reference',
            'Tenant Name', 
            'Payment Date',
            'Invoice Number',
            'Payment Period',
            'Amount Paid',
            'Payment Method',
            'Payment Status',
            'Transaction Reference',
            'Late Fee',
            'Receipt Number',
            'Recorded By',
            'Verification Date',
            'Verified By',
            'Notes',
            'Complex Name'
        ];
        
        $data = [];
        
        foreach ($payments as $payment) {
            $tenant = $payment->tenant;
            $profile = $tenant->tenantProfile;
            $invoice = $payment->invoice;
            
            $data[] = [
                'Tenancy Reference' => $tenant->tenancy_ref,
                'Tenant Name' => $profile ? ($profile->tenant_type === 'Business' ? $profile->business_name : $profile->tenant_name) : '',
                'Payment Date' => $payment->payment_date->format('Y-m-d'),
                'Invoice Number' => $invoice ? $invoice->invoice_number : '',
                'Payment Period' => $payment->payment_period ?? '',
                'Amount Paid' => number_format($payment->amount_paid, 2),
                'Payment Method' => ucwords(str_replace('_', ' ', $payment->payment_method)),
                'Payment Status' => ucfirst($payment->payment_status),
                'Transaction Reference' => $payment->transaction_reference ?? '',
                'Late Fee' => number_format($payment->late_fee ?? 0, 2),
                'Receipt Number' => $payment->receipt_number ?? '',
                'Recorded By' => $payment->recordedBy ? $payment->recordedBy->name : '',
                'Verification Date' => $payment->verification_date ? $payment->verification_date->format('Y-m-d') : '',
                'Verified By' => $payment->verifiedBy ? $payment->verifiedBy->name : '',
                'Notes' => $payment->payment_notes ?? '',
                'Complex Name' => $tenant->complex ? $tenant->complex->title : ''
            ];
        }
        
        return ['data' => $data, 'headers' => $headers];
    }

    private function formatBillingType($billingType)
    {
        switch ($billingType) {
            case 'tenancy_only':
                return 'Tenancy Only';
            case 'service_charge_only':
                return 'Service Charge Only';
            case 'combined':
                return 'Combined Bill';
            default:
                return ucwords(str_replace('_', ' ', $billingType));
        }
    }

    private function calculateBillingStatus($billing)
    {
        // Get related invoices and their payment status
        $invoices = TenantInvoice::where('tenancy_id', $billing->tenancy_id)
            ->with('payments')
            ->get();
        
        if ($invoices->isEmpty()) {
            return 'No Invoices';
        }
        
        $totalAmount = $invoices->sum('amount');
        $totalPaid = $invoices->sum(function($invoice) {
            return $invoice->payments->sum('amount_paid');
        });
        
        if ($totalPaid >= $totalAmount) {
            return 'Paid';
        } elseif ($totalPaid > 0) {
            return 'Partial';
        } else {
            return 'Pending';
        }
    }

    private function formatInvoicePeriod($invoice)
    {
        if ($invoice->billing_period_start && $invoice->billing_period_end) {
            $start = \Carbon\Carbon::parse($invoice->billing_period_start);
            $end = \Carbon\Carbon::parse($invoice->billing_period_end);
            
            if ($start->format('Y-m') === $end->format('Y-m')) {
                return $start->format('M Y');
            }
            
            return $start->format('M j') . ' - ' . $end->format('M j, Y');
        }
        
        if ($invoice->due_date) {
            return \Carbon\Carbon::parse($invoice->due_date)->format('M Y');
        }
        
        // If no billing period, try to infer from created date
        if ($invoice->created_at) {
            return \Carbon\Carbon::parse($invoice->created_at)->format('M Y');
        }
        
        return 'N/A';
    }

    private function calculateInvoiceStatus($amount, $amountPaid, $dueDate)
    {
        if ($amountPaid >= $amount) {
            return 'Paid';
        } elseif ($amountPaid > 0) {
            return 'Partial';
        } elseif ($dueDate && \Carbon\Carbon::parse($dueDate)->isPast()) {
            return 'Overdue';
        } else {
            return 'Pending';
        }
    }

    public function generateMultiSheetExcelExport($exportData, $filename)
    {
        if (empty($exportData)) {
            return response()->json([
                'status' => 404,
                'message' => 'No data found to export'
            ], 404);
        }

        try {
            $spreadsheet = new Spreadsheet();
            
            // Remove default sheet
            $spreadsheet->removeSheetByIndex(0);
            
            $sheetIndex = 0;
            
            foreach ($exportData as $sheetName => $sheetData) {
                if (empty($sheetData['data'])) continue;
                
                // Create new sheet
                $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, ucfirst($sheetName));
                $spreadsheet->addSheet($sheet, $sheetIndex);
                
                // Set as active sheet
                $spreadsheet->setActiveSheetIndex($sheetIndex);
                $activeSheet = $spreadsheet->getActiveSheet();
                
                // Prepare data array for fromArray method
                $dataArray = [];
                foreach ($sheetData['data'] as $row) {
                    $dataArray[] = array_values($row);
                }
                
                // Add data to sheet
                $this->populateSheet($activeSheet, $dataArray, $sheetData['headers'], ucfirst($sheetName));
                
                $sheetIndex++;
            }
            
            // Set first sheet as active
            if ($sheetIndex > 0) {
                $spreadsheet->setActiveSheetIndex(0);
            }
            
            // Create temporary file
            $tempPath = sys_get_temp_dir() . '/' . uniqid('excel_export_') . '.xlsx';
            
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            // Clean up
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Multi-sheet Excel export generation failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 500,
                'message' => 'Failed to generate Excel file: ' . $e->getMessage()
            ], 500);
        }
    }

    private function populateSheet($sheet, $data, $headers, $sheetTitle)
    {
        // Set headers
        $sheet->fromArray([$headers], null, 'A1');
        
        // Style headers
        $columnCount = count($headers);
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount) . '1';
        
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
        ]);
        
        // Add data
        if (!empty($data)) {
            $sheet->fromArray($data, null, 'A2');
            
            // Auto-size columns
            for ($i = 1; $i <= $columnCount; $i++) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            // Add alternating row colors
            $rowCount = count($data);
            for ($row = 2; $row <= $rowCount + 1; $row++) {
                if ($row % 2 == 0) {
                    $rowRange = 'A' . $row . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount) . $row;
                    $sheet->getStyle($rowRange)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']]
                    ]);
                }
            }
        }
    }

    private function applyBillingFilters($query, $request)
    {
        if ($request->date_range && $request->date_range !== '') {
            // Use created_at instead of billing_date for TenantBilling
            $query = $this->applyDateRangeFilter($query, $request->date_range, $request->from_date, $request->to_date, 'created_at');
        }

        // Apply tenant filters
        if ($request->filled('specific_tenant') && is_array($request->specific_tenant)) {
            $tenantRefs = $request->specific_tenant;
            $query->whereHas('tenant.tenantProfile', function($q) use ($tenantRefs) {
                $q->whereIn('tenant_ref', $tenantRefs);
            });
        }

        return $query;
    }

    private function buildTenantProfilesData($userId, $request, $profileOptions)
    {
        $query = TenantProfile::forBusiness($userId);
        
        // Apply filters
        $query = $this->applyTenantProfileFilters($query, $request);
        
        $profiles = $query->get();
        
        Log::info('Tenant profiles export query results', [
            'total_profiles' => $profiles->count(),
            'individual_profiles' => $profiles->where('tenant_type', 'Individual')->count(),
            'business_profiles' => $profiles->where('tenant_type', 'Business')->count(),
        ]);
        
        // Initialize headers with basic fields
        $allHeaders = ['Tenant Profile Reference', 'Tenant Type', 'Tenant Name', 'Profile Status', 'Created Date'];
        $data = [];
        
        // First pass: collect all possible headers
        foreach ($profiles as $profile) {
            $tempRow = [];
            
            // Collect headers from optional sections
            if (in_array('personal_details', $profileOptions)) {
                $personalHeaders = $this->addPersonalDetailsToProfileExport($tempRow, $profile);
                $allHeaders = array_merge($allHeaders, $personalHeaders);
            }
            
            if (in_array('contact_details', $profileOptions)) {
                $contactHeaders = $this->addContactDetailsToProfileExport($tempRow, $profile);
                $allHeaders = array_merge($allHeaders, $contactHeaders);
            }
            
            if (in_array('emergency_contacts', $profileOptions)) {
                $emergencyHeaders = $this->addEmergencyContactsToProfileExport($tempRow, $profile);
                $allHeaders = array_merge($allHeaders, $emergencyHeaders);
            }
        }
        
        // Remove duplicates and preserve order
        $allHeaders = array_unique($allHeaders);
        
        // Second pass: build actual data with all headers
        foreach ($profiles as $profile) {
            // Initialize row with all headers as empty
            $row = array_fill_keys($allHeaders, '');
            
            // Basic profile information (always included)
            $row['Tenant Profile Reference'] = $profile->tenant_ref;
            $row['Tenant Type'] = $profile->tenant_type;
            $row['Profile Status'] = $profile->profile_status;
            $row['Created Date'] = $profile->created_at->format('Y-m-d');
            
            // Add display name based on tenant type
            if ($profile->tenant_type === 'Individual') {
                $row['Tenant Name'] = $profile->tenant_name ?: '';
            } else {
                $row['Tenant Name'] = $profile->business_name ?: '';
            }
            
            // Optional sections based on user selection
            if (in_array('personal_details', $profileOptions)) {
                $this->addPersonalDetailsToProfileExport($row, $profile);
            }
            
            if (in_array('contact_details', $profileOptions)) {
                $this->addContactDetailsToProfileExport($row, $profile);
            }
            
            if (in_array('emergency_contacts', $profileOptions)) {
                $this->addEmergencyContactsToProfileExport($row, $profile);
            }
            
            $data[] = $row;
        }
        
        return ['data' => $data, 'headers' => $allHeaders];
    }

    private function buildTenancyDetailsData($userId, $request, $tenancyOptions)
    {
        $query = TenantManagement::where('tenant_management.managed_by', $userId)
            ->with(['tenantProfile', 'complex', 'commercialUnit']);
        
        // Apply filters
        $query = $this->applyExportFilters($query, $request);
        
        $tenancies = $query->get();
        
        Log::info('Tenancy details export query results', [
            'total_tenancies' => $tenancies->count(),
        ]);
        
        $headers = ['Tenancy Reference'];
        $data = [];
        
        foreach ($tenancies as $tenancy) {
            $row = ['tenancy_ref' => $tenancy->tenancy_ref];
            
            // Basic tenancy information (always included)
            $row['Tenant Profile Reference'] = $tenancy->tenantProfile ? $tenancy->tenantProfile->tenant_ref : '';
            $row['Status'] = $tenancy->status;
            $row['Created Date'] = $tenancy->created_at->format('Y-m-d');
            $headers = array_merge($headers, ['Tenant Profile Reference', 'Status', 'Created Date']);
            
            // Optional sections based on user selection
            if (in_array('property_details', $tenancyOptions)) {
                $propertyHeaders = $this->addPropertyDetailsToTenancyExport($row, $tenancy);
                $headers = array_merge($headers, $propertyHeaders);
            }
            
            if (in_array('agreement_details', $tenancyOptions)) {
                $agreementHeaders = $this->addAgreementDetailsToTenancyExport($row, $tenancy);
                $headers = array_merge($headers, $agreementHeaders);
            }
            
            if (in_array('financial_details', $tenancyOptions)) {
                $financialHeaders = $this->addFinancialDetailsToTenancyExport($row, $tenancy);
                $headers = array_merge($headers, $financialHeaders);
            }
            
            if (in_array('occupancy_details', $tenancyOptions)) {
                $occupancyHeaders = $this->addOccupancyDetailsToTenancyExport($row, $tenancy);
                $headers = array_merge($headers, $occupancyHeaders);
            }
            
            $data[$tenancy->tenancy_ref] = $row;
        }
        
        return ['data' => array_values($data), 'headers' => array_unique($headers)];
    }

    private function addPersonalDetailsToProfileExport(&$row, $profile)
    {
        $headers = [];
        
        if ($profile->tenant_type === 'Individual') {
            $row['Full Name'] = $profile->tenant_name ?: '';
            $row['ID Number'] = $profile->tenant_id_number ?: '';
            $row['Date of Birth'] = $profile->date_of_birth ? $profile->date_of_birth->format('Y-m-d') : '';
            $row['Gender'] = $profile->gender ?: '';
            $row['Nationality'] = $profile->nationality ?: '';
            $row['Occupation'] = $profile->occupation ?: '';
            $row['Employer'] = $profile->employer ?: '';
            $row['Monthly Income (KES)'] = $profile->monthly_income ? number_format($profile->monthly_income, 2) : '';
            
            $headers = [
                'Full Name', 
                'ID Number', 
                'Date of Birth', 
                'Gender', 
                'Nationality', 
                'Occupation', 
                'Employer', 
                'Monthly Income (KES)'
            ];
        } else {
            // Business details - ensure these are properly populated
            $row['Business Name'] = $profile->business_name ?: '';
            $row['Registration Number'] = $profile->business_registration ?: '';
            $row['Contact Person'] = $profile->business_contact_person ?: '';
            $row['Business Type'] = $profile->business_type ?: '';
            $row['Tax ID Number'] = $profile->tax_id_number ?: '';
            $row['Annual Revenue (KES)'] = $profile->annual_revenue ? number_format($profile->annual_revenue, 2) : '';
            $row['Years in Business'] = $profile->years_in_business ?: '';
            $row['Industry'] = $profile->industry ?: '';
            $row['Business Description'] = $profile->business_description ?: '';
            
            $headers = [
                'Business Name', 
                'Registration Number', 
                'Contact Person', 
                'Business Type', 
                'Tax ID Number', 
                'Annual Revenue (KES)', 
                'Years in Business',
                'Industry',
                'Business Description'
            ];
            
            // Debug log for business profiles
            Log::info('Business profile data', [
                'tenant_ref' => $profile->tenant_ref,
                'business_name' => $profile->business_name,
                'business_registration' => $profile->business_registration,
                'contact_person' => $profile->business_contact_person,
                'business_type' => $profile->business_type
            ]);
        }
        
        return $headers;
    }

    private function addContactDetailsToProfileExport(&$row, $profile)
    {
        if ($profile->tenant_type === 'Individual') {
            $row['Email'] = $profile->tenant_email ?: '';
            $row['Phone'] = $profile->tenant_phone ?: '';
            $row['Address'] = $profile->address ?: '';
            $row['City'] = $profile->city ?: '';
            $row['Postal Code'] = $profile->postal_code ?: '';
            
            return ['Email', 'Phone', 'Address', 'City', 'Postal Code'];
        } else {
            // Business contact details
            $row['Business Email'] = $profile->business_email ?: '';
            $row['Business Phone'] = $profile->business_phone ?: '';
            $row['Business Address'] = $profile->business_address ?: '';
            $row['Business City'] = $profile->business_city ?: '';
            $row['Business Postal Code'] = $profile->business_postal_code ?: '';
            $row['Website'] = $profile->website ?: '';
            $row['Alternative Phone'] = $profile->alternative_phone ?: '';
            $row['Fax Number'] = $profile->fax_number ?: '';
            
            return [
                'Business Email', 
                'Business Phone', 
                'Business Address', 
                'Business City', 
                'Business Postal Code',
                'Website',
                'Alternative Phone',
                'Fax Number'
            ];
        }
    }

    private function addEmergencyContactsToProfileExport(&$row, $profile)
    {
        $row['Emergency Contact Name'] = $profile->emergency_contact_name ?: '';
        $row['Emergency Contact Phone'] = $profile->emergency_contact_phone ?: '';
        $row['Emergency Contact Relationship'] = $profile->emergency_contact_relationship ?: '';
        $row['Emergency Contact Address'] = $profile->emergency_contact_address ?: '';
        
        return ['Emergency Contact Name', 'Emergency Contact Phone', 'Emergency Contact Relationship', 'Emergency Contact Address'];
    }

    private function addPropertyDetailsToTenancyExport(&$row, $tenancy)
    {
        $row['Complex Name'] = $tenancy->complex ? $tenancy->complex->title : '';
        $row['Property Type'] = $tenancy->listing_type;
        $row['Unit Number'] = $tenancy->unit_number ?: $tenancy->specific_unit_number;
        $row['Furnishing'] = $tenancy->furnishing;
        $row['Bedrooms'] = $tenancy->bedrooms;
        $row['Bathrooms'] = $tenancy->bathrooms;
        
        // Add both available area and area taken by tenant
        $row['Total Square Area (sq ft)'] = $tenancy->sq_area ?: $tenancy->occupied_sq_area;
        $row['Square Area Taken (sq ft)'] = $tenancy->occupied_sq_area ?: $tenancy->sq_area; // Area actually taken by tenant
        
        $row['Rate per Sq Ft'] = $tenancy->rate_per_sq_ft ? number_format($tenancy->rate_per_sq_ft, 2) : '';
        
        return [
            'Complex Name', 
            'Property Type', 
            'Unit Number', 
            'Furnishing', 
            'Bedrooms', 
            'Bathrooms', 
            'Total Square Area (sq ft)', 
            'Square Area Taken (sq ft)',  // Added this
            'Rate per Sq Ft'
        ];
    }
    
    private function addAgreementDetailsToTenancyExport(&$row, $tenancy)
    {
        $row['Agreement Type'] = $tenancy->agreement_type;
        $row['Lease Start Date'] = $tenancy->lease_start_date ? $tenancy->lease_start_date->format('Y-m-d') : '';
        $row['Lease End Date'] = $tenancy->lease_end_date ? $tenancy->lease_end_date->format('Y-m-d') : '';
        $row['Lease Duration (Months)'] = $tenancy->lease_duration_months;
        $row['Payment Frequency'] = $tenancy->payment_frequency;
        $row['Service Charge Interval'] = $tenancy->service_charge_payment_interval;
        $row['Grace Period (Days)'] = $tenancy->grace_period_days;
        
        return ['Agreement Type', 'Lease Start Date', 'Lease End Date', 'Lease Duration (Months)', 'Payment Frequency', 'Service Charge Interval', 'Grace Period (Days)'];
    }

    private function addFinancialDetailsToTenancyExport(&$row, $tenancy)
    {
        $row['Currency'] = $tenancy->currency;
        $row['Monthly Payment (KES)'] = number_format($tenancy->monthly_payment, 2);
        $row['Security Deposit (KES)'] = number_format($tenancy->security_deposit ?: 0, 2);
        $row['Late Fee Amount (KES)'] = number_format($tenancy->late_fee_amount ?: 0, 2);
        $row['Penalty Amount (KES)'] = number_format($tenancy->penalty_amount ?: 0, 2);
        
        // Add calculated amounts based on area taken
        if ($tenancy->occupied_sq_area && $tenancy->rate_per_sq_ft) {
            $row['Calculated Monthly Rate (KES)'] = number_format($tenancy->occupied_sq_area * $tenancy->rate_per_sq_ft, 2);
        } else {
            $row['Calculated Monthly Rate (KES)'] = '';
        }
        
        return [
            'Currency', 
            'Monthly Payment (KES)', 
            'Security Deposit (KES)', 
            'Late Fee Amount (KES)', 
            'Penalty Amount (KES)',
            'Calculated Monthly Rate (KES)' // Added this
        ];
    }

    private function addOccupancyDetailsToTenancyExport(&$row, $tenancy)
    {
        $row['Number of Occupants'] = $tenancy->number_of_occupants;
        $row['Has Pets'] = $tenancy->has_pets ? 'Yes' : 'No';
        $row['Pet Details'] = $tenancy->pet_details;
        
        return ['Number of Occupants', 'Has Pets', 'Pet Details'];
    }

    private function applyTenantProfileFilters($query, $request)
    {
        // Date range filtering on profile creation
        if ($request->date_range && $request->date_range !== '') {
            $query = $this->applyDateRangeFilter($query, $request->date_range, $request->from_date, $request->to_date, 'created_at');
        }
        
        // Tenant type filtering
        if ($request->filled('tenant_type') && $request->tenant_type !== '') {
            $query->where('tenant_type', $request->tenant_type);
        }
        
        // Profile status filtering
        if ($request->filled('profile_status') && $request->profile_status !== '') {
            $query->where('profile_status', $request->profile_status);
        }
        
        // Specific tenant selection
        if ($request->filled('specific_tenant') && is_array($request->specific_tenant)) {
            $tenantRefs = $request->specific_tenant;
            $query->whereIn('tenant_ref', $tenantRefs);
        }
        
        return $query;
    }
}