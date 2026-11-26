<?php

namespace App\Services\Properties;

use App\Services\NotificationCenter;
use App\Services\Rent\TenantChargesService;

use App\Models\ComplexExpense;
use App\Models\MaintenanceRequest;
use App\Models\TenantManagement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Maintenance requests from report to closure.
 *
 *   open → assigned → in progress → completed → closed   (or cancelled)
 *
 * Completing records what was done, the cost and who pays for it. Closing (by someone who manages the
 * property) books the cost as a draft property expense in the expense workflow (so finance approves and pays
 * it as usual) and, when the tenant is responsible, issues the charge on the tenant's account.
 */
class MaintenanceService
{
    public function __construct(private TenantChargesService $charges) {}

    public function log(array $data, int $ownerId): MaintenanceRequest
    {
        return DB::transaction(function () use ($data, $ownerId) {
            $tenancy = !empty($data['tenancy_id']) ? TenantManagement::forBusiness($ownerId)->find($data['tenancy_id']) : null;
            $request = MaintenanceRequest::create([
                'reference' => $this->reference(),
                'managed_by' => $ownerId,
                'complex_id' => $data['complex_id'],
                'residential_unit_id' => $data['residential_unit_id'] ?? $tenancy?->residential_unit_id,
                'commercial_unit_id' => $data['commercial_unit_id'] ?? $tenancy?->commercial_unit_id,
                'tenancy_id' => $tenancy?->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'other',
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'open',
                'reported_by' => $data['reported_by'] ?? null,
                'reported_via' => $data['reported_via'] ?? null,
                'reported_at' => isset($data['reported_at']) ? Carbon::parse($data['reported_at']) : now(),
                'logged_by' => auth()->id(),
            ]);
            $this->note($request, null, 'open', 'Request logged' . (!empty($data['reported_by']) ? ', reported by ' . $data['reported_by'] : '') . '.');
            app(NotificationCenter::class)->maintenanceLogged($request);
            if (!empty($data['assigned_user_id'])) {
                $this->assign($request, (int) $data['assigned_user_id'], $data['assign_note'] ?? null);
            }
            return $request->fresh();
        });
    }

    public function assign(MaintenanceRequest $request, int $userId, ?string $note = null): MaintenanceRequest
    {
        $this->assertOpen($request);
        $user = User::findOrFail($userId);
        $from = $request->status;
        $request->update(['assigned_user_id' => $user->id, 'assigned_at' => now(), 'status' => $request->status === 'in_progress' ? 'in_progress' : 'assigned']);
        $this->note($request, $from, $request->status, 'Assigned to ' . trim($user->first_name . ' ' . $user->last_name) . ($note ? ': ' . $note : '.'));
        app(NotificationCenter::class)->maintenanceAssigned($request);
        return $request;
    }

    public function start(MaintenanceRequest $request, ?string $note = null): MaintenanceRequest
    {
        $this->assertOpen($request);
        $from = $request->status;
        $request->update(['status' => 'in_progress', 'started_at' => $request->started_at ?? now()]);
        $this->note($request, $from, 'in_progress', $note ?: 'Work started.');
        return $request;
    }

    public function addNote(MaintenanceRequest $request, string $note): MaintenanceRequest
    {
        $this->note($request, null, null, $note);
        return $request;
    }

    public function complete(MaintenanceRequest $request, string $resolution, ?float $cost, ?string $borneBy): MaintenanceRequest
    {
        $this->assertOpen($request);
        if ($borneBy === 'tenant' && !$request->tenancy_id) {
            throw ValidationException::withMessages(['cost_borne_by' => 'Only a request linked to a tenancy can be charged to the tenant.']);
        }
        $from = $request->status;
        $request->update([
            'status' => 'completed', 'completed_at' => now(), 'started_at' => $request->started_at ?? now(),
            'resolution' => $resolution, 'cost' => $cost, 'cost_borne_by' => $cost > 0 ? ($borneBy ?: 'landlord') : null,
        ]);
        $this->note($request, $from, 'completed', 'Completed: ' . $resolution . ($cost > 0 ? ' · cost ' . number_format($cost, 2) . ' (' . ($borneBy === 'tenant' ? 'tenant pays' : 'landlord pays') . ')' : ''));
        app(NotificationCenter::class)->maintenanceCompleted($request);
        return $request;
    }

