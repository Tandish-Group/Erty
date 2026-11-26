<?php

namespace App\Http\Requests;

use App\Models\Agent;
use App\Models\Lead;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Capturing a lead: for a new person or business (whose email must be new to this business), or for a
 * profile the business already has. Access is checked by the business access map.
 */
class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'profile_mode' => 'required|in:new,existing',
            'lead_type' => 'required|in:Individual,Business',
            'source' => 'required|string|max:255',
            'property_type' => 'required|string|max:255',
            'priority' => 'nullable|in:hot,warm,cold',
            'purpose' => 'nullable|in:rent,buy,lease',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|string|max:10',
            'bathrooms' => 'nullable|string|max:10',
            'timeline' => 'nullable|string|max:50',
            'contact_method' => 'nullable|in:phone,email,sms,whatsapp,any',
            'contact_time' => 'nullable|in:morning,afternoon,evening,weekend,anytime',
            'follow_up_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'referral_details' => 'nullable|string|max:500',
            'location_preference' => 'nullable|string|max:255',
            'special_requirements' => 'nullable|string|max:1000',
            'min_sq_area' => 'nullable|numeric|min:0',
            'max_sq_area' => 'nullable|numeric|min:0',
            'business_use' => 'nullable|string|max:255',
            'assigned_agent' => ['nullable', function ($attribute, $value, $fail) {
            if ($value === 'auto') return;

            $agentIsAssignable = Agent::where('id', $value)
            ->where('created_by', business_owner_id())
            ->where('status', 'active')
            ->where('is_active', true)
            ->exists();

            if (!$agentIsAssignable) $fail('Select an active agent from your team.');
            }],
        ];

        if ($this->input('profile_mode') === 'existing') {
            // The person is already on file: pick their profile instead of typing their details again.
            $rules['existing_profile_id'] = ['required', 'integer', function ($attribute, $value, $fail) {
                if (!Lead::forBusiness()->where('lead_profile_id', $value)->exists()) {
                    $fail('Choose one of your existing lead profiles.');
                }
            }];
        } elseif ($this->input('lead_type') === 'Individual') {
            $rules += [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => ['required', 'email', 'max:255', $this->uniqueInBusiness('email')],
                'phone' => 'required|string|max:20',
                'occupation' => 'nullable|string|max:255',
                'date_of_birth' => 'nullable|date|before:today',
                'current_address' => 'nullable|string|max:500'
            ];
        } elseif ($this->input('lead_type') === 'Business') {
            $rules += [
                'company_name' => 'required|string|max:255',
                'contact_person' => 'required|string|max:255',
                'business_email' => ['required', 'email', 'max:255', $this->uniqueInBusiness('business_email')],
                'business_phone' => 'required|string|max:20',
                'registration_number' => 'nullable|string|max:100',
                'contact_title' => 'nullable|string|max:100',
                'industry' => 'nullable|string|max:100',
                'company_size' => 'nullable|string|max:50',
                'business_address' => 'nullable|string|max:500'
            ];
        }

        if (in_array($this->input('source'), ['referral', 'agent_referral'], true)) {
            $rules['referral_details'] = 'required|string|max:500';
        }
        if ($this->input('budget_min') && $this->input('budget_max')) {
            $rules['budget_max'] = 'numeric|gt:budget_min';
        }

        return $rules;
    }

    /** Unique among this business's leads only: other businesses' leads are invisible here. */
    private function uniqueInBusiness(string $column)
    {
        return Rule::unique('lead_profiles', $column)->where(fn ($query) => $query->whereIn('id', Lead::forBusiness()->select('lead_profile_id')));
    }

    /** The lead form expects this shape. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['status' => 422, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422));
    }
}
