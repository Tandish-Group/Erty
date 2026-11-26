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
    /**
     * Stage tenant rows: each row is a tenant (profile) and their tenancy. A value that cannot be read (a bad date
     * or number) is noted on the row instead of losing it, so the review page can say what to fix.
     */
    private function storeTenantDataInTempTable($csvData, $fieldMappings, $batchId, $userId)
    {
        $results = ['successful' => 0, 'failed' => 0, 'errors' => []];

        foreach ($csvData as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;
            try {
                $data = $this->mapCsvRowToFields($row, $fieldMappings);
                $problems = [];
                $date = function ($key, $label) use ($data, &$problems) {
                    try { return $this->parseDate($data[$key] ?? null); } catch (\Exception) { $problems[] = $label . ' "' . $data[$key] . '" is not a date'; return null; }
                };
                $number = function ($key, $label) use ($data, &$problems) {
                    try { return $this->parseDecimal($data[$key] ?? null); } catch (\Exception) { $problems[] = $label . ' "' . $data[$key] . '" is not a number'; return null; }
                };
                $text = fn ($key) => ($v = trim((string) ($data[$key] ?? ''))) === '' ? null : $v;

                $profile = [
                    'tenant_type' => $text('tenant_type'),
                    'tenant_name' => $text('tenant_name'), 'tenant_email' => $text('tenant_email'), 'tenant_phone' => $text('tenant_phone'),
                    'tenant_id_number' => $text('tenant_id_number'), 'date_of_birth' => $date('date_of_birth', 'Date of birth'),
                    'gender' => $text('gender'), 'nationality' => $text('nationality'), 'occupation' => $text('occupation'), 'employer' => $text('employer'),
                    'business_name' => $text('business_name'), 'business_registration' => $text('business_registration'),
                    'business_contact_person' => $text('business_contact_person'), 'business_email' => $text('business_email'),
                    'business_phone' => $text('business_phone'), 'business_type' => $text('business_type'),
                    'emergency_contact_name' => $text('emergency_contact_name'), 'emergency_contact_phone' => $text('emergency_contact_phone'),
                    'emergency_contact_relationship' => $text('emergency_contact_relationship'),
                ];
                $tenancy = [
                    'property' => $text('property'), 'complex_slug' => $text('property'),
                    'unit_number' => $text('unit_number'),
                    'occupied_sq_area' => $number('occupied_sq_area', 'Area'),
                    'rate_per_sq_ft' => $number('rate_per_sq_ft', 'Rent per sq ft'),
                    'service_charge_per_sq_ft' => $number('service_charge_per_sq_ft', 'Service charge per sq ft'),
                    'monthly_payment' => $number('monthly_rent', 'Rent'),
                    'payment_frequency' => $text('payment_frequency'),
                    'start_date' => $date('start_date', 'Start date'),
                    'end_date' => $date('end_date', 'End date'),
                    'security_deposit' => $number('deposit', 'Deposit') ?? 0,
                    'deposit_held' => $number('deposit_held', 'Deposit held'),
                    'balance_owed' => $number('balance_owed', 'Balance owed') ?? 0,
                    'currency' => $text('currency'),
                    'agreement_type' => $text('agreement_type'),
                    'furnishing' => $text('furnishing'),
                    'number_of_occupants' => (int) ($number('number_of_occupants', 'Occupants') ?? 0) ?: null,
                    'general_notes' => $text('notes'),
                ];
                $error = $problems ? 'Row ' . $rowNumber . ': ' . implode(', ', $problems) . '.' : null;

                // Empty values are left out so the columns' defaults apply.
                $filled = fn (array $values) => array_filter($values, fn ($v) => $v !== null);
                DB::transaction(function () use ($profile, $tenancy, $filled, $batchId, $userId, $rowNumber, $error) {
                    $profileId = DB::table('temp_tenant_profiles')->insertGetId($filled($profile) + [
                        'batch_id' => $batchId, 'user_id' => $userId, 'row_number' => $rowNumber,
                        'use_existing_tenant' => false, 'status' => $error ? 'failed' : 'pending', 'error_message' => $error,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('temp_tenant_management')->insert($filled($tenancy) + [
                        'batch_id' => $batchId, 'user_id' => $userId, 'row_number' => $rowNumber, 'temp_tenant_profile_id' => $profileId,
                        'processing_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                });
                $results['successful']++;
            } catch (\Exception $e) {
                $results['failed']++;
                Log::warning('Tenants import row could not be staged', ['batch_id' => $batchId, 'row' => $rowNumber, 'error' => $e->getMessage()]);
                $message = 'Row ' . $rowNumber . ' could not be read: check its values against the example file.';
                $results['errors'][] = ['row' => $rowNumber, 'error' => $message];
                $this->storeErrorInTempTable('temp_tenant_profiles', $batchId, $rowNumber, $message, $userId);
            }
        }
        Log::info('Tenants import staged', ['batch_id' => $batchId, 'rows' => count($csvData), 'failed' => $results['failed']]);

        return $results;
    }

    /**
     * Store payment data in temporary table
     */
    /**
     * Stage payment rows for review. A row needs the tenant (tenancy_ref, tenant_profile_ref or tenant_email), the
     * date, the amount and the method; the invoice to pay first and "cleared" are optional. Rows that cannot be
     * read are kept with their reason so the review page can show them.
     */
    private function storePaymentDataInTempTable($csvData, $fieldMappings, $batchId, $userId)
    {
        $results = ['successful' => 0, 'failed' => 0, 'errors' => []];

        foreach ($csvData as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;
            try {
                $data = $this->mapCsvRowToFields($row, $fieldMappings);
                $date = $this->parseDate($data['payment_date'] ?? null);
                $amount = $this->parseDecimal($data['amount_paid'] ?? null);
                $problems = array_filter([
                    !($data['tenancy_ref'] ?? '') && !($data['tenant_profile_ref'] ?? '') && !($data['tenant_email'] ?? '') ? 'no tenancy reference, tenant reference or e-mail' : null,
                    !$date ? 'no payment date' : null,
                    !$amount || $amount <= 0 ? 'no amount' : null,
                ]);
                $method = self::paymentMethod($data['payment_method'] ?? null);
                // "cleared" = no (or the old payment_status = pending) means the money has not cleared yet.
                $pending = (isset($data['cleared']) && $data['cleared'] !== '' && !$this->parseBooleanValue($data['cleared']))
                    || strtolower((string) ($data['payment_status'] ?? '')) === 'pending';

                DB::table('temp_tenant_payments')->insert([
                    'batch_id' => $batchId,
                    'user_id' => $userId,
                    'row_number' => $rowNumber,
                    'tenancy_ref' => ($data['tenancy_ref'] ?? '') ?: null,
                    'tenant_profile_ref' => ($data['tenant_profile_ref'] ?? '') ?: null,
                    'tenant_email' => ($data['tenant_email'] ?? '') ?: null,
                    'invoice_id' => ($data['invoice_id'] ?? '') ?: null,
                    'payment_date' => $date,
                    'amount_paid' => $amount ?? 0,
                    'total_amount' => $amount ?? 0,
                    'payment_method' => $method,
                    'transaction_reference' => ($data['transaction_reference'] ?? '') ?: null,
                    'payment_status' => $pending ? 'pending' : 'paid',
                    'payment_notes' => ($data['payment_notes'] ?? '') ?: null,
                    'is_combined_payment' => false,
                    'late_fee' => 0,
                    'late_fee_paid_now' => false,
                    'is_verified' => false,
                    'processing_status' => $problems ? 'failed' : 'pending',
                    'error_message' => $problems ? 'Row ' . $rowNumber . ': ' . implode(', ', $problems) . '.' : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $results['successful']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = ['row' => $rowNumber, 'error' => $e->getMessage()];
                $this->storeErrorInTempTable('temp_tenant_payments', $batchId, $rowNumber, $e->getMessage(), $userId);
            }
        }
        Log::info('Payments import staged', ['batch_id' => $batchId, 'rows' => count($csvData), 'failed' => $results['failed']]);

        return $results;
    }

    /** A payment method as Erty stores it, accepting common spellings (M-Pesa, cheque, bank). */
    public static function paymentMethod(?string $value): string
    {
        $v = str_replace([' ', '-'], '_', strtolower(trim((string) $value)));

        return match (true) {
            in_array($v, ['cash', 'bank_transfer', 'mobile_money', 'check', 'card', 'other'], true) => $v,
            in_array($v, ['mpesa', 'm_pesa', 'mobile', 'airtel_money'], true) => 'mobile_money',
            in_array($v, ['bank', 'eft', 'transfer', 'rtgs', 'bank_deposit'], true) => 'bank_transfer',
            in_array($v, ['cheque'], true) => 'check',
            in_array($v, ['debit_card', 'credit_card'], true) => 'card',
            default => 'other',
        };
    }

    /**
     * Store notice data in temporary table
     */
    /** Stage notice rows (notices already given outside Erty), keeping unreadable values as a note on the row. */
    private function storeNoticeDataInTempTable($csvData, $fieldMappings, $batchId, $userId)
    {
        $results = ['successful' => 0, 'failed' => 0, 'errors' => []];

        foreach ($csvData as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;
            try {
                $data = $this->mapCsvRowToFields($row, $fieldMappings);
                $problems = [];
                $date = function ($key, $label) use ($data, &$problems) {
                    try { return $this->parseDate($data[$key] ?? null); } catch (\Exception) { $problems[] = $label . ' "' . $data[$key] . '" is not a date'; return null; }
                };
                $text = fn ($key) => ($v = trim((string) ($data[$key] ?? ''))) === '' ? null : $v;
                $noticeDate = $date('notice_date', 'Notice date');
                $effective = $date('effective_date', 'Effective date');
                $error = $problems ? 'Row ' . $rowNumber . ': ' . implode(', ', $problems) . '.' : null;

                DB::table('temp_tenant_notices')->insert([
                    'batch_id' => $batchId, 'user_id' => $userId, 'row_number' => $rowNumber,
                    'tenancy_ref' => $text('tenancy_ref'), 'tenant_profile_ref' => $text('tenant_profile_ref'), 'tenant_email' => $text('tenant_email'),
                    'notice_type' => $text('notice_type'), 'notice_date' => $noticeDate, 'effective_date' => $effective,
                    'notice_subject' => $text('notice_subject'), 'notice_details' => $text('notice_details'),
                    'notice_period' => ctype_digit((string) ($data['notice_period'] ?? '')) ? (int) $data['notice_period'] : null,
                    'delivery_methods' => $text('delivery_methods') ?? $text('delivery_method'),
                    'processing_status' => $error ? 'failed' : 'pending', 'error_message' => $error,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $results['successful']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = ['row' => $rowNumber, 'error' => $e->getMessage()];
                $this->storeErrorInTempTable('temp_tenant_notices', $batchId, $rowNumber, $e->getMessage(), $userId);
            }
        }

        return $results;
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
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        // YYYY-MM-DD first, then day-first (15/03/2026, 15-03-2026, 15.03.2026) as written in Kenya and most of
        // the world, then anything else Carbon understands ("15 March 2026").
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            $errors = \DateTime::getLastErrors();
            if ($date && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        if (preg_match('#^\d{1,2}[/.-]\d{1,2}[/.-]\d{2,4}$#', $value)) {
            throw new \Exception("Invalid date format: {$value}"); // numeric but not a real day-first date
        }
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