    /** Sign off a completed request: book the cost and, if the tenant is responsible, charge them. */
    public function close(MaintenanceRequest $request, ?string $note = null): MaintenanceRequest
    {
        if ($request->status !== 'completed') {
            throw ValidationException::withMessages(['status' => 'Only a completed request can be closed.']);
        }
        return DB::transaction(function () use ($request, $note) {
            $request = MaintenanceRequest::lockForUpdate()->findOrFail($request->id);
            if ($request->cost > 0 && !$request->expense_id) {
                $request->expense_id = $this->bookExpense($request)->id;
            }
            if ($request->cost > 0 && $request->cost_borne_by === 'tenant' && $request->tenancy_id && !$request->tenant_invoice_id) {
                $tenancy = TenantManagement::findOrFail($request->tenancy_id);
                $invoice = $this->charges->charge($tenancy, 'adjustment', (float) $request->cost, 'Repair: ' . $request->title . ' (' . $request->reference . ')');
                $request->tenant_invoice_id = $invoice->id;
                DB::afterCommit(fn () => $this->charges->notify($tenancy->fresh(), collect([$invoice->fresh()])));
            }
            $from = $request->status;
            $request->status = 'closed';
            $request->closed_at = now();
            $request->save();
            $this->note($request, $from, 'closed', $note ?: 'Closed.' . ($request->expense_id ? ' Cost booked as a property expense for approval.' : '') . ($request->tenant_invoice_id ? ' Charged to the tenant.' : ''));
            return $request;
        });
    }

    public function cancel(MaintenanceRequest $request, string $reason): MaintenanceRequest
    {
        $this->assertOpen($request);
        $from = $request->status;
        $request->update(['status' => 'cancelled', 'closed_at' => now()]);
        $this->note($request, $from, 'cancelled', 'Cancelled: ' . $reason);
        return $request;
    }

    public function reopen(MaintenanceRequest $request, string $reason): MaintenanceRequest
    {
        if (!in_array($request->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Only a completed or cancelled request can be reopened.']);
        }
        $from = $request->status;
        $request->update(['status' => $request->assigned_user_id ? 'assigned' : 'open', 'completed_at' => null, 'closed_at' => null]);
        $this->note($request, $from, $request->status, 'Reopened: ' . $reason);
        return $request;
    }

    private function bookExpense(MaintenanceRequest $request): ComplexExpense
    {
        $scope = $request->residential_unit_id ? 'residential_unit' : ($request->commercial_unit_id ? 'commercial_unit' : 'complex');
        $type = DB::table('expense_types')->where('expense_type', 'Maintenance and Repairs')->value('id');
        $currency = $request->tenancy?->currency ?: default_currency();
        $expense = ComplexExpense::create([
            'complex_id' => $request->complex_id,
            'scope' => $scope,
            'residential_unit_id' => $request->residential_unit_id,
            'commercial_unit_id' => $request->commercial_unit_id,
            'type' => $type,
            'description' => 'Repair ' . $request->reference . ': ' . $request->title . ($request->cost_borne_by === 'tenant' ? ' (recharged to tenant)' : ''),
            'amount' => $request->cost,
            'currency' => $currency,
            'expense_date' => ($request->completed_at ?? now())->toDateString(),
            'listing_type' => 'complex',
            'transaction_type' => 'expense',
            'user_id' => $request->managed_by,
        ]);
        $workflow = app(PropertyExpenseWorkflow::class);
        if ($workflow->available()) {
            $expense->forceFill(['workflow_status' => 'draft', 'allocation_group' => (string) Str::uuid()])->save();
            $workflow->event($expense, 'created', ['source' => 'maintenance', 'maintenance_request' => $request->reference]);
        }
        return $expense;
    }

    private function note(MaintenanceRequest $request, ?string $from, ?string $to, ?string $note): void
    {
        $request->notes()->create(['user_id' => auth()->id(), 'status_from' => $from, 'status_to' => $to, 'note' => $note]);
    }

    private function assertOpen(MaintenanceRequest $request): void
    {
        if (!$request->isOpen()) {
            throw ValidationException::withMessages(['status' => 'This request is ' . strtolower(MaintenanceRequest::STATUSES[$request->status] ?? $request->status) . '. Reopen it first.']);
        }
    }

    private function reference(): string
    {
        do {
            $ref = 'MR-' . strtoupper(Str::random(6));
        } while (MaintenanceRequest::withoutGlobalScopes()->where('reference', $ref)->exists());
        return $ref;
    }
}
