<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Moving a lead along: status changes, confirming its details and recording interactions. */
class LeadStatusController extends Controller
{
    /**
     * Update lead status
     */
    public function updateLeadStatus(Request $request, $id)
    {
        try {
            $leadId = decrypt($id);
            $lead = Lead::forBusiness()->findOrFail($leadId);
            
            $request->validate([
                'status' => 'required|in:new,contacted,qualified,proposal,closed_won,closed_lost',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($request->status !== 'new' && !$lead->details_confirmed_at) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Confirm the lead details before moving this lead beyond New.'
                ], 422);
            }

            if (in_array($request->status, ['qualified', 'proposal', 'closed_won'], true)
                && !$lead->qualification_confirmed_at) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Confirm qualification before moving this lead to the next commercial stage.'
                ], 422);
            }
            
            $lead->updateStatus($request->status, $request->notes);
            
            return response()->json([
                'status' => 200,
                'message' => 'Lead status updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating lead status: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Confirm the factual intake or qualification information for a lead.
     */
    public function confirmDetails(Request $request, $id)
    {
        try {
            $leadId = decrypt($id);
            $lead = Lead::with('profile')->where('user_id', business_owner_id())->findOrFail($leadId);

            $request->validate([
                'confirmation_type' => 'required|in:details,qualification',
            ]);

            $missing = $request->confirmation_type === 'details'
                ? $this->leadDetailsConfirmationGaps($lead)
                : $this->leadQualificationConfirmationGaps($lead);

            if ($missing) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Complete the following before confirming: ' . implode(', ', $missing),
                    'missing' => $missing,
                ], 422);
            }

            $now = now();
            $attributes = $request->confirmation_type === 'details'
                ? ['details_confirmed_at' => $now, 'details_confirmed_by' => auth()->id()]
                : ['qualification_confirmed_at' => $now, 'qualification_confirmed_by' => auth()->id()];

            $lead->update($attributes);

            return response()->json([
                'status' => 200,
                'message' => ucfirst($request->confirmation_type) . ' confirmed successfully.',
                'confirmed_at' => $now->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error confirming lead details: ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Unable to confirm this lead right now.',
            ], 500);
        }
    }
    /**
     * Add interaction to lead
     */
    public function addInteraction(Request $request, $id)
    {
        try {
            $leadId = decrypt($id);
            $lead = Lead::forBusiness()->findOrFail($leadId);
            
            $request->validate([
                'interaction_type' => 'required|string',
                'details' => 'required|string|max:1000',
                'followup_date' => 'nullable|date'
            ]);
            
            $lead->addInteraction($request->interaction_type, $request->details);
            
            if ($request->requires_followup && $request->followup_date) {
                $lead->update(['follow_up_date' => $request->followup_date]);
            }
            
            return response()->json([
                'status' => 200,
                'message' => 'Interaction added successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error adding interaction: ' . $e->getMessage()
            ], 500);
        }
    }
    private function leadDetailsConfirmationGaps(Lead $lead): array
    {
        $gaps = [];
        $profile = $lead->profile;

        if (!$profile) {
            $gaps[] = 'lead profile';
        } elseif ($profile->lead_type === 'Individual') {
            if (!$profile->first_name || !$profile->last_name) $gaps[] = 'full name';
            if (!$profile->email && !$profile->phone) $gaps[] = 'email or phone';
        } elseif (!$profile->company_name || !$profile->contact_person) {
            $gaps[] = 'company and contact person';
        }

        if (!$lead->source) $gaps[] = 'lead source';
        if (!$lead->property_type) $gaps[] = 'property requirement';

        return $gaps;
    }
    private function leadQualificationConfirmationGaps(Lead $lead): array
    {
        $gaps = $this->leadDetailsConfirmationGaps($lead);

        if (!$lead->details_confirmed_at) $gaps[] = 'confirmed lead details';
        if (!$lead->purpose) $gaps[] = 'rental or purchase purpose';
        if (!$lead->assigned_agent_id) $gaps[] = 'assigned agent';

        return array_values(array_unique($gaps));
    }
}
