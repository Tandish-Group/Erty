<?php

namespace App\Services;

use App\Services\Access\BusinessPermissionService;
use App\Services\Access\PropertyAccess;

use App\Models\Agent;
use App\Models\AgentCommissionPayout;
use App\Models\Complex;
use App\Models\Lead;
use App\Models\MaintenanceRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\TenantManagement;
use App\Models\TenantPayment;
use App\Models\User;
use App\Notifications\ErtyNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Who hears about what, in the bell panel.
 *
 * - Agents hear about their own work: leads given to them, units and properties they are given or lose,
 *   tenancies and payments on units they answer for, repairs assigned to them, commission paid to them
 *   and their overdue tasks.
 * - Team members hear about events their role covers (e.g. payments for people who handle payments,
 *   repairs for people who manage maintenance), and only on properties and units they can see.
 * - The account owner sees everything their business does.
 * The person who did something is never notified about it, suspended accounts get nothing, and a
 * notification never stops the action that caused it (failures are logged). Inside a database
 * transaction, notifications go out only once it commits.
 */
class NotificationCenter
{
    public function __construct(private BusinessPermissionService $permissions) {}

    // ─── Events ─────────────────────────────────────────────────────────────

    public function leadAssigned(Lead $lead): void
    {
        $this->safely(function () use ($lead) {
            $agentUser = $this->agentUser($lead->assigned_agent_id);
            $this->deliver(collect([$agentUser]), [
                'kind' => 'lead.assigned', 'icon' => 'person-plus', 'tone' => 'primary',
                'title' => 'New lead: ' . $lead->full_name,
                'body' => 'Assigned to you' . ($lead->complex ? ' for ' . $lead->complex->title : '') . '. Your first tasks are ready.',
                'url' => $this->leadUrl($lead),
            ]);
        });
    }

    public function leadNeedsAgent(Lead $lead): void
    {
        $this->safely(function () use ($lead) {
            $this->deliver($this->staff((int) $lead->user_id, 'lead_management', 'edit', $lead->complex_id), [
                'kind' => 'lead.unassigned', 'icon' => 'person-exclamation', 'tone' => 'warning',
                'title' => 'Lead waiting for an agent',
                'body' => $lead->full_name . ($lead->complex ? ' · ' . $lead->complex->title : '') . ' has no agent yet.',
                'url' => $this->leadUrl($lead),
            ]);
        });
    }

    public function leadConverted(Lead $lead, TenantManagement $tenancy): void
    {
        $this->safely(function () use ($lead, $tenancy) {
            $complex = $tenancy->complex_id ? Complex::withoutGlobalScopes()->find($tenancy->complex_id) : null;
            $people = collect([$this->ownerUser((int) $tenancy->managed_by), $this->agentUser($lead->assigned_agent_id)])
                ->merge($complex && !$complex->isCompanyManaged() ? [$this->agentUser($complex->assigned_agent_id)] : []);
            $this->deliver($people, [
                'kind' => 'lead.converted', 'icon' => 'house-check', 'tone' => 'success',
                'title' => $lead->full_name . ' is now a tenant',
                'body' => trim(($complex?->title ?? '') . ($tenancy->unit_number ? ' · unit ' . $tenancy->unit_number : ''), ' ·') . '. Tenancy ' . $tenancy->tenancy_ref . '.',
                'url' => $this->tenancyUrl($tenancy),
            ]);
        });
    }

