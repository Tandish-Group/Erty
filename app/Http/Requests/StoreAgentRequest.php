<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Adding an agent (an agency, an employee or a contractor). Departments, roles, work locations and the reporting
 * manager must be this business's own, and the email new to this business. Access is checked by the access map.
 */
class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
            $rules = [
                'agent_type' => 'required|in:agency,employee,contractor',
                'department' => ['required', $this->ours('agent_departments')],
                'role' => ['required', $this->ours('agent_roles')],
                'commission_rate' => 'required|numeric|min:0|max:100',
            ];

            // Add conditional validation based on agent type
            if ($this->input('agent_type') === 'agency') {
                $rules = array_merge($rules, [
                    'company_name' => 'required|string|max:200',
                    'contact_person_name' => 'required|string|max:150',
                    'contact_person_email' => ['required', 'email', 'max:150', $this->newAgentEmail()],
                    'contact_person_phone' => 'required|string|max:20',
                    'agency_license' => 'nullable|string|max:100',
                    'tax_id' => 'nullable|string|max:50',
                    'business_registration_number' => 'nullable|string|max:100',
                    'company_address' => 'nullable|string',
                ]);
            } elseif ($this->input('agent_type') === 'employee') {
                $rules = array_merge($rules, [
                    'first_name' => 'required|string|max:100',
                    'last_name' => 'required|string|max:100',
                    'email' => ['required', 'email', 'max:150', $this->newAgentEmail()],
                    'phone' => 'required|string|max:20',
                    'gender' => 'required|in:male,female,other,prefer_not_to_say',
                    'employment_type' => 'required|in:full_time,part_time',
                    'start_date' => 'required|date',
                    'work_location' => ['required', $this->ours('agent_work_locations')],
                    'date_of_birth' => 'nullable|date',
                    'address' => 'nullable|string',
                    'national_id' => 'nullable|string|max:50',
                    'emergency_contact' => 'nullable|string|max:20',
                    'probation_period' => 'nullable|integer|min:0|max:12',
                    'reporting_manager' => ['nullable', $this->teamMember()],
                    'specializations' => 'nullable|array',
                    'job_description' => 'nullable|string',
                    'base_salary' => 'nullable|numeric|min:0',
                    'commission_type' => 'nullable|in:sales_value,net_profit,flat_rate,tiered',
                    'bonus_rate' => 'nullable|numeric|min:0|max:100',
                    'payment_frequency' => 'nullable|in:weekly,bi_weekly,monthly,quarterly',
                    'benefits' => 'nullable|array',
                    'sales_target' => 'nullable|numeric|min:0',
                ]);
            } elseif ($this->input('agent_type') === 'contractor') {
                $rules = array_merge($rules, [
                    'first_name' => 'required|string|max:100',
                    'last_name' => 'required|string|max:100',
                    'email' => ['required', 'email', 'max:150', $this->newAgentEmail()],
                    'phone' => 'required|string|max:20',
                    'gender' => 'required|in:male,female,other,prefer_not_to_say',
                    'contract_type' => 'required|in:hourly,project,retainer,commission_only',
                    'contract_start_date' => 'required|date',
                    'work_location_contractor' => ['required', $this->ours('agent_work_locations')],
                    'contract_end_date' => 'nullable|date|after:contract_start_date',
                    'date_of_birth' => 'nullable|date',
                    'address' => 'nullable|string',
                    'national_id' => 'nullable|string|max:50',
                    'emergency_contact' => 'nullable|string|max:20',
                    'specializations' => 'nullable|array',
                    'job_description' => 'nullable|string',
                    'hourly_rate' => 'nullable|numeric|min:0',
                    'project_fee' => 'nullable|numeric|min:0',
                    'payment_terms' => 'nullable|string',
                    'commission_rate_contractor' => 'nullable|numeric|min:0|max:100',
                    'commission_type_contractor' => 'nullable|in:sales_value,net_profit,flat_rate',
                    'payment_frequency_contractor' => 'nullable|in:upon_completion,weekly,bi_weekly,monthly,milestone',
                    'sales_target_contractor' => 'nullable|numeric|min:0',
                ]);
            }

        return $rules;
    }

    /** A department, role or work location this business set up. */
    private function ours(string $table)
    {
        return Rule::exists($table, 'id')->where('created_by', business_owner_id())->whereNull('deleted_at');
    }

    private function newAgentEmail()
    {
        return Rule::unique('agents', 'email')->where('created_by', business_owner_id());
    }

    /** The owner or one of their team members. */
    private function teamMember()
    {
        $owner = business_owner_id();
        return Rule::exists('users', 'id')->where(fn ($query) => $query->where('id', $owner)->orWhere('main_account_user_id', $owner));
    }

    /** The agent form expects this shape. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['status' => 422, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422));
    }
}
