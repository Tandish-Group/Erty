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
    public function downloadTenantBulkUploadTemplate()
    {
        try {
            $headers = [
                // Tenant Type (Required)
                'tenant_type', // Individual or Business
                
                // Individual Tenant Fields (Required when tenant_type = Individual)
                'tenant_name', // Required for Individual
                'tenant_email', // Required for Individual
                'tenant_phone', // Required for Individual
                'tenant_id_number', // Required for Individual
                'date_of_birth', // Optional
                'gender', // Optional: Male, Female, Other
                'address', // Optional
                'nationality', // Optional
                'occupation', // Optional
                'employer', // Optional
                'monthly_income', // Optional
                
                // Business Tenant Fields (Required when tenant_type = Business)
                'business_name', // Required for Business
                'business_registration', // Required for Business
                'business_contact_person', // Required for Business
                'business_email', // Required for Business
                'business_phone', // Required for Business
                'business_address', // Optional
                'business_type', // Optional
                'tax_id_number', // Optional
                'annual_revenue', // Optional
                'years_in_business', // Optional
                
                // Emergency Contact (Optional for both types)
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
                'emergency_contact_address',
                
                // Profile Status and Notes
                'profile_status', // Active, Inactive, Blacklisted
                'profile_notes', // Additional notes about tenant profile
            ];
    
            $sampleData = [
                [
                    // Individual Tenant Example - New Tenant
                    'Individual', // tenant_type
                    'John Doe', // tenant_name
                    'john.doe@email.com', // tenant_email
                    '+254712345678', // tenant_phone
                    '12345678', // tenant_id_number
                    '1990-05-15', // date_of_birth
                    'Male', // gender
                    'P.O. Box 12345, Nairobi', // address
                    'Kenyan', // nationality
                    'Software Engineer', // occupation
                    'Tech Company Ltd', // employer
                    '150000', // monthly_income
                    '', // business_name
                    '', // business_registration
                    '', // business_contact_person
                    '', // business_email
                    '', // business_phone
                    '', // business_address
                    '', // business_type
                    '', // tax_id_number
                    '', // annual_revenue
                    '', // years_in_business
                    'Jane Doe', // emergency_contact_name
                    '+254712345679', // emergency_contact_phone
                    'Sister', // emergency_contact_relationship
                    'Karen, Nairobi', // emergency_contact_address
                    'Active', // profile_status
                    'Reliable tenant with good credit history', // profile_notes
                ],
                [
                    // Business Tenant Example - New Tenant
                    'Business', // tenant_type
                    '', // tenant_name
                    '', // tenant_email
                    '', // tenant_phone
                    '', // tenant_id_number
                    '', // date_of_birth
                    '', // gender
                    '', // address
                    '', // nationality
                    '', // occupation
                    '', // employer
                    '', // monthly_income
                    'ABC Marketing Ltd', // business_name
                    'PVT-12345678', // business_registration
                    'Mary Johnson', // business_contact_person
                    'info@abcmarketing.com', // business_email
                    '+254701234567', // business_phone
                    'Westlands Business Park, Nairobi', // business_address
                    'Marketing Agency', // business_type
                    'TAX-987654321', // tax_id_number
                    '2400000', // annual_revenue
                    '5', // years_in_business
                    'Peter Johnson', // emergency_contact_name
                    '+254701234568', // emergency_contact_phone
                    'Business Partner', // emergency_contact_relationship
                    'Karen, Nairobi', // emergency_contact_address
                    'Active', // profile_status
                    'Established business with good track record', // profile_notes
                ]
            ];
    
            return $this->generateCsvTemplate('tenant_profile_bulk_upload_template.csv', $headers, $sampleData);
    
        } catch (\Exception $e) {
            Log::error('Failed to generate tenant profile bulk upload template: ' . $e->getMessage());
            
            return response()->json([
                'status' => 500,
                'message' => 'Failed to generate template'
            ], 500);
        }
    }
    public function downloadPaymentBulkUploadTemplate()
    {
        try {
            $headers = [
                // Tenant Identification (Required)
                'tenancy_ref', // Tenancy reference number (preferred)
                'tenant_profile_ref', // Alternative: tenant profile reference
                'tenant_email', // Alternative: tenant email for identification
                
                // Invoice Information (Required)
                'invoice_id', // Required: Invoice number or combined invoice number
                
                // Payment Information (Required)
                'payment_date', // Required: YYYY-MM-DD format
                'amount_paid', // Required: Total amount being paid
                'payment_method', // Required: cash, bank_transfer, mobile_money, check, card, other
                'payment_status', // Required: paid, partial, pending, failed
                
                // Payment Details (Optional)
                'transaction_reference', // M-Pesa code, check number, etc.
                'late_fee', // Late fee amount if applicable
                'late_fee_paid_now', // true/false - whether late fee is paid with this payment
                'payment_notes', // Additional notes about the payment
                
                // Partial Payment Allocation (Required for partial payments)
                'single_invoice_amount', // Amount for single invoice partial payment
                'lease_amount', // Amount allocated to tenancy/lease for combined invoices
                'service_charge_amount', // Amount allocated to service charges for combined invoices
                
            ];
    
            $sampleData = [
                [
                    // Full payment for single tenancy invoice
                    'TM6GNKG83E', // tenancy_ref
                    'TPAXX69DOR', // tenant_profile_ref
                    'john.doe@email.com', // tenant_email
                    'LEASE-20240105-3F8A2C', // invoice_id
                    '2024-01-05', // payment_date
                    '45000', // amount_paid
                    'mobile_money', // payment_method
                    'paid', // payment_status
                    'QD12345678', // transaction_reference
                    '0', // late_fee
                    'false', // late_fee_paid_now
                    'Payment received on time via M-Pesa', // payment_notes
                    '', // single_invoice_amount (empty for full payment)
                    '', // lease_amount (empty for single invoice)
                    '', // service_charge_amount (empty for single invoice)
                ],
                [
                    // Full payment for service charge invoice
                    'TMYMZJGQG1', // tenancy_ref
                    'TP7WURRWBX', // tenant_profile_ref
                    'abc@business.com', // tenant_email
                    'LEASE-20240108-7B1D4E', // invoice_id
                    '2024-01-08', // payment_date
                    '12500', // amount_paid
                    'bank_transfer', // payment_method
                    'paid', // payment_status
                    'BT20240108001', // transaction_reference
                    '0', // late_fee
                    'false', // late_fee_paid_now
                    'Service charges paid - parking, security, water', // payment_notes
                    '', // single_invoice_amount (empty for full payment)
                    '', // lease_amount (empty for service charge invoice)
                    '', // service_charge_amount (empty for single invoice)
                ],
                [
                    // Full payment for combined invoice with late fee
                    'TMABCD1234', // tenancy_ref
                    'TP12345678', // tenant_profile_ref
                    'tenant@example.com', // tenant_email
                    'LEASE-20240110-9C2F6A', // invoice_id
                    '2024-01-10', // payment_date
                    '95000', // amount_paid (90000 + 5000 late fee)
                    'bank_transfer', // payment_method
                    'paid', // payment_status
                    'BT20240110001', // transaction_reference
                    '5000', // late_fee
                    'true', // late_fee_paid_now
                    'Late payment with penalty - paid in full', // payment_notes
                    '', // single_invoice_amount (empty for full payment)
                    '', // lease_amount (empty for full payment)
                    '', // service_charge_amount (empty for full payment)
                ],
                [
                    // Partial payment for service charge invoice
                    'TM9876543Z', // tenancy_ref
                    'TP87654321', // tenant_profile_ref
                    'partial@tenant.com', // tenant_email
                    'LEASE-20240115-1E7B3D', // invoice_id
                    '2024-01-15', // payment_date
                    '7500', // amount_paid
                    'cash', // payment_method
                    'partial', // payment_status
                    '', // transaction_reference
                    '0', // late_fee
                    'false', // late_fee_paid_now
                    'Partial service charge payment - parking and security only', // payment_notes
                    '7500', // single_invoice_amount (amount for this service charge invoice)
                    '', // lease_amount (empty for service charge invoice)
                    '', // service_charge_amount (empty for single invoice)
                ],
                [
                    // Partial payment for combined invoice with allocation
                    'TM5555666E', // tenancy_ref
                    'TP98765432', // tenant_profile_ref
                    'business@company.com', // tenant_email
                    'LEASE-20240120-5A4C8F', // invoice_id
                    '2024-01-20', // payment_date
                    '70000', // amount_paid
                    'mobile_money', // payment_method
                    'partial', // payment_status
                    'QE98765432', // transaction_reference
                    '0', // late_fee
                    'false', // late_fee_paid_now
                    'Partial payment - rent portion only', // payment_notes
                    '', // single_invoice_amount (empty for combined invoice)
                    '70000', // lease_amount (amount allocated to rent)
                    '0', // service_charge_amount (no service charge payment)
                ],
                [
                    // Pending payment for service charge (awaiting bank clearance)
                    'TM7777888F', // tenancy_ref
                    'TP11223344', // tenant_profile_ref
                    'pending@service.com', // tenant_email
                    'LEASE-20240125-2D9E1B', // invoice_id
                    '2024-01-25', // payment_date
                    '15000', // amount_paid
                    'check', // payment_method
                    'pending', // payment_status
                    'CHK-789456', // transaction_reference
                    '0', // late_fee
                    'false', // late_fee_paid_now
                    'Check payment for service charges - awaiting clearance', // payment_notes
                    '', // single_invoice_amount (empty for full payment)
                    '', // lease_amount (empty for service charge invoice)
                    '', // service_charge_amount (empty for single invoice)
                ]
            ];
    
            return $this->generateCsvTemplate('payment_bulk_upload_template.csv', $headers, $sampleData);
    
        } catch (\Exception $e) {
            Log::error('Failed to generate payment bulk upload template: ' . $e->getMessage());
            
            return response()->json([
                'status' => 500,
                'message' => 'Failed to generate template'
            ], 500);
        }
    }
    public function downloadNoticeBulkUploadTemplate()
    {
        try {
            $headers = [
                // Tenant Identification (Required)
                'tenancy_ref', // Tenancy reference number (preferred)
                'tenant_profile_ref', // Alternative: tenant profile reference
                'tenant_email', // Alternative: tenant email for identification
                
                // Notice Information (Required)
                'notice_type', // Required: lease_termination, rent_increase, lease_violation, property_inspection, maintenance_notice, eviction_notice, lease_renewal, other
                'notice_date', // Required: YYYY-MM-DD format
                'effective_date', // Required: YYYY-MM-DD format
                'notice_subject', // Required: Subject of the notice
                'notice_details', // Required: Detailed notice content
                
                // Notice Configuration (Optional)
                'notice_period', // Notice period in days
                'delivery_methods', // Comma-separated: email,sms,physical
                'priority_level', // low, medium, high, urgent
                'response_required', // true/false - whether tenant response is required
                'response_deadline', // YYYY-MM-DD format if response required
                
                // Follow-up Configuration (Optional)
                'follow_up_required', // true/false
                'follow_up_date', // YYYY-MM-DD format
                'escalation_level', // 1, 2, 3 (for violation notices)
                
                // Additional Information (Optional)
                'legal_reference', // Legal statute or regulation reference
                'amount_involved', // For rent increase or fine notices
                'contact_person', // Who tenant should contact for queries
                'contact_phone', // Contact phone number
                'internal_notes', // Internal notes not sent to tenant
            ];
    
            $sampleData = [
                [
                    'TM12345678', // tenancy_ref
                    'TP87654321', // tenant_profile_ref
                    'john.doe@email.com', // tenant_email
                    'lease_termination', // notice_type
                    '2024-03-01', // notice_date
                    '2024-04-30', // effective_date
                    'Notice of Lease Termination', // notice_subject
                    'This notice serves to inform you that your lease agreement will be terminated effective April 30, 2024. Please ensure all personal belongings are removed by the specified date.', // notice_details
                    '30', // notice_period
                    'email,physical', // delivery_methods
                    'high', // priority_level
                    'true', // response_required
                    '2024-03-15', // response_deadline
                    'false', // follow_up_required
                    '', // follow_up_date
                    '', // escalation_level
                    'Landlord and Tenant Act Section 10', // legal_reference
                    '', // amount_involved
                    'Property Manager', // contact_person
                    '+254712345000', // contact_phone
                    'Lease expiring naturally - no violations', // internal_notes
                ],
                [
                    'TM87654321', // tenancy_ref
                    'TP12345678', // tenant_profile_ref
                    'business@company.com', // tenant_email
                    'rent_increase', // notice_type
                    '2024-02-01', // notice_date
                    '2024-04-01', // effective_date
                    'Notice of Rent Increase', // notice_subject
                    'We hereby notify you of an increase in monthly rent from KES 90,000 to KES 105,000 effective April 1, 2024. This increase is due to improved amenities and market adjustments.', // notice_details
                    '60', // notice_period
                    'email,sms', // delivery_methods
                    'medium', // priority_level
                    'false', // response_required
                    '', // response_deadline
                    'true', // follow_up_required
                    '2024-03-15', // follow_up_date
                    '', // escalation_level
                    'Rent Restriction Act Section 5', // legal_reference
                    '15000', // amount_involved
                    'Finance Department', // contact_person
                    '+254712345001', // contact_phone
                    'Market rate adjustment - 16.7% increase', // internal_notes
                ],
                [
                    'TM11111111', // tenancy_ref
                    'TP22222222', // tenant_profile_ref
                    'tenant@example.com', // tenant_email
                    'lease_violation', // notice_type
                    '2024-01-20', // notice_date
                    '2024-02-03', // effective_date
                    'Lease Violation Notice - Noise Complaints', // notice_subject
                    'Multiple noise complaints have been received from neighbors. Please ensure noise levels are kept to acceptable limits, especially during quiet hours (10 PM - 7 AM).', // notice_details
                    '14', // notice_period
                    'email,physical', // delivery_methods
                    'high', // priority_level
                    'true', // response_required
                    '2024-01-27', // response_deadline
                    'true', // follow_up_required
                    '2024-02-05', // follow_up_date
                    '1', // escalation_level
                    'Lease Agreement Clause 8.3', // legal_reference
                    '', // amount_involved
                    'Building Supervisor', // contact_person
                    '+254712345002', // contact_phone
                    'First violation - monitoring required', // internal_notes
                ],
                [
                    'TM99999999', // tenancy_ref
                    'TP88888888', // tenant_profile_ref
                    'maintenance@tenant.com', // tenant_email
                    'maintenance_notice', // notice_type
                    '2024-02-15', // notice_date
                    '2024-02-20', // effective_date
                    'Scheduled Maintenance Notice', // notice_subject
                    'Scheduled maintenance work on plumbing systems will be conducted on February 20-22, 2024. Water supply may be interrupted between 9 AM - 5 PM. Please plan accordingly.', // notice_details
                    '5', // notice_period
                    'email,sms,physical', // delivery_methods
                    'medium', // priority_level
                    'false', // response_required
                    '', // response_deadline
                    'false', // follow_up_required
                    '', // follow_up_date
                    '', // escalation_level
                    'Building Maintenance Schedule', // legal_reference
                    '', // amount_involved
                    'Maintenance Team', // contact_person
                    '+254712345003', // contact_phone
                    'Routine quarterly maintenance', // internal_notes
                ]
            ];
    
            return $this->generateCsvTemplate('notice_bulk_upload_template.csv', $headers, $sampleData);
    
        } catch (\Exception $e) {
            Log::error('Failed to generate notice bulk upload template: ' . $e->getMessage());
            
            return response()->json([
                'status' => 500,
                'message' => 'Failed to generate template'
            ], 500);
        }
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
    /**
     * Show mapping page with sample data from temp tables
     */
    public function showMappingPage()
    {
        $this->assertWholeBusinessImport();
        $user_id = business_owner_id();
    
        // Get tenant rows with joined temp_tenant_management data
        $tenantRows = DB::table('temp_tenant_profiles as ttp')
            ->where('ttp.user_id', $user_id)
            ->get()
            ->map(function($row) {
                $row = (array)$row;
    
                // If tenant_ref is present, fetch existing tenancy and profile details
                if (!empty($row['tenant_ref'])) {
    
                    // Existing profile (from tenant_profiles)
                    $existingProfile = \App\Models\TenantProfile::query()
                        ->where('tenant_ref', $row['tenant_ref'])
                        ->where('managed_by', business_owner_id())
                        ->first();
                    if ($existingProfile) {
                        $row['existing_profile'] = (array)$existingProfile;
                    }
                }
                return $row;
            });
    
        $tenantHeaders = $tenantRows->isNotEmpty() ? array_keys($tenantRows->first()) : [];
    
        // Payment rows with invoice validation
        $paymentRows = DB::table('temp_tenant_payments')
            ->where('user_id', $user_id)
            ->get()
            ->map(function($row) use ($user_id) {
                $row = (array)$row;
                
                // Validate invoice_id if present
                if (!empty($row['invoice_id'])) {
                    $invoiceId = $row['invoice_id'];
                    $invoiceExists = false;
                    $invoiceDetails = null;
                    
                    // Check if invoice_id starts with 'COMB' or 'CMB' (combined invoice)
                    if (str_starts_with($invoiceId, 'COMB') || str_starts_with($invoiceId, 'CMB')) {
                        // Check combined_invoice_number column
                        $invoice = DB::table('tenant_invoices')
                            ->join('tenant_management', 'tenant_invoices.tenancy_id', '=', 'tenant_management.id')
                            ->where('tenant_invoices.combined_invoice_number', $invoiceId)
                            ->where('tenant_management.managed_by', $user_id)
                            ->tap(fn ($q) => app(\App\Services\Access\PropertyAccess::class)->constrain($q, 'tenant_management.complex_id', ['residential' => 'tenant_management.residential_unit_id', 'commercial' => 'tenant_management.commercial_unit_id']))
                            ->select([
                                'tenant_invoices.*',
                                'tenant_management.tenancy_ref',
                                'tenant_management.tenant_profile_id'
                            ])
                            ->first();
                            
                        if ($invoice) {
                            $invoiceExists = true;
                            $invoiceDetails = [
                                'id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'combined_invoice_number' => $invoice->combined_invoice_number,
                                'invoice_type' => $invoice->invoice_type ?? 'Combined',
                                'amount' => $invoice->amount,
                                'tenancy_ref' => $invoice->tenancy_ref,
                                'tenant_profile_id' => $invoice->tenant_profile_id,
                                'due_date' => $invoice->due_date,
                                'is_combined' => true
                            ];
                        }
                    } else {
                        // Check regular invoice_number column
                        $invoice = DB::table('tenant_invoices')
                            ->join('tenant_management', 'tenant_invoices.tenancy_id', '=', 'tenant_management.id')
                            ->where('tenant_invoices.invoice_number', $invoiceId)
                            ->where('tenant_management.managed_by', $user_id)
                            ->tap(fn ($q) => app(\App\Services\Access\PropertyAccess::class)->constrain($q, 'tenant_management.complex_id', ['residential' => 'tenant_management.residential_unit_id', 'commercial' => 'tenant_management.commercial_unit_id']))
                            ->select([
                                'tenant_invoices.*',
                                'tenant_management.tenancy_ref',
                                'tenant_management.tenant_profile_id'
                            ])
                            ->first();
                            
                        if ($invoice) {
                            $invoiceExists = true;
                            $invoiceDetails = [
                                'id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'combined_invoice_number' => $invoice->combined_invoice_number,
                                'invoice_type' => $invoice->invoice_type ?? 'Single',
                                'amount' => $invoice->amount,
                                'tenancy_ref' => $invoice->tenancy_ref,
                                'tenant_profile_id' => $invoice->tenant_profile_id,
                                'due_date' => $invoice->due_date,
                                'is_combined' => false
                            ];
                        }
                    }
                    
                    // Add validation results to the row
                    $row['invoice_exists'] = $invoiceExists;
                    $row['invoice_details'] = $invoiceDetails;
                    $row['invoice_validation_status'] = $invoiceExists ? 'valid' : 'invalid';
                    
                    // Add error message for invalid invoices
                    if (!$invoiceExists) {
                        $row['validation_error'] = "Invoice '{$invoiceId}' not found in the system";
                        
                        // Log the missing invoice for debugging
                        Log::warning("Invoice not found during temp payment validation", [
                            'invoice_id' => $invoiceId,
                            'user_id' => $user_id,
                            'temp_payment_id' => $row['id'] ?? null
                        ]);
                    }
                    
                    // Check if tenancy_ref matches (additional validation)
                    if ($invoiceExists && !empty($row['tenancy_ref'])) {
                        if ($invoiceDetails['tenancy_ref'] !== $row['tenancy_ref']) {
                            $row['invoice_validation_status'] = 'mismatch';
                            $row['validation_error'] = "Invoice '{$invoiceId}' belongs to tenancy '{$invoiceDetails['tenancy_ref']}' but payment references '{$row['tenancy_ref']}'";
                        }
                    }
                } else {
                    // No invoice_id provided
                    $row['invoice_exists'] = null;
                    $row['invoice_details'] = null;
                    $row['invoice_validation_status'] = 'missing';
                    $row['validation_error'] = 'No invoice ID provided';
                }
                
                return $row;
            });
    
        $paymentHeaders = $paymentRows->isNotEmpty() ? array_keys($paymentRows->first()) : [];
    
        // Notice rows (no change)
        $noticeRows = DB::table('temp_tenant_notices')->where('user_id', $user_id)->get()->map(fn($r) => (array)$r);
        $noticeHeaders = $noticeRows->isNotEmpty() ? array_keys($noticeRows->first()) : [];
    
        // Add validation summary for payments
        $paymentValidationSummary = [
            'total' => $paymentRows->count(),
            'valid_invoices' => $paymentRows->where('invoice_validation_status', 'valid')->count(),
            'invalid_invoices' => $paymentRows->where('invoice_validation_status', 'invalid')->count(),
            'missing_invoices' => $paymentRows->where('invoice_validation_status', 'missing')->count(),
            'mismatched_invoices' => $paymentRows->where('invoice_validation_status', 'mismatch')->count(),
        ];
    
        // Log validation summary
        Log::info("Payment validation summary for user {$user_id}", $paymentValidationSummary);
    
        return view('pages.business.tenant.mapping', compact(
            'tenantHeaders', 'tenantRows',
            'paymentHeaders', 'paymentRows', 'paymentValidationSummary',
            'noticeHeaders', 'noticeRows'
        ));
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
    
            return response()->json([
                'status' => 200,
                'message' => "{$type} import completed successfully!",
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
    private function processTempTenants($batchId, $selectedIds)
    {
        $success = 0; 
        $failed = 0; 
        $errors = [];
        $rows = DB::table('temp_tenant_profiles')->where('batch_id', $batchId)->whereIn('id', $selectedIds)->get();
    
        foreach ($rows as $row) {
            try {
                $profileData = (array)$row;
                $isExisting = !empty($profileData['use_existing_tenant']) && filter_var($profileData['use_existing_tenant'], FILTER_VALIDATE_BOOLEAN);
    
                if ($isExisting && !empty($profileData['tenant_ref'])) {
                    // Use existing tenant profile - just validate it exists
                    $existingProfile = TenantProfile::where('tenant_ref', $profileData['tenant_ref'])
                        ->where('managed_by', business_owner_id())
                        ->first();
                        
                    if (!$existingProfile) {
                        throw new \Exception('Existing tenant profile not found for tenant_ref: ' . $profileData['tenant_ref']);
                    }
                    
                    Log::info("Existing tenant profile validated", [
                        'tenant_ref' => $profileData['tenant_ref'],
                        'profile_id' => $existingProfile->id
                    ]);
                } else {
                    // Create new tenant profile
                    $cleanProfileData = $profileData;
                    
                    // Remove temp table specific fields
                    unset(
                        $cleanProfileData['id'], 
                        $cleanProfileData['batch_id'], 
                        $cleanProfileData['user_id'], 
                        $cleanProfileData['row_number'], 
                        $cleanProfileData['status'], 
                        $cleanProfileData['created_at'], 
                        $cleanProfileData['updated_at'], 
                        $cleanProfileData['error_message'], 
                        $cleanProfileData['use_existing_tenant'], 
                        $cleanProfileData['tenant_ref']
                    );
    
                    // Set required fields from store function logic
                    $cleanProfileData['managed_by'] = business_owner_id();
                    $cleanProfileData['created_by'] = business_owner_id();
                    $cleanProfileData['updated_by'] = auth()->id();
                    
                    // Generate unique tenant reference for profile
                    $cleanProfileData['tenant_ref'] = $this->tenantStoreService->generateTenantProfileReference();
                    
                    // Set default profile status if not provided
                    if (empty($cleanProfileData['profile_status'])) {
                        $cleanProfileData['profile_status'] = 'Active';
                    }
    
                    // Validate tenant type is set
                    if (empty($cleanProfileData['tenant_type'])) {
                        throw new \Exception('Tenant type is required');
                    }
    
                    // Check for existing tenant profile with same email/ID to prevent duplicates (from store function)
                    if ($cleanProfileData['tenant_type'] === 'Individual') {
                        if (empty($cleanProfileData['tenant_name']) || empty($cleanProfileData['tenant_email'])) {
                            throw new \Exception('Tenant name and email are required for Individual tenant type');
                        }
                        
                        $existingProfile = TenantProfile::where(function($query) use ($cleanProfileData) {
                            $query->where('tenant_email', $cleanProfileData['tenant_email'])
                                  ->orWhere('tenant_id_number', $cleanProfileData['tenant_id_number'] ?? '');
                        })
                        ->where('managed_by', business_owner_id())
                        ->first();
                    } else {
                        if (empty($cleanProfileData['business_name']) || empty($cleanProfileData['business_email'])) {
                            throw new \Exception('Business name and email are required for Business tenant type');
                        }
                        
                        $existingProfile = TenantProfile::where(function($query) use ($cleanProfileData) {
                            $query->where('business_email', $cleanProfileData['business_email'])
                                  ->orWhere('business_registration', $cleanProfileData['business_registration'] ?? '');
                        })
                        ->where('managed_by', business_owner_id())
                        ->first();
                    }
    
                    if ($existingProfile) {
                        throw new \Exception('A tenant profile with this email or ID number already exists');
                    }
    
                    // Create the tenant profile
                    $tenantProfile = TenantProfile::create($cleanProfileData);
                    
                    Log::info("New tenant profile created", [
                        'tenant_ref' => $tenantProfile->tenant_ref,
                        'profile_id' => $tenantProfile->id,
                        'tenant_type' => $tenantProfile->tenant_type
                    ]);
                }
    
                // Remove temp profile row after successful processing
                DB::table('temp_tenant_profiles')->where('id', $row->id)->delete();
    
                $success++;
                
            } catch (\Exception $e) {
                $failed++;
                $error_message = $e->getMessage();
                $errors[] = "Row ID {$row->id}: {$error_message}";
                
                Log::error("Bulk tenant profile processing failed", [
                    'temp_profile_id' => $row->id,
                    'tenant_type' => $profileData['tenant_type'] ?? 'N/A',
                    'tenant_name' => $profileData['tenant_name'] ?? $profileData['business_name'] ?? 'N/A',
                    'error' => $error_message
                ]);
    
                // Update the temp record with error status for debugging
                DB::table('temp_tenant_profiles')
                    ->where('id', $row->id)
                    ->update([
                        'status' => 'failed',
                        'error_message' => $error_message,
                        'updated_at' => now()
                    ]);
            }
        }
        
        return compact('success', 'failed', 'errors');
    }
    private function processTempPayments($batchId, $selectedIds)
    {
        $success = 0; 
        $failed = 0; 
        $errors = [];
        $rows = DB::table('temp_tenant_payments')->where('batch_id', $batchId)->whereIn('id', $selectedIds)->get();
    
        foreach ($rows as $row) {
            try {
                $data = (array)$row;
                
                // Find tenant by tenancy_ref or tenant_profile_ref
                $tenant = null;
                if (!empty($row->tenancy_ref)) {
                    $tenant = TenantManagement::where('tenancy_ref', $row->tenancy_ref)
                        ->where('managed_by', business_owner_id())
                        ->first();
                } elseif (!empty($row->tenant_profile_ref)) {
                    $tenantProfile = TenantProfile::where('tenant_ref', $row->tenant_profile_ref)
                        ->where('managed_by', business_owner_id())
                        ->first();
                    if ($tenantProfile) {
                        $tenant = TenantManagement::where('tenant_profile_id', $tenantProfile->id)
                            ->where('managed_by', business_owner_id())
                            ->first();
                    }
                } elseif (!empty($row->tenant_email)) {
                    $tenantProfile = TenantProfile::where(function($query) use ($row) {
                        $query->where('tenant_email', $row->tenant_email)
                              ->orWhere('business_email', $row->tenant_email);
                    })->where('managed_by', business_owner_id())->first();
                    
                    if ($tenantProfile) {
                        $tenant = TenantManagement::where('tenant_profile_id', $tenantProfile->id)
                            ->where('managed_by', business_owner_id())
                            ->first();
                    }
                }
    
                if (!$tenant) {
                    throw new \Exception("Tenant not found for payment - tenancy_ref: {$row->tenancy_ref}, tenant_profile_ref: {$row->tenant_profile_ref}, tenant_email: {$row->tenant_email}");
                }
    
                // Get invoices and validate they belong to the tenant
                $invoiceReference = $row->invoice_id;
                $invoices = TenantInvoice::where(function($query) use ($invoiceReference) {
                    $query->where('invoice_number', $invoiceReference)
                          ->orWhere('combined_invoice_number', $invoiceReference);
                })->where('tenancy_id', $tenant->id)->get();
                
                if ($invoices->count() == 0) {
                    throw new \Exception("Invoice '{$invoiceReference}' not found or does not belong to tenant {$tenant->tenancy_ref}");
                }
    
                // Imported payments go through the tenant account: the named invoice first, then oldest first, extra as credit.
                $paymentRequest = (object) [
                    'amount_paid' => floatval($row->amount_paid),
                    'payment_status' => ($row->payment_status ?? 'paid') === 'pending' ? 'pending' : 'confirmed',
                ];
                app(\App\Services\Rent\TenantAccountService::class)->record($tenant, [
                    'amount' => floatval($row->amount_paid),
                    'payment_date' => $row->payment_date,
                    'payment_method' => $row->payment_method ?? 'other',
                    'transaction_reference' => $row->transaction_reference,
                    'payment_notes' => $row->payment_notes ?? 'Bulk imported payment',
                    'pending' => $paymentRequest->payment_status === 'pending',
                    'first_invoice_id' => $invoices->first()->id,
                ]);

                // If we get here, the payment was processed successfully
                DB::table('temp_tenant_payments')->where('id', $row->id)->delete();
                $success++;
    
                Log::info("Bulk payment processed successfully", [
                    'temp_payment_id' => $row->id,
                    'tenant_id' => $tenant->id,
                    'invoice_reference' => $invoiceReference,
                    'amount' => $paymentRequest->amount_paid,
                    'status' => $paymentRequest->payment_status
                ]);
    
            } catch (\Exception $e) {
                $failed++;
                $error_message = $e->getMessage();
                $errors[] = "Row ID {$row->id}: {$error_message}";
                
                Log::error("Bulk payment processing failed", [
                    'temp_payment_id' => $row->id,
                    'tenancy_ref' => $row->tenancy_ref ?? 'N/A',
                    'invoice_id' => $row->invoice_id ?? 'N/A',
                    'error' => $error_message
                ]);
    
                // Update the temp record with error status for debugging
                DB::table('temp_tenant_payments')
                    ->where('id', $row->id)
                    ->update([
                        'processing_status' => 'failed',
                        'error_message' => $error_message,
                        'updated_at' => now()
                    ]);
            }
        }
        
        return compact('success', 'failed', 'errors');
    }
    private function processTempNotices($batchId, $selectedIds)
    {
        $success = 0; $failed = 0; $errors = [];
        $rows = DB::table('temp_tenant_notices')->where('batch_id', $batchId)->whereIn('id', $selectedIds)->get();
        
        foreach ($rows as $row) {
            try {
                $data = (array)$row;
                unset($data['id'], $data['batch_id'], $data['user_id'], $data['row_number'], $data['processing_status'], $data['created_at'], $data['updated_at'], $data['error_message']);
                // Find tenant by tenancy_ref or tenant_profile_ref
                $tenant = TenantManagement::where('tenancy_ref', $row->tenancy_ref)->first();
                if ($tenant) {
                    $data['tenant_id'] = $tenant->id;
                    $data['issued_by'] = auth()->id();
                    TenantNotice::create($data);
                    DB::table('temp_tenant_notices')->where('id', $row->id)->delete();
                    $success++;
                } else {
                    throw new \Exception('Tenant not found for notice');
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }
        return compact('success', 'failed', 'errors');
    }
    private function cancelTempTenants($batchId)
    {
        DB::table('temp_tenant_profiles')->where('batch_id', $batchId)->delete();
        DB::table('temp_tenant_management')->where('batch_id', $batchId)->delete();
    
        Log::info("Canceled tenant mapping data for batch: {$batchId}");
    }
    private function cancelTempPayments($batchId)
    {
        DB::table('temp_tenant_payments')->where('batch_id', $batchId)->delete();
    
        Log::info("Canceled payment mapping data for batch: {$batchId}");
    }
    private function cancelTempNotices($batchId)
    {
        DB::table('temp_tenant_notices')->where('batch_id', $batchId)->delete();
    
        Log::info("Canceled notice mapping data for batch: {$batchId}");
    }
}