    /** Units given to (or taken from) agents on a property. */
    public function unitsAssigned(Complex $complex, string $kind, Collection $units, ?int $agentId, array $previousAgentIds): void
    {
        $this->safely(function () use ($complex, $kind, $units, $agentId, $previousAgentIds) {
            $numbers = $units->pluck('unit_number')->filter()->sort(SORT_NATURAL)->values();
            $list = $numbers->take(6)->implode(', ') . ($numbers->count() > 6 ? ' and ' . ($numbers->count() - 6) . ' more' : '');
            $url = route('complex.show', $complex->slug) . '#' . $kind . '_units';
            if ($agentId) {
                $this->deliver(collect([$this->agentUser($agentId)]), [
                    'kind' => 'units.assigned', 'icon' => 'door-open', 'tone' => 'primary',
                    'title' => Str::plural('Unit', $numbers->count()) . ' assigned to you at ' . $complex->title,
                    'body' => $list . '. You are their sub-agent: their tenants, bills and commission are now yours.',
                    'url' => $url,
                ]);
            }
            foreach (collect($previousAgentIds)->filter()->unique()->reject(fn ($id) => (int) $id === (int) $agentId) as $oldId) {
                $this->deliver(collect([$this->agentUser((int) $oldId)]), [
                    'kind' => 'units.removed', 'icon' => 'door-closed', 'tone' => 'muted',
                    'title' => 'Units moved off your list at ' . $complex->title,
                    'body' => 'You are no longer the agent for ' . $list . '. Commission already earned stays yours.',
                    'url' => route('complex.show', $complex->slug),
                ]);
            }
        });
    }

    /** A property became company-managed, or got a (new) primary manager. */
    public function managementChanged(Complex $complex, ?int $oldAgentId, ?int $newAgentId): void
    {
        $this->safely(function () use ($complex, $oldAgentId, $newAgentId) {
            $url = route('complex.show', $complex->slug) . '#assignments';
            if ($newAgentId && $newAgentId !== $oldAgentId) {
                $this->deliver(collect([$this->agentUser($newAgentId)]), [
                    'kind' => 'property.primary', 'icon' => 'building-check', 'tone' => 'primary',
                    'title' => 'You now manage ' . $complex->title,
                    'body' => 'You are its primary property manager: you oversee every unit and can delegate units to agents.',
                    'url' => $url,
                ]);
            }
            if ($oldAgentId && $oldAgentId !== $newAgentId) {
                $this->deliver(collect([$this->agentUser($oldAgentId)]), [
                    'kind' => 'property.primary_removed', 'icon' => 'building-dash', 'tone' => 'muted',
                    'title' => 'You no longer manage ' . $complex->title,
                    'body' => $newAgentId ? 'Another primary manager has taken it over.' : 'It is now managed by the company.',
                    'url' => route('complex.show', $complex->slug),
                ]);
            }
        });
    }

    public function maintenanceLogged(MaintenanceRequest $request): void
    {
        $this->safely(function () use ($request) {
            [$kind, $unitId] = $this->unitOf($request);
            $managers = $this->staff((int) $request->managed_by, null, null, $request->complex_id, $kind, $unitId)
                ->filter(fn (User $u) => $this->permissions->can($u, 'maintenance_management', 'manage'));
            $urgent = in_array($request->priority, ['urgent', 'high'], true);
            $this->deliver($managers->push($this->responsibleFor($request->complex_id, $kind, $unitId, $request->tenancy_id)), [
                'kind' => 'maintenance.logged', 'icon' => 'tools', 'tone' => $urgent ? 'danger' : 'warning',
                'title' => ($urgent ? ucfirst($request->priority) . ' repair: ' : 'Repair logged: ') . $request->title,
                'body' => $this->placeOf($request->complex_id, $request) . ' · ' . $request->reference,
                'url' => route('maintenance.show', $request->reference),
            ]);
        });
    }

    public function maintenanceAssigned(MaintenanceRequest $request): void
    {
        $this->safely(function () use ($request) {
            $user = $request->assigned_user_id ? User::find($request->assigned_user_id) : null;
            $this->deliver(collect([$user]), [
                'kind' => 'maintenance.assigned', 'icon' => 'wrench', 'tone' => 'primary',
                'title' => 'Repair assigned to you: ' . $request->title,
                'body' => $this->placeOf($request->complex_id, $request) . ' · ' . ucfirst($request->priority) . ' priority · ' . $request->reference,
                'url' => route('maintenance.show', $request->reference),
            ]);
        });
    }

