<?php

namespace App\Services\Tenancy;

use App\Services\Access\PropertyAccess;

use App\Mail\TenancyAssignedMail;
use App\Models\Agent;
use App\Models\Complex;
use App\Models\TenantManagement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Emails agents when a tenancy is onboarded to them (and the property's lead agent when a sub-agent placed it). */
class TenancyNotificationService
{
    /** Send after the surrounding transaction commits; failures are logged, never block onboarding. */
    public function tenancyAssigned(TenantManagement $tenancy): void
    {
        DB::afterCommit(function () use ($tenancy) {
            try {
                $this->send($tenancy->fresh(['tenantProfile', 'residentialUnit', 'commercialUnit']));
            } catch (\Throwable $e) {
                Log::warning('Tenancy agent email failed', ['tenancy_id' => $tenancy->id, 'error' => $e->getMessage()]);
            }
        });
    }

    public function send(TenantManagement $tenancy): array
    {
        $sent = [];
        $agent = $tenancy->assigned_agent_id ? Agent::with('user')->find($tenancy->assigned_agent_id) : null;
        $complex = app(PropertyAccess::class)->unrestricted(fn () => Complex::with('assignedAgent.user')->find($tenancy->complex_id));
        if (!$agent && !$complex?->assignedAgent) {
            return $sent;
        }
        $details = $this->details($tenancy, $complex);

        if ($agent && ($email = $this->emailFor($agent))) {
            Mail::to($email)->send(new TenancyAssignedMail($details + ['agent_name' => $agent->first_name, 'placed_by' => null]));
            $sent[] = $email;
        }
        // The property's lead agent hears about tenancies their sub-agents place.
        $lead = $complex?->assignedAgent;
        if ($lead && $agent && $lead->id !== $agent->id && (int) $agent->reports_to_agent_id === (int) $lead->id && ($email = $this->emailFor($lead))) {
            Mail::to($email)->send(new TenancyAssignedMail($details + ['agent_name' => $lead->first_name, 'placed_by' => $agent->full_name], true));
            $sent[] = $email;
        }
        return $sent;
    }

    private function emailFor(Agent $agent): ?string
    {
        return $agent->user?->email ?: $agent->email ?: null;
    }

    private function details(TenantManagement $tenancy, ?Complex $complex): array
    {
        $profile = $tenancy->tenantProfile;
        $unit = $tenancy->residentialUnit?->unit_number ?? $tenancy->commercialUnit?->unit_number ?? $tenancy->unit_number;
        $owner = \App\Models\User::with('business')->find($tenancy->managed_by);
        return [
            'tenant_name' => $profile?->display_name ?? 'Tenant',
            'tenant_type' => $profile?->tenant_type ?? 'Individual',
            'property' => $complex?->title ?? $tenancy->complex_name ?? 'Property',
            'unit' => $unit ? ($tenancy->commercial_unit_id ? "Space {$unit}" . ($tenancy->occupied_sq_area ? ' (' . number_format((float) $tenancy->occupied_sq_area) . ' sq ft)' : '') : "Unit {$unit}") : null,
            'start_date' => optional($tenancy->move_in_date ? \Carbon\Carbon::parse($tenancy->move_in_date) : null)?->format('j M Y'),
            'rent' => ($tenancy->currency ?: default_currency()) . ' ' . number_format((float) $tenancy->monthly_payment, 2) . ' / month',
            'reference' => $tenancy->tenancy_ref,
            'business' => $owner?->business?->business_name ?? config('app.name'),
            'dashboard_url' => url(route('business.my_agent', [], false)),
        ];
    }
}
