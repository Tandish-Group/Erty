<?php

namespace App\Services\Tenancy;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkUploadService
{
    /**
     * Parse CSV file and return data
     */
    public function parseCsvFile($file)
    {
        $csvData = [];
        $handle = fopen($file->getRealPath(), 'r');
        
        // Skip header row
        $header = fgetcsv($handle);
        
        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (array_filter($row)) { // Skip empty rows
                $csvData[] = $row;
            }
        }
        
        fclose($handle);
        return $csvData;
    }

    /**
     * Store bulk data in temporary tables based on upload type
     */
    public function storeBulkDataInTempTable($uploadType, $csvData, $fieldMappings, $batchId, $userId)
    {
        switch ($uploadType) {
            case 'tenants':
                Log::info("Storing tenant data in temp table for batch: {$batchId}", ['user_id' => $userId]);
                return $this->storeTenantDataInTempTable($csvData, $fieldMappings, $batchId, $userId);
            case 'payments':
                Log::info("Storing payment data in temp table for batch: {$batchId}", ['user_id' => $userId]);
                return $this->storePaymentDataInTempTable($csvData, $fieldMappings, $batchId, $userId);
            case 'notices':
                Log::info("Storing notice data in temp table for batch: {$batchId}", ['user_id' => $userId]);
                return $this->storeNoticeDataInTempTable($csvData, $fieldMappings, $batchId, $userId);
            default:
                throw new \Exception('Invalid upload type');
        }
    }

    /**
     * Store tenant data in temporary tables
     */
    private function storeTenantDataInTempTable($csvData, $fieldMappings, $batchId, $userId)
    {
        $results = ['successful' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($csvData as $rowIndex => $row) {
            try {
                // Map CSV row data to expected fields
                $mappedData = $this->mapCsvRowToFields($row, $fieldMappings);
                Log::info("Mapped tenant data for row " . ($rowIndex + 2), [
                    'mapped_data' => $mappedData,
                    'batch_id' => $batchId,
                    'user_id' => $userId
                ]);
                
                // Store in temp_tenant_profiles
                $tempProfile = $this->storeTempTenantProfile($mappedData, $batchId, $rowIndex + 2, $userId);
                Log::info("Stored tenant profile in temp table for batch: {$batchId}", [
                    'temp_tenant_profile_id' => $tempProfile,
                    'row_number' => $rowIndex + 2,
                    'user_id' => $userId
                ]);
                
                // Store in temp_tenant_management
                // $this->storeTempTenantManagement($mappedData, $batchId, $rowIndex + 2, $tempProfile, $userId);
                
                $results['successful']++;
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage()
                ];
                
                // Store error in temp table
                $this->storeErrorInTempTable('temp_tenant_profiles', $batchId, $rowIndex + 2, $e->getMessage(), $userId);
            }
        }
        
        return $results;
    }

    /**
     * Store payment data in temporary table
     */
    private function storePaymentDataInTempTable($csvData, $fieldMappings, $batchId, $userId)
    {
        $results = ['successful' => 0, 'failed' => 0, 'errors' => []];
        // Log the input parameters at the start of the function
        Log::info("storePaymentDataInTempTable called with parameters", [
            'batch_id' => $batchId,
            'user_id' => $userId,
            'csv_rows_count' => count($csvData),
            'field_mappings_count' => count($fieldMappings),
            'field_mappings' => $fieldMappings,
            'csv_sample_first_5_rows' => array_slice($csvData, 0, 5), // Log first 5 rows as sample
            'function' => __METHOD__,
            'timestamp' => now()->toISOString()
        ]);
        
        foreach ($csvData as $rowIndex => $row) {
            try {
                // Map CSV row data to expected fields
                $mappedData = $this->mapCsvRowToFields($row, $fieldMappings);

                // helper wrappers to avoid exceptions from parseDate/parseDecimal
                $safeDate = function($v) {
                    if (empty($v)) return null;
                    try { return $this->parseDate($v); } catch (\Exception $e) { return null; }
                };
             
                $safeDecimal = function($v) {
                    if ($v === null || $v === '' || strtolower($v) === 'null') return 0.0;
                    try {
                        $parsed = $this->parseDecimal($v);
                        return $parsed === null ? 0.0 : $parsed;
                    } catch (\Exception $e) {
                        return 0.0;
                    }
                };

                $safeBool = function($v) {
                    if (empty($v)) return false;
                    return $this->parseBooleanValue($v);
                };

                Log::info("Mapped payment data for row " . ($rowIndex + 2), [
                    'mapped_data' => $mappedData,
                    'batch_id' => $batchId,
                    'user_id' => $userId
                ]);

                // Prepare allocation data JSON from individual fields
                $allocationData = null;
                if (!empty($mappedData['single_invoice_amount']) || !empty($mappedData['lease_amount']) || !empty($mappedData['service_charge_amount'])) {
                    $allocationData = json_encode([
                        'single_invoice_amount' => $safeDecimal($mappedData['single_invoice_amount'] ?? null),
                        'lease_amount' => $safeDecimal($mappedData['lease_amount'] ?? null),
                        'service_charge_amount' => $safeDecimal($mappedData['service_charge_amount'] ?? null),
                    ]);
                }

                $insertData = [
                    'batch_id' => $batchId,
                    'user_id' => $userId,
                    'row_number' => $rowIndex + 2,
                    
                    // Tenant identification (for lookup during processing)
                    'tenancy_ref' => $mappedData['tenancy_ref'] ?? null,
                    'tenant_profile_ref' => $mappedData['tenant_profile_ref'] ?? null,
                    'tenant_email' => $mappedData['tenant_email'] ?? null,
                    
                    // These will be populated during processing
                    'tenant_id' => null, // Will be resolved during processing
                    'invoice_id' => $mappedData['invoice_id'] ?? null, // Required from template
                    'recorded_by' => null, // Will be set to user_id during processing
                    
                    // Payment information
                    'payment_date' => $safeDate($mappedData['payment_date'] ?? null),
                    'amount_paid' => $safeDecimal($mappedData['amount_paid'] ?? null),
                    'total_amount' => $safeDecimal($mappedData['total_amount'] ?? $mappedData['amount_paid'] ?? null),
                    'payment_method' => $mappedData['payment_method'] ?? null,
                    'transaction_reference' => $mappedData['transaction_reference'] ?? null,
                    'payment_status' => $mappedData['payment_status'] ?? 'paid',
                    'payment_notes' => $mappedData['payment_notes'] ?? null,
                    'receipt_path' => null, // For future receipt uploads
                    
                    // Allocation data (for partial payments)
                    'allocation_data' => $allocationData,
                    'single_invoice_amount' => $safeDecimal($mappedData['single_invoice_amount'] ?? null),
                    'lease_amount' => $safeDecimal($mappedData['lease_amount'] ?? null),
                    'service_charge_amount' => $safeDecimal($mappedData['service_charge_amount'] ?? null),
                    
                    // Combined payment handling
                    'is_combined_payment' => false, // Will be determined during processing based on invoice_id
                    'combined_payment_reference' => null, // Will be set during processing if needed
                    
                    // Late fee handling
                    'late_fee' => $safeDecimal($mappedData['late_fee'] ?? null),
                    'late_fee_paid_now' => $safeBool($mappedData['late_fee_paid_now'] ?? false),
                    
                    // Verification
                    'is_verified' => false, // Will be set during processing
                    'verified_at' => null, // Will be set during processing
                    
                    // Processing status
                    'processing_status' => 'pending',
                    'error_message' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Log::info('Bulk payment temp insert', [
                    'row' => $rowIndex + 2,
                    'insert_data' => $insertData,
                    'batch_id' => $batchId,
                    'user_id' => $userId
                ]);
                
                DB::table('temp_tenant_payments')->insert($insertData);
       
                $results['successful']++;
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage()
                ];
                
                // Store error in temp table
                $this->storeErrorInTempTable('temp_tenant_payments', $batchId, $rowIndex + 2, $e->getMessage(), $userId);
            }
        }
        
        return $results;
    }

    /**
     * Store notice data in temporary table
     */
    private function storeNoticeDataInTempTable($csvData, $fieldMappings, $batchId, $userId)
    {
        $results = ['successful' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($csvData as $rowIndex => $row) {
            try {
                // Map CSV row data to expected fields
                $mappedData = $this->mapCsvRowToFields($row, $fieldMappings);
                
                // Parse delivery methods
                $deliveryMethods = null;
                if (!empty($mappedData['delivery_method'])) {
                    $methods = array_map('trim', explode(',', $mappedData['delivery_method']));
                    $deliveryMethods = json_encode($methods);
                }
                
                DB::table('temp_tenant_notices')->insert([
                    'batch_id' => $batchId,
                    'user_id' => $userId,
                    'row_number' => $rowIndex + 2,
                    
                    // Tenant identification
                    'tenancy_ref' => $mappedData['tenancy_ref'] ?? null,
                    'tenant_profile_ref' => $mappedData['tenant_profile_ref'] ?? null,
                    'tenant_email' => $mappedData['tenant_email'] ?? null,
                    
                    // Notice information
                    'notice_type' => $mappedData['notice_type'] ?? null,
                    'notice_date' => $this->parseDate($mappedData['notice_date'] ?? null),
                    'effective_date' => $this->parseDate($mappedData['effective_date'] ?? null),
                    'notice_subject' => $mappedData['notice_subject'] ?? null,
                    'notice_details' => $mappedData['notice_details'] ?? null,
                    'notice_period' => $this->parseInteger($mappedData['notice_period'] ?? null),
                    'delivery_methods' => $deliveryMethods,
                    'priority_level' => $mappedData['priority_level'] ?? 'medium',
                    'response_required' => $this->parseBooleanValue($mappedData['response_required'] ?? 'false'),
                    'response_deadline' => $this->parseDate($mappedData['response_deadline'] ?? null),
                    'follow_up_required' => $this->parseBooleanValue($mappedData['follow_up_required'] ?? 'false'),
                    'follow_up_date' => $this->parseDate($mappedData['follow_up_date'] ?? null),
                    'escalation_level' => $this->parseInteger($mappedData['escalation_level'] ?? null),
                    'legal_reference' => $mappedData['legal_reference'] ?? null,
                    'amount_involved' => $this->parseDecimal($mappedData['amount_involved'] ?? null),
                    'contact_person' => $mappedData['contact_person'] ?? null,
                    'contact_phone' => $mappedData['contact_phone'] ?? null,
                    'internal_notes' => $mappedData['internal_notes'] ?? null,
                    
                    'processing_status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $results['successful']++;
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage()
                ];
                
                // Store error in temp table
                $this->storeErrorInTempTable('temp_tenant_notices', $batchId, $rowIndex + 2, $e->getMessage(), $userId);
            }
        }
        
        return $results;
    }

    /**
     * Store temporary tenant profile
     */
    private function storeTempTenantProfile($mappedData, $batchId, $rowNumber, $userId)
    {
        return DB::table('temp_tenant_profiles')->insertGetId([
            'batch_id' => $batchId,
            'user_id' => $userId,
            'row_number' => $rowNumber,
            
            // Basic tenant info
            'tenant_type' => $mappedData['tenant_type'] ?? null,
            'use_existing_tenant' => $this->parseBooleanValue($mappedData['use_existing_tenant'] ?? 'false'),
            'tenant_ref' => $mappedData['tenant_ref'] ?? null,
            
            // Individual tenant fields
            'tenant_name' => $mappedData['tenant_name'] ?? null,
            'tenant_email' => $mappedData['tenant_email'] ?? null,
            'tenant_phone' => $mappedData['tenant_phone'] ?? null,
            'tenant_id_number' => $mappedData['tenant_id_number'] ?? null,
            'date_of_birth' => $this->parseDate($mappedData['date_of_birth'] ?? null),
            'gender' => $mappedData['gender'] ?? null,
            'nationality' => $mappedData['nationality'] ?? null,
            'occupation' => $mappedData['occupation'] ?? null,
            'employer' => $mappedData['employer'] ?? null,
            'monthly_income' => $this->parseDecimal($mappedData['monthly_income'] ?? null),
            
            // Business tenant fields
            'business_name' => $mappedData['business_name'] ?? null,
            'business_registration' => $mappedData['business_registration'] ?? null,
            'business_contact_person' => $mappedData['business_contact_person'] ?? null,
            'business_email' => $mappedData['business_email'] ?? null,
            'business_phone' => $mappedData['business_phone'] ?? null,
            'business_type' => $mappedData['business_type'] ?? null,
            'tax_id_number' => $mappedData['tax_id_number'] ?? null,
            'annual_revenue' => $this->parseDecimal($mappedData['annual_revenue'] ?? null),
            'years_in_business' => $this->parseInteger($mappedData['years_in_business'] ?? null),
            
            // Emergency contact
            'emergency_contact_name' => $mappedData['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $mappedData['emergency_contact_phone'] ?? null,
            'emergency_contact_relationship' => $mappedData['emergency_contact_relationship'] ?? null,
            'emergency_contact_address' => $mappedData['emergency_contact_address'] ?? null,
            
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Store temporary tenant management
     */
    private function storeTempTenantManagement($mappedData, $batchId, $rowNumber, $tempProfileId, $userId)
    {
        return DB::table('temp_tenant_management')->insertGetId([
            'batch_id' => $batchId,
            'user_id' => $userId,
            'temp_tenant_profile_id' => $tempProfileId,
            'row_number' => $rowNumber,
            
            // Property Information
            'listing_type' => $mappedData['listing_type'] ?? null,
            'complex_slug' => $mappedData['complex_slug'] ?? null,
            'furnishing' => $mappedData['furnishing'] ?? null,
            'bedrooms' => $this->parseInteger($mappedData['bedrooms'] ?? null),
            'bathrooms' => $this->parseInteger($mappedData['bathrooms'] ?? null),
            'sq_area' => $this->parseDecimal($mappedData['sq_area'] ?? null),
            'unit_number' => $mappedData['unit_number'] ?? null,
            'commercial_unit_id' => $this->parseInteger($mappedData['commercial_unit_id'] ?? null),
            'occupied_sq_area' => $this->parseDecimal($mappedData['occupied_sq_area'] ?? null),
            'rate_per_sq_ft' => $this->parseDecimal($mappedData['rate_per_sq_ft'] ?? null),
            'specific_unit_number' => $mappedData['specific_unit_number'] ?? null,
            
            // Agreement Details
            'agreement_type' => $mappedData['agreement_type'] ?? 'rental',
            'start_date' => $this->parseDate($mappedData['start_date'] ?? null),
            'end_date' => $this->parseDate($mappedData['end_date'] ?? null),
            'monthly_payment' => $this->parseDecimal($mappedData['monthly_payment'] ?? null),
            'security_deposit' => $this->parseDecimal($mappedData['security_deposit'] ?? null),
            'grace_period_days' => $this->parseInteger($mappedData['grace_period_days'] ?? null),
            'late_fee_amount' => $this->parseDecimal($mappedData['late_fee_amount'] ?? null),
            'penalty_amount' => $this->parseDecimal($mappedData['penalty_amount'] ?? null),
            
            // Additional Information
            'number_of_occupants' => $this->parseInteger($mappedData['number_of_occupants'] ?? 1),
            'has_pets' => $this->parseBooleanValue($mappedData['has_pets'] ?? 'false'),
            'pet_details' => $mappedData['pet_details'] ?? null,
            'general_notes' => $mappedData['general_notes'] ?? null,
            'status' => $mappedData['status'] ?? 'Active',
            'payment_frequency' => $mappedData['payment_frequency'] ?? 'Monthly',
            'payment_due_day' => $this->parseInteger($mappedData['payment_due_day'] ?? 1),
            
            'processing_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Map CSV row to fields using field mappings
     */
    private function mapCsvRowToFields($row, $fieldMappings)
    {
        $mappedData = [];
        
        foreach ($fieldMappings as $fieldKey => $mapping) {
            $columnIndex = $mapping['columnIndex'] ?? null;
            if ($columnIndex !== null && isset($row[$columnIndex])) {
                $mappedData[$fieldKey] = trim($row[$columnIndex]);
            }
        }
        
        return $mappedData;
    }

    /**
     * Store error in temporary table
     */
    private function storeErrorInTempTable($tableName, $batchId, $rowNumber, $errorMessage, $userId)
    {
        try {
            DB::table($tableName)->insert([
                'batch_id' => $batchId,
                'user_id' => $userId,
                'row_number' => $rowNumber,
                'processing_status' => 'failed',
                'error_message' => $errorMessage,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store error in temp table: ' . $e->getMessage());
        }
    }

    /**
     * Parse date value
     */
    public function parseDate($value)
    {
        if (empty($value)) return null;
        Log::info("Parsing date value: {$value}");

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \Exception("Invalid date format: {$value}");
        }
    }

    /**
     * Parse decimal value
     */
    public function parseDecimal($value)
    {
        if (empty($value)) return null;
        
        // Remove any currency symbols or commas
        $cleaned = preg_replace('/[^\d.-]/', '', $value);
        
        if (!is_numeric($cleaned)) {
            throw new \Exception("Invalid decimal value: {$value}");
        }
        
        return (float) $cleaned;
    }

    /**
     * Parse integer value
     */
    public function parseInteger($value)
    {
        if (empty($value)) return null;
        
        if (!is_numeric($value)) {
            throw new \Exception("Invalid integer value: {$value}");
        }
        
        return (int) $value;
    }

    /**
     * Parse boolean value
     */
    public function parseBooleanValue($value)
    {
        if (empty($value)) return false;
        
        $value = strtolower(trim($value));
        return in_array($value, ['true', '1', 'yes', 'on']);
    }
}