    public function maintenanceCompleted(MaintenanceRequest $request): void
    {
        $this->safely(function () use ($request) {
            $logger = $request->logged_by ? User::find($request->logged_by) : null;
            [$kind, $unitId] = $this->unitOf($request);
            $this->deliver(collect([$logger, $this->responsibleFor($request->complex_id, $kind, $unitId, $request->tenancy_id)]), [
                'kind' => 'maintenance.completed', 'icon' => 'check2-circle', 'tone' => 'success',
                'title' => 'Repair done: ' . $request->title,
                'body' => $this->placeOf($request->complex_id, $request) . ' · ' . $request->reference,
                'url' => route('maintenance.show', $request->reference),
            ]);
        });
    }

    public function paymentRecorded(TenantPayment $payment, TenantManagement $tenancy): void
    {
        $this->safely(function () use ($payment, $tenancy) {
            [$kind, $unitId] = $this->unitOfTenancy($tenancy);
            $people = $this->staff((int) $tenancy->managed_by, 'tenant_management', 'payments', $tenancy->complex_id, $kind, $unitId)
                ->push($this->responsibleFor($tenancy->complex_id, $kind, $unitId, $tenancy->id));
            $pending = $payment->payment_status === TenantPayment::PENDING;
            $this->deliver($people, [
                'kind' => 'payment.recorded', 'icon' => 'cash-coin', 'tone' => $pending ? 'warning' : 'success',
                'title' => ($pending ? 'Payment waiting to clear: ' : 'Payment received: ') . 'KES ' . number_format((float) $payment->amount_paid, 0),
                'body' => $this->tenantName($tenancy) . ' · ' . $this->placeOf($tenancy->complex_id, $tenancy),
                'url' => $this->tenancyUrl($tenancy) . '#billing',
            ]);
        });
    }

    public function paymentReversed(TenantPayment $payment, TenantManagement $tenancy, string $reason): void
    {
        $this->safely(function () use ($payment, $tenancy, $reason) {
            [$kind, $unitId] = $this->unitOfTenancy($tenancy);
            $people = $this->staff((int) $tenancy->managed_by, 'tenant_management', 'payments', $tenancy->complex_id, $kind, $unitId)
                ->push($this->responsibleFor($tenancy->complex_id, $kind, $unitId, $tenancy->id));
            $this->deliver($people, [
                'kind' => 'payment.reversed', 'icon' => 'arrow-counterclockwise', 'tone' => 'danger',
                'title' => 'Payment reversed: KES ' . number_format((float) $payment->amount_paid, 0),
                'body' => $this->tenantName($tenancy) . ' · ' . $reason . '. Any commission on it is reversed too.',
                'url' => $this->tenancyUrl($tenancy) . '#billing',
            ]);
        });
    }

    public function noticeGiven(TenantManagement $tenancy, string $noticeLabel, ?string $effectiveDate): void
    {
        $this->safely(function () use ($tenancy, $noticeLabel, $effectiveDate) {
            [$kind, $unitId] = $this->unitOfTenancy($tenancy);
            $people = $this->staff((int) $tenancy->managed_by, 'tenant_management', 'notices', $tenancy->complex_id, $kind, $unitId)
                ->push($this->responsibleFor($tenancy->complex_id, $kind, $unitId, $tenancy->id));
            $this->deliver($people, [
                'kind' => 'tenancy.notice', 'icon' => 'envelope-exclamation', 'tone' => 'warning',
                'title' => $noticeLabel . ': ' . $this->tenantName($tenancy),
                'body' => $this->placeOf($tenancy->complex_id, $tenancy) . ($effectiveDate ? ' · effective ' . \Carbon\Carbon::parse($effectiveDate)->format('j M Y') : ''),
                'url' => $this->tenancyUrl($tenancy),
            ]);
        });
    }

    public function tenancyEnded(TenantManagement $tenancy, ?string $endDate): void
    {
        $this->safely(function () use ($tenancy, $endDate) {
            [$kind, $unitId] = $this->unitOfTenancy($tenancy);
            $people = $this->staff((int) $tenancy->managed_by, 'tenant_management', 'notices', $tenancy->complex_id, $kind, $unitId)
                ->push($this->responsibleFor($tenancy->complex_id, $kind, $unitId, $tenancy->id));
            $this->deliver($people, [
                'kind' => 'tenancy.ended', 'icon' => 'box-arrow-right', 'tone' => 'warning',
                'title' => 'Tenancy ending: ' . $this->tenantName($tenancy),
                'body' => $this->placeOf($tenancy->complex_id, $tenancy) . ($endDate ? ' · move-out ' . \Carbon\Carbon::parse($endDate)->format('j M Y') : '') . '. The unit will be free to let.',
                'url' => $this->tenancyUrl($tenancy),
            ]);
        });
    }

