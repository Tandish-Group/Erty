<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The tenancy onboarding form: who the tenant is (a new profile or an existing one), the property and unit,
 * the agreement and billing, documents and extras. Access is checked by the business access map.
 */
class StoreTenancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = $this->input('tenant_option') === 'existing' ? $this->forExistingTenant() : $this->forNewTenant();

        return $this->combine([
            'onboarding_confirmed' => 'accepted',
            'tenant_type' => 'required|in:Individual,Business',
            'tenant_option' => 'required|in:new,existing',
            'existing_tenant_id' => ['required_if:tenant_option,existing', 'nullable', Rule::exists('tenant_profiles', 'id')->where('managed_by', business_owner_id())],
        ], $tenant, [
            'currency' => 'required|string|size:3',
            'payment_interval' => 'required|in:Monthly,Quarterly,Annually',
            'build_out_period' => 'nullable|integer|min:0',
            'service_charge_per_sq_ft' => 'nullable|numeric|min:0',
            'tenancy_quarterly_payment_type' => 'required_if:payment_interval,Quarterly|nullable|in:pre_pay,post_pay',
            'kt_repeater_additional_parking_slots' => 'prohibited',
            'residential_unit_id' => 'nullable|integer',
            'commercial_unit_id' => 'required_if:listing_type,Commercial Unit|nullable|integer',
            'occupied_sq_area' => 'required_if:listing_type,Commercial Unit|nullable|numeric|gt:0',
            'assigned_agent_id' => ['nullable', Rule::exists('agents', 'id')->where('created_by', business_owner_id())],
            'parking_slot' => 'nullable|array',
            'parking_slot.*' => 'integer|distinct',
            'legal_files' => 'nullable|array',
            'legal_files.*' => ['string', Rule::exists('legals', 'file_name')->where('user_id', business_owner_id())],
        ], $this->followUps());
    }

    /** Merge rule sets; a field named in several sets keeps every rule (as when the stages were validated in turn). */
    private function combine(array ...$sets): array
    {
        $rules = [];
        foreach ($sets as $set) {
            foreach ($set as $field => $rule) {
                $rule = is_string($rule) ? explode('|', $rule) : (array) $rule;
                $rules[$field] = isset($rules[$field]) ? array_merge($rules[$field], $rule) : $rule;
            }
        }
        return $rules;
    }

    /** A tenancy for a tenant profile the business already has. */
    private function forExistingTenant(): array
    {
        return [
            // Property Information - Step 2
            'listing_type' => 'required|string',
            'complex' => 'required|string|max:255',

            // Non-Commercial property fields (conditional)
            'furnishing' => 'nullable|in:Unfurnished,Furnished,Partially Furnished',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'sq_area' => 'nullable|numeric|min:0',
            'unit_number' => 'nullable|string|max:50',

            // Commercial property fields (conditional)
            'commercial_unit_id' => 'nullable|exists:commercial_units,id',
            'occupied_sq_area' => 'nullable|numeric|min:0',
            'rate_per_sq_ft' => 'nullable|numeric|min:0',
            'specific_unit_number' => 'nullable|string|max:50',

            // Agreement Details - Step 3
            'agreement_type' => 'required|in:rental,lease',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'monthly_payment' => 'required|numeric|min:0',
            'security_deposit' => 'nullable|numeric|min:0',
            'grace_period' => 'nullable|integer|min:0',
            'rent_paid_up_to' => 'nullable|date|after_or_equal:start_date|before_or_equal:today',
            'late_fee_amount' => 'nullable|numeric|min:0',
            'penalty_amount' => 'nullable|numeric|min:0',

            // Documents and Notes - Step 4
            'documents' => 'nullable|array',
            'documents.*' => 'file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
            'notes' => 'nullable|string',

            // Additional fields that might be collected
            'number_of_occupants' => 'nullable|integer|min:1',
            'has_pets' => 'nullable|boolean',
            'pet_details' => 'nullable|string',
        ];
    }

    /** A new tenant profile with the tenancy. */
    private function forNewTenant(): array
    {
        return [
            // Personal Information - Step 1 (for tenant profile)
            'tenant_type' => 'required|in:Individual,Business',

            // Individual tenant fields
            'tenant_name' => 'required_if:tenant_type,Individual|string|max:255|nullable',
            'tenant_email' => 'required_if:tenant_type,Individual|email|nullable',
            'tenant_phone' => 'required_if:tenant_type,Individual|string|max:20|nullable',
            'tenant_id_number' => 'required_if:tenant_type,Individual|string|nullable',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:Male,Female,Other',
            'nationality' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:100',
            'employer' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',

            // Business tenant fields
            'business_name' => 'required_if:tenant_type,Business|string|max:255|nullable',
            'business_registration' => 'required_if:tenant_type,Business|string|max:255|nullable',
            'business_contact_person' => 'required_if:tenant_type,Business|string|max:255|nullable',
            'business_email' => 'required_if:tenant_type,Business|email|nullable',
            'business_phone' => 'required_if:tenant_type,Business|string|max:20|nullable',
            'business_type' => 'nullable|string|max:100',
            'tax_id_number' => 'nullable|string|max:50',
            'annual_revenue' => 'nullable|numeric|min:0',
            'years_in_business' => 'nullable|integer|min:0',

            // Emergency contacts (for both types)
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:100',

            // Property Information - Step 2
            'listing_type' => 'required|string',
            'complex' => 'required|string|max:255',

            // Non-Commercial property fields (conditional)
            'furnishing' => 'nullable|in:Unfurnished,Furnished,Partially Furnished',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'sq_area' => 'nullable|numeric|min:0',
            'unit_number' => 'nullable|string|max:50',

            // Commercial property fields (conditional)
            'commercial_unit_id' => 'nullable|exists:commercial_units,id',
            'occupied_sq_area' => 'nullable|numeric|min:0',
            'rate_per_sq_ft' => 'nullable|numeric|min:0',
            'specific_unit_number' => 'nullable|string|max:50',

            // Agreement Details - Step 3
            'agreement_type' => 'required|in:rental,lease',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'monthly_payment' => 'required|numeric|min:0',
            'security_deposit' => 'nullable|numeric|min:0',
            'grace_period' => 'nullable|integer|min:0',
            'rent_paid_up_to' => 'nullable|date|after_or_equal:start_date|before_or_equal:today',
            'late_fee_amount' => 'nullable|numeric|min:0',
            'penalty_amount' => 'nullable|numeric|min:0',

            // Documents and Notes - Step 4
            'documents' => 'nullable|array',
            'documents.*' => 'file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
            'notes' => 'nullable|string',

            // Additional fields that might be collected
            'number_of_occupants' => 'nullable|integer|min:1',
            'has_pets' => 'nullable|boolean',
            'pet_details' => 'nullable|string',
        ];
    }

    /** Rules that depend on other answers. */
    private function followUps(): array
    {
        $rules = [];
        if ($this->input('tenant_option') === 'new' && $this->input('tenant_type') === 'Individual') {
            $rules['emergency_contact_name'] = 'required_with:emergency_contact_phone|nullable|string|max:255';
            $rules['emergency_contact_phone'] = 'required_with:emergency_contact_name|nullable|string|max:20';
        }
        if ($this->input('listing_type') !== 'Commercial Unit' && $this->boolean('has_pets')) {
            $rules['pet_details'] = 'required|string|max:2000';
        }
        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('listing_type') === 'Commercial Unit' && $this->filled('end_date') && $this->filled('start_date')
                && !$validator->errors()->hasAny(['start_date', 'end_date', 'build_out_period'])
                && Carbon::parse($this->input('start_date'))->addDays((int) $this->input('build_out_period', 0))->gte(Carbon::parse($this->input('end_date')))) {
                $validator->errors()->add('build_out_period', 'Billing must start before the agreement ends.');
            }
        }];
    }
}
