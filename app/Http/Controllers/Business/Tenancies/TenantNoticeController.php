<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Controllers\Controller;
use App\Models\TenantManagement;
use App\Models\TenantNotice;
use App\Models\TenantTermination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TenantNoticeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Give a formal notice to a tenant.
     */
    public function giveNotice(Request $request)
    {
        $request->validate([
            'tenant_id'      => 'required|exists:tenant_management,id',
            'notice_type'    => 'required|in:lease_termination,rent_increase,lease_violation,property_inspection,maintenance_notice,eviction_notice,lease_renewal,other',
            'notice_date'    => 'required|date',
            'effective_date' => 'required|date|after_or_equal:notice_date',
            'notice_period'  => 'nullable|integer|min:1',
            'notice_subject' => 'required|string|max:255',
            'notice_details' => 'required|string',
            'delivery_method'   => 'required|array|min:1',
            'delivery_method.*' => 'in:email,sms,physical',
        ]);

        try {
            // Verify tenancy belongs to the authenticated user
            $tenant = TenantManagement::where('id', $request->tenant_id)
                ->where('managed_by', business_owner_id())
                ->firstOrFail();

            // Create the notice
            $notice = TenantNotice::create([
                'tenant_id'        => $request->tenant_id,
                'issued_by'        => auth()->id(),
                'notice_type'      => $request->notice_type,
                'notice_date'      => $request->notice_date,
                'effective_date'   => $request->effective_date,
                'notice_period'    => $request->notice_period,
                'notice_subject'   => $request->notice_subject,
                'notice_details'   => $request->notice_details,
                'delivery_methods' => $request->delivery_method,
                'delivery_status'  => 'pending',
            ]);

            // Process delivery methods
            $this->processNoticeDelivery($notice, $tenant);
            app(\App\Services\NotificationCenter::class)->noticeGiven($tenant, $notice->notice_type_label ?? 'Notice', $request->effective_date);

            Log::info("Notice created for tenancy {$tenant->tenancy_ref}", [
                'notice_id'   => $notice->id,
                'notice_type' => $notice->notice_type,
                'tenant_id'   => $tenant->id,
                'issued_by'   => auth()->id(),
            ]);

            return response()->json([
                'status'  => 200,
                'message' => 'Notice sent successfully!',
                'notice'  => [
                    'id'               => $notice->id,
                    'notice_type'      => $notice->notice_type_label,
                    'effective_date'   => $notice->effective_date->format('M j, Y'),
                    'delivery_methods' => $notice->delivery_methods_list,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'  => 404,
                'message' => 'Tenancy not found or you do not have permission to give notice to this tenant.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to create tenant notice: ' . $e->getMessage(), [
                'tenant_id' => $request->tenant_id,
                'user_id'   => business_owner_id(),
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 500,
                'message' => 'Failed to send notice. Please try again.',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * End a tenancy.
     *
     * A termination notice (lease_termination or eviction_notice) must have
     * been issued against the tenancy before it can be ended.
     */
    /**
     * Return the termination/eviction notices available for a tenancy.
     * Used to populate the notice selector in the End Tenancy modal.
     */
    public function getTerminationNotices($tenantId)
    {
        $tenantManagement = TenantManagement::where('id', $tenantId)
            ->where('managed_by', business_owner_id())
            ->firstOrFail();

        $notices = TenantNotice::where('tenant_id', $tenantManagement->id)
            ->whereIn('notice_type', ['lease_termination', 'eviction_notice'])
            ->orderBy('notice_date', 'desc')
            ->get()
            ->map(fn ($n) => [
                'id'             => $n->id,
                'label'          => $n->notice_type_label . ' — ' . $n->effective_date->format('M j, Y') . ' (issued ' . $n->notice_date->format('M j, Y') . ')',
                'notice_type'    => $n->notice_type,
                'notice_subject' => $n->notice_subject,
                'effective_date' => $n->effective_date->format('Y-m-d'),
                'notice_date'    => $n->notice_date->format('Y-m-d'),
            ]);

        return response()->json([
            'status'  => 200,
            'notices' => $notices,
        ]);
    }

    /** What leaving on a date would settle (nothing is saved). */
    public function settlementPreview(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'required|integer',
            'termination_date' => 'required|date',
            'damage_charges' => 'nullable|numeric|min:0',
        ]);
        $tenancy = TenantManagement::where('id', $data['tenant_id'])->where('managed_by', business_owner_id())->firstOrFail();
        $figures = app(\App\Services\Rent\TenantAccountService::class)->settleMoveOut($tenancy, $data['termination_date'], (float) ($data['damage_charges'] ?? 0), null, null, [], dryRun: true);
        return response()->json(['status' => 200, 'settlement' => $figures]);
    }

    public function endTenancy(Request $request)
    {
        try {
            $request->validate([
                'tenant_id'               => 'required|exists:tenant_management,id',
                'notice_id'               => 'required_if:notice_compliance,proper_notice|nullable|exists:tenant_notices,id',
                'termination_date'        => 'required|date',
                'termination_reason'      => 'required|string',
                'other_reason'            => 'nullable|required_if:termination_reason,other|string|max:255',
                'notice_compliance'       => 'required|in:proper_notice,emergency_termination',
                'security_deposit_refund' => 'nullable|numeric|min:0',
                'damage_charges'          => 'nullable|numeric|min:0',
                'damage_description'      => 'nullable|string|max:255',
                'refund_method'           => 'nullable|in:cash,bank_transfer,mobile_money,check,card,other',
                'refund_reference'        => 'nullable|string|max:255',
                'property_condition'      => 'required|in:good,fair,damaged',
                'keys_returned'           => 'nullable|boolean',
                'access_cards_returned'   => 'nullable|boolean',
                'forwarding_address'      => 'nullable|string',
                'termination_notes'       => 'nullable|string',
            ], [
                'notice_id.required_if' => 'You must select the notice this termination is based on.',
            ]);

            $user_id = business_owner_id();

            $result = DB::transaction(function () use ($request, $user_id) {
                $tenantManagement = TenantManagement::where('id', $request->tenant_id)
                    ->where('managed_by', $user_id)
                    ->firstOrFail();

                // Guard: validate the selected notice belongs to this tenancy and is the right type
                if ($request->notice_compliance === 'proper_notice') {
                    $notice = TenantNotice::where('id', $request->notice_id)
                        ->where('tenant_id', $tenantManagement->id)
                        ->whereIn('notice_type', ['lease_termination', 'eviction_notice'])
                        ->first();

                    if (!$notice) {
                        throw new \Exception(
                            'The selected notice is invalid or does not belong to this tenancy. Please issue a lease termination or eviction notice first.'
                        );
                    }
                }

                // Guard: already terminated
                if ($tenantManagement->termination()->exists()) {
                    throw new \Exception('This tenancy has already been terminated.');
                }

                // Settlement from the tenant's account (see TenantAccountService::settleMoveOut).
                $date = $request->termination_date;
                $settlement = app(\App\Services\Rent\TenantAccountService::class)->settleMoveOut(
                    $tenantManagement, $date,
                    round(floatval($request->damage_charges ?? 0), 2), $request->damage_description,
                    $request->filled('security_deposit_refund') ? floatval($request->security_deposit_refund) : null,
                    ['payment_method' => $request->refund_method ?: 'bank_transfer', 'transaction_reference' => $request->refund_reference]
                );
                $damageCharges = $settlement['damages'];
                $outstandingRent = $settlement['owed_before_deposit'];
                $securityDepositRefund = $settlement['refund'];
                $netSettlement = round($settlement['refund'] + $settlement['left_to_refund'] - $settlement['still_owed'], 2);
                $held = $settlement['deposit_held'];
                $depositStatus = $held <= 0 ? 'not_applicable'
                    : ($settlement['deposit_applied'] >= $held - 0.001 ? 'no_refund' : ($settlement['deposit_applied'] > 0 ? 'partial_refund' : 'full_refund'));

                $finalReason = $request->termination_reason === 'other'
                    ? $request->other_reason
                    : $request->termination_reason;

                $termination = TenantTermination::create([
                    'tenant_management_id'    => $tenantManagement->id,
                    'termination_date'        => $request->termination_date,
                    'termination_reason'      => $request->termination_reason,
                    'other_reason'            => $request->termination_reason === 'other' ? $request->other_reason : null,
                    'notice_compliance'       => $request->notice_compliance,
                    'security_deposit_status' => $depositStatus,
                    'security_deposit_refund' => $securityDepositRefund,
                    'outstanding_rent'        => $outstandingRent,
                    'damage_charges'          => $damageCharges,
                    'net_settlement'          => $netSettlement,
                    'property_condition'      => $request->property_condition,
                    'keys_returned'           => $request->has('keys_returned'),
                    'access_cards_returned'   => $request->has('access_cards_returned'),
                    'forwarding_address'      => $request->forwarding_address,
                    'termination_notes'       => $request->termination_notes,
                    'terminated_by'           => auth()->id(),
                ]);

                $tenantManagement->update([
                    'status'     => 'Terminated',
                    'expected_move_out_date' => $date,
                    'updated_by' => $user_id,
                ]);
                // The unit and its parking are free again.
                $inventory = app(\App\Services\Properties\ComplexInventoryService::class);
                $inventory->releaseParking($tenantManagement->id);
                $inventory->syncOccupancy($tenantManagement->residential_unit_id, $tenantManagement->commercial_unit_id);
                app(\App\Services\NotificationCenter::class)->tenancyEnded($tenantManagement, $date);

                Log::info('Tenancy terminated', [
                    'tenant_id'        => $tenantManagement->id,
                    'tenancy_ref'      => $tenantManagement->tenancy_ref,
                    'termination_id'   => $termination->id,
                    'reason'           => $finalReason,
                    'termination_date' => $request->termination_date,
                    'terminated_by'    => $user_id,
                    'net_settlement'   => $netSettlement,
                ]);

                return [
                    'tenant_management' => $tenantManagement,
                    'termination'       => $termination,
                    'final_reason'      => $finalReason,
                    'net_settlement'    => $netSettlement,
                ];
            });

            return response()->json([
                'status'  => 200,
                'message' => 'Tenancy ended successfully',
                'data'    => [
                    'tenant_id'               => $result['tenant_management']->id,
                    'termination_id'          => $result['termination']->id,
                    'termination_date'        => $request->termination_date,
                    'reason'                  => $result['final_reason'],
                    'net_settlement'          => $result['net_settlement'],
                    'settlement_type'         => $result['termination']->settlement_type,
                    'formatted_net_settlement' => $result['termination']->formatted_net_settlement,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to end tenancy', [
                'error'     => $e->getMessage(),
                'tenant_id' => $request->tenant_id ?? null,
                'user_id'   => business_owner_id() ?? null,
            ]);

            $knownErrors = [
                'This tenancy has already been terminated.',
                'A lease termination or eviction notice must be issued to this tenant before the tenancy can be ended.',
                'The selected notice is invalid or does not belong to this tenancy. Please issue a lease termination or eviction notice first.',
            ];

            if (in_array($e->getMessage(), $knownErrors)) {
                return response()->json([
                    'status'  => 422,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return response()->json([
                'status'  => 500,
                'message' => 'Failed to end tenancy. Please try again.',
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private delivery helpers
    // -------------------------------------------------------------------------

    private function processNoticeDelivery($notice, $tenant): void
    {
        $successfulDeliveries = [];

        foreach ($notice->delivery_methods as $method) {
            try {
                switch ($method) {
                    case 'email':
                        if ($tenant->primary_email ?? null) {
                            $this->sendNoticeByEmail($notice, $tenant);
                            $successfulDeliveries[] = 'email';
                        }
                        break;

                    case 'sms':
                        if (($tenant->tenant_phone ?? null) || ($tenant->business_phone ?? null)) {
                            $this->sendNoticeBySMS($notice, $tenant);
                            $successfulDeliveries[] = 'sms';
                        }
                        break;

                    case 'physical':
                        $this->generatePhysicalNotice($notice, $tenant);
                        $successfulDeliveries[] = 'physical';
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Failed to deliver notice via {$method}", [
                    'notice_id' => $notice->id,
                    'method'    => $method,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        if (count($successfulDeliveries) > 0) {
            $notice->update([
                'delivery_status' => 'sent',
                'sent_at'         => now(),
            ]);
        }
    }

    private function sendNoticeByEmail($notice, $tenant): void
    {
        // TODO: Mail::to($tenant->primary_email)->send(new TenantNoticeEmail($notice, $tenant));
        Log::info("Email notice would be sent to: {$tenant->primary_email}");
    }

    private function sendNoticeBySMS($notice, $tenant): void
    {
        $phone = $tenant->tenant_phone ?? $tenant->business_phone;
        Log::info("SMS notice would be sent to: {$phone}");
    }

    private function generatePhysicalNotice($notice, $tenant): void
    {
        // TODO: Generate a printable PDF for physical delivery.
        Log::info("Physical notice document would be generated for tenancy: {$tenant->tenancy_ref}");
    }
}