    public function commissionPaid(AgentCommissionPayout $payout, Agent $agent): void
    {
        $this->safely(function () use ($payout, $agent) {
            $this->deliver(collect([$this->agentUser($agent->id)]), [
                'kind' => 'commission.paid', 'icon' => 'wallet2', 'tone' => 'success',
                'title' => 'Commission paid: ' . money($payout->total, $payout->currency, 0),
                'body' => 'For ' . \Carbon\Carbon::parse($payout->period_from)->format('j M') . ' – ' . \Carbon\Carbon::parse($payout->period_to)->format('j M Y') . '. Your statement shows every payment it came from.',
                'url' => route('business.my_agent'),
            ]);
        });
    }

    /** Commission entries that need someone with agent edit rights to approve them. */
    public function commissionAwaitingApproval(int $businessId, int $count, float $amount, string $why): void
    {
        if ($count < 1) {
            return;
        }
        $this->safely(function () use ($businessId, $count, $amount, $why) {
            $this->deliver($this->staff($businessId, 'agent_management', 'edit'), [
                'kind' => 'commission.pending', 'icon' => 'hourglass-split', 'tone' => 'warning',
                'title' => $count . ' commission ' . Str::plural('entry', $count) . ' to approve',
                'body' => 'KES ' . number_format($amount, 0) . ' · ' . $why,
                'url' => route('tenant_management.agent_commissions', ['tab' => 'periods']),
            ]);
        });
    }

    // ─── House sales ────────────────────────────────────────────────────────

    /** People who follow a sale: its agent, the house's responsible agent, and staff with the sales right ($action) who can see the house. */
    private function saleFollowers(\App\Models\PropertySale $sale, string $action = 'create'): Collection
    {
        return $this->staff((int) $sale->business_id, 'sales_management', $action, $sale->complex_id, 'residential', $sale->residential_unit_id)
            ->push($this->agentUser($sale->agent_id))
            ->push($this->responsibleFor($sale->complex_id, 'residential', $sale->residential_unit_id));
    }

    private function salePlace(\App\Models\PropertySale $sale): string
    {
        $title = DB::table('complexes')->where('id', $sale->complex_id)->value('title');
        $number = DB::table('complex_residential_units')->where('id', $sale->residential_unit_id)->value('unit_number');
        return trim(($title ?? 'Property') . ($number ? ' · house ' . $number : ''));
    }

    private function money(\App\Models\PropertySale $sale, float $amount): string
    {
        return money($amount, $sale->currency, 0);
    }

    public function saleStarted(\App\Models\PropertySale $sale): void
    {
        $this->safely(fn () => $this->deliver($this->saleFollowers($sale), [
            'kind' => 'sale.started', 'icon' => 'house-check', 'tone' => 'primary',
            'title' => 'House under offer: ' . $this->salePlace($sale),
            'body' => $sale->buyer_name . ' · ' . $this->money($sale, $sale->price) . ' · ' . $sale->reference,
            'url' => route('sales.show', $sale->reference),
        ]));
    }

    public function salePaymentRecorded(\App\Models\PropertySale $sale, \App\Models\PropertySalePayment $payment): void
    {
        $this->safely(fn () => $this->deliver($this->saleFollowers($sale, 'payments'), [
            'kind' => 'sale.payment', 'icon' => 'cash-coin', 'tone' => 'success',
            'title' => 'Sale payment received: ' . $this->money($sale, $payment->amount),
            'body' => $sale->buyer_name . ' · ' . $this->salePlace($sale) . ' · receipt ' . $payment->receipt_number,
            'url' => route('sales.show', $sale->reference),
        ]));
    }

    public function salePaymentReversed(\App\Models\PropertySale $sale, \App\Models\PropertySalePayment $payment, string $reason): void
    {
        $this->safely(fn () => $this->deliver($this->saleFollowers($sale, 'payments'), [
            'kind' => 'sale.payment_reversed', 'icon' => 'arrow-counterclockwise', 'tone' => 'danger',
            'title' => 'Sale payment reversed: ' . $this->money($sale, $payment->amount),
            'body' => $sale->buyer_name . ' · ' . $reason . '. Any sales commission on it is reversed too.',
            'url' => route('sales.show', $sale->reference),
        ]));
    }

    public function saleCompleted(\App\Models\PropertySale $sale): void
    {
        $this->safely(fn () => $this->deliver($this->saleFollowers($sale), [
            'kind' => 'sale.completed', 'icon' => 'trophy', 'tone' => 'success',
            'title' => 'House sold: ' . $this->salePlace($sale),
            'body' => $sale->buyer_name . ' · ' . $this->money($sale, $sale->price) . ' · ' . $sale->reference,
            'url' => route('sales.show', $sale->reference),
        ]));
    }

    public function saleCancelled(\App\Models\PropertySale $sale): void
    {
        $this->safely(fn () => $this->deliver($this->saleFollowers($sale), [
            'kind' => 'sale.cancelled', 'icon' => 'x-octagon', 'tone' => 'warning',
            'title' => 'Sale cancelled: ' . $this->salePlace($sale),
            'body' => $sale->buyer_name . ' · ' . ($sale->cancel_reason ?: 'No reason given') . '. The house is back on the market.',
            'url' => route('sales.show', $sale->reference),
        ]));
    }

    /** An instalment past its due date, once per instalment: the sale's agent and the people who take sale payments. */
    public function instalmentOverdue(\App\Models\PropertySaleInstalment $instalment): bool
    {
        $sent = false;
        $this->safely(function () use ($instalment, &$sent) {
            $sale = \App\Models\PropertySale::withoutGlobalScopes()->find($instalment->sale_id);
            if (!$sale) return;
            $people = $this->staff((int) $sale->business_id, 'sales_management', 'payments', $sale->complex_id, 'residential', $sale->residential_unit_id)
                ->push($this->agentUser($sale->agent_id));
            $sent = $this->deliver($people, [
                'kind' => 'sale.instalment_overdue', 'icon' => 'alarm', 'tone' => 'danger', 'key' => 'instalment-overdue:' . $instalment->id,
                'title' => 'Overdue: ' . $instalment->label . ' · ' . $this->money($sale, $instalment->outstanding()),
                'body' => $sale->buyer_name . ' · ' . $this->salePlace($sale) . ' · was due ' . $instalment->due_date->format('j M Y') . '.',
                'url' => route('sales.show', $sale->reference) . '#plan',
            ], false) > 0;
        });
        return $sent;
    }

    /** Reminders for tasks past their due time, once per task (see the tasks:notify-overdue command). */
    public function taskOverdue(\App\Models\LeadTask $task): bool
    {
        $sent = false;
        $this->safely(function () use ($task, &$sent) {
            $lead = $task->lead;
            if (!$lead) {
                return;
            }
            $sent = $this->deliver(collect([$this->agentUser($task->assigned_agent_id)]), [
                'kind' => 'task.overdue', 'icon' => 'alarm', 'tone' => 'danger', 'key' => 'task-overdue:' . $task->id,
                'title' => 'Overdue: ' . $task->title,
                'body' => $lead->full_name . ' · was due ' . $task->sla_breach_at->format('j M, H:i') . '.',
                'url' => '/agents/lead-tasks/' . Crypt::encrypt($lead->id) . '#task-' . $task->id,
            ], false) > 0;
        });
        return $sent;
    }

    // ─── Support tickets ────────────────────────────────────────────────────

    /** A new ticket: everyone who answers support tickets hears about it. */
    public function supportTicketOpened(SupportTicket $ticket): void
    {
        $this->safely(function () use ($ticket) {
            $this->deliver($this->supportAgents(), [
                'kind' => 'support.opened', 'icon' => 'life-preserver', 'tone' => 'warning',
                'title' => 'New support ticket ' . $ticket->reference,
                'body' => $this->personName($ticket->user) . ' · ' . $ticket->topicLabel() . ': ' . $ticket->subject(90),
                'url' => route('platform.support.show', $ticket->reference),
            ]);
        });
    }

    /**
     * A reply or status change. Support replies go to the person who posted the ticket; the poster's
     * replies go to whoever the ticket is assigned to, or to everyone who answers support when nobody is.
     */
    public function supportTicketUpdated(SupportTicket $ticket, SupportTicketMessage $message): void
    {
        $this->safely(function () use ($ticket, $message) {
            $changed = $message->status_to && $message->status_to !== $message->status_from;
            if ($message->is_staff) {
                $title = $message->body
                    ? 'Reply on your support ticket ' . $ticket->reference
                    : 'Support ticket ' . $ticket->reference . ' is now ' . strtolower($ticket->statusLabel());
                $this->deliver(collect([$ticket->user]), [
                    'kind' => 'support.reply', 'icon' => 'chat-left-text', 'tone' => $ticket->status === 'resolved' ? 'success' : 'primary',
                    'title' => $title,
                    'body' => $message->body ? Str::limit(trim(html_entity_decode(strip_tags($message->body))), 120) : $ticket->subject(90),
                    'url' => route('support.tickets.show', $ticket->reference),
                ]);
                return;
            }
            $people = $ticket->assigned_to ? collect([$ticket->assignee]) : $this->supportAgents();
            $this->deliver($people, [
                'kind' => 'support.user_reply', 'icon' => 'chat-left-dots', 'tone' => 'warning',
                'title' => $this->personName($ticket->user) . ' replied on ' . $ticket->reference . ($changed && $message->status_to === 'open' ? ' (reopened)' : ''),
                'body' => Str::limit(trim(html_entity_decode(strip_tags((string) $message->body))), 120),
                'url' => route('platform.support.show', $ticket->reference),
            ]);
        });
    }

    /** People who answer support tickets: platform administrators and support agents. */
    public function supportAgents(): Collection
    {
        return User::role(['admin', 'support'])->where('is_suspended', false)->get();
    }

    private function personName(?User $user): string
    {
        return $user ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: (string) $user->email) : 'Someone';
    }

    // ─── Recipients ─────────────────────────────────────────────────────────

    /**
     * The owner and active team members of a business who have the permission (when given) and can see the
     * property (and, for people limited to some units there, the unit).
     */
    public function staff(int $ownerId, ?string $group = null, ?string $action = null, $complexId = null, ?string $unitKind = null, $unitId = null): Collection
    {
        if (!$ownerId) {
            return collect();
        }
        return User::with('businessRole')
            ->where(fn ($q) => $q->where('id', $ownerId)->orWhere(fn ($m) => $m->where('is_sub_account', true)->where('main_account_user_id', $ownerId)))
            ->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended'))
            ->get()
            ->filter(fn (User $user) => !$group || $this->permissions->can($user, $group, $action ?? 'view'))
            ->filter(fn (User $user) => $this->canSee($user, $complexId, $unitKind, $unitId))
            ->values();
    }

    public function canSee(User $user, $complexId, ?string $unitKind = null, $unitId = null): bool
    {
        if (!$complexId || !$user->is_sub_account || !$user->limitedToAssignedProperties()) {
            return true;
        }
        $access = new PropertyAccess;
        $access->activate($user);
        return $unitKind && $unitId ? $access->allowsUnit((int) $complexId, $unitKind, (int) $unitId) : $access->allows((int) $complexId);
    }

    /** The team member behind an agent profile, if the agent signs in and is active. */
    public function agentUser($agentId): ?User
    {
        if (!$agentId) {
            return null;
        }
        $agent = Agent::withoutGlobalScopes()->find($agentId);
        if (!$agent || !$agent->user_id || (isset($agent->is_active) && !$agent->is_active)) {
            return null;
        }
        return User::with('businessRole')->find($agent->user_id);
    }

    public function ownerUser(int $ownerId): ?User
    {
        return $ownerId ? User::find($ownerId) : null;
    }

    /** Who answers for a unit right now: its agent, else the tenancy's agent, else the property's primary manager. */
    public function responsibleFor($complexId, ?string $kind, $unitId, $tenancyId = null): ?User
    {
        $agentId = null;
        if ($kind && $unitId) {
            $agentId = DB::table($kind === 'commercial' ? 'commercial_units' : 'complex_residential_units')->where('id', $unitId)->value('assigned_agent_id');
        }
        if (!$agentId && $tenancyId) {
            $agentId = DB::table('tenant_management')->where('id', $tenancyId)->value('assigned_agent_id');
        }
        if (!$agentId && $complexId) {
            $complex = DB::table('complexes')->where('id', $complexId)->first(['assigned_agent_id', 'management_type']);
            $agentId = $complex && ($complex->management_type ?? 'individual') !== 'company' ? $complex->assigned_agent_id : null;
        }
        return $this->agentUser($agentId);
    }

    // ─── Delivery ───────────────────────────────────────────────────────────

    /** Send to each person once, never to the person acting, never to suspended accounts. Returns how many got it. */
    public function deliver(Collection $people, array $payload, bool $skipActor = true): int
    {
        $actorId = $skipActor ? auth()->id() : null;
        $recipients = $people->filter()->unique('id')
            ->reject(fn (User $u) => $actorId && (int) $u->id === (int) $actorId)
            ->reject(fn (User $u) => (bool) $u->is_suspended)
            ->reject(fn (User $u) => !empty($payload['key']) && $u->notifications()->whereLike('data', '%"key":"' . $payload['key'] . '"%')->exists())
            ->values();
        if ($recipients->isEmpty()) {
            return 0;
        }
        $payload += ['actor' => $actorId ? trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')) : null];
        $send = fn () => $this->safely(fn () => $recipients->each(fn (User $u) => $u->notify(new ErtyNotification($payload))));
        DB::transactionLevel() > 0 ? DB::afterCommit($send) : $send();
        return $recipients->count();
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('Notification not sent: ' . $e->getMessage(), ['exception' => get_class($e)]);
        }
    }

    // ─── Wording helpers ────────────────────────────────────────────────────

    private function unitOf(MaintenanceRequest $request): array
    {
        return $request->commercial_unit_id ? ['commercial', $request->commercial_unit_id] : ($request->residential_unit_id ? ['residential', $request->residential_unit_id] : [null, null]);
    }

    private function unitOfTenancy(TenantManagement $tenancy): array
    {
        return $tenancy->commercial_unit_id ? ['commercial', $tenancy->commercial_unit_id] : ($tenancy->residential_unit_id ? ['residential', $tenancy->residential_unit_id] : [null, null]);
    }

    private function placeOf($complexId, $record): string
    {
        $title = $complexId ? DB::table('complexes')->where('id', $complexId)->value('title') : null;
        $unit = $record->unit_number ?? null;
        if (!$unit && ($record->residential_unit_id ?? null)) {
            $unit = DB::table('complex_residential_units')->where('id', $record->residential_unit_id)->value('unit_number');
        } elseif (!$unit && ($record->commercial_unit_id ?? null)) {
            $unit = DB::table('commercial_units')->where('id', $record->commercial_unit_id)->value('unit_number');
        }
        return trim(($title ?? 'Property') . ($unit ? ' · unit ' . $unit : ''));
    }

    private function tenantName(TenantManagement $tenancy): string
    {
        $profile = $tenancy->tenantProfile ?? null;
        return $profile ? (($profile->tenant_type ?? null) === 'Business' ? ($profile->business_name ?: $profile->tenant_name) : $profile->tenant_name) : 'Tenant';
    }

    private function leadUrl(Lead $lead): string
    {
        return route('lead_management.show', Crypt::encrypt($lead->id));
    }

    private function tenancyUrl(TenantManagement $tenancy): string
    {
        return url('/tenant_management/tenancy/' . $tenancy->tenancy_ref . '/view');
    }
}
