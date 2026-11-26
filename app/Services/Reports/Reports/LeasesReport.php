<?php

namespace App\Services\Reports\Reports;

use App\Models\TenantManagement;
use App\Models\TenantMovement;
use App\Models\User;
use App\Services\Reports\PropertyFigures;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Carbon\Carbon;

/** Leases ending soon, tenancies on notice, and transfers between units. */
class LeasesReport extends Report
{
    public function __construct(private PropertyFigures $figures)
    {
    }

    public function key(): string { return 'leases'; }
    public function title(): string { return 'Leases & notices'; }
    public function description(): string { return 'Leases ending in the next 90 days, tenancies on notice with their move-out dates, and transfers between units.'; }
    public function icon(): string { return 'fa-file-signature'; }
    public function rights(): array { return ['reports.occupancy']; }

    public function build(ReportFilters $f, User $user): ReportResult
    {
        $properties = $this->figures->properties($f->complexId)->keys();
        $today = Carbon::today();
        $tenancies = fn () => TenantManagement::query()->where('managed_by', business_owner_id())->whereIn('complex_id', $properties)->with('tenantProfile');
        $name = fn (TenantManagement $t) => $t->tenantProfile?->tenant_type === 'Business' ? ($t->tenantProfile->business_name ?: $t->tenantProfile->tenant_name) : $t->tenantProfile?->tenant_name;
        $place = fn ($t) => trim($t->complex_name . ($t->unit_number ? ' · ' . $t->unit_number : ''));

        $ending = $tenancies()->where('status', 'Active')
            ->whereRaw('COALESCE(lease_end_date, agreement_end_date) BETWEEN ? AND ?', [$today->toDateString(), $today->copy()->addDays(90)->toDateString()])
            ->get()
            ->map(function (TenantManagement $t) use ($name, $place, $today) {
                $end = Carbon::parse($t->lease_end_date ?? $t->agreement_end_date);
                $days = (int) $today->diffInDays($end);

                return [
                    'ends' => $end, 'days' => $days, 'window' => $days <= 30 ? 'Within 30 days' : ($days <= 60 ? '31–60 days' : '61–90 days'),
                    'tenant' => $name($t), 'tenancy' => $t->tenancy_ref, 'property' => $place($t),
                    'rent' => (float) $t->monthly_payment, 'currency' => $t->currency ?: default_currency(),
                ];
            })->sortBy('days')->values();

        $notice = $tenancies()->where('status', 'On Notice')->orderBy('expected_move_out_date')->get()
            ->map(fn (TenantManagement $t) => [
                'given' => $t->notice_given_date, 'moves_out' => $t->expected_move_out_date,
                'tenant' => $name($t), 'tenancy' => $t->tenancy_ref, 'property' => $place($t),
                'rent' => (float) $t->monthly_payment, 'currency' => $t->currency ?: default_currency(),
            ]);

        $noticesGiven = $tenancies()->whereBetween('notice_given_date', [$f->from, $f->to])->count();

        $transfers = TenantMovement::query()->where('managed_by', business_owner_id())
            ->where(fn ($q) => $q->whereIn('old_complex_id', $properties)->orWhereIn('new_complex_id', $properties))
            ->whereBetween('transfer_date', [$f->from, $f->to])
            ->with('tenantProfile')->orderBy('transfer_date')->get()
            ->map(fn ($m) => [
                'date' => $m->transfer_date, 'reference' => $m->ref,
                'tenant' => $m->tenantProfile?->tenant_type === 'Business' ? ($m->tenantProfile->business_name ?: $m->tenantProfile->tenant_name) : $m->tenantProfile?->tenant_name,
                'from' => $m->old_unit_number, 'to' => $m->new_unit_number, 'status' => ucfirst((string) $m->status),
                'old_rent' => (float) $m->old_rent, 'new_rent' => (float) $m->new_rent, 'currency' => $m->currency ?: default_currency(),
            ]);

        $result = (new ReportResult())
            ->metric('Leases ending in 30 days', $ending->where('days', '<=', 30)->count(), 'number', $ending->count() . ' in the next 90 days')
            ->metric('On notice now', $notice->count(), 'number', $notice->first() ? 'Next move-out ' . Carbon::parse($notice->first()['moves_out'])->format('j M') : 'Nobody is leaving')
            ->metric('Notices given', $noticesGiven, 'number', 'In the period')
            ->metric('Transfers', $transfers->count(), 'number', 'Between units, in the period');

        $result->section('Leases ending in the next 90 days', [
            'ends' => ['Lease ends', 'date'], 'days' => ['Days left', 'number'], 'window' => ['When', 'text'], 'tenant' => ['Tenant', 'text'],
            'tenancy' => ['Tenancy', 'text'], 'property' => ['Property / unit', 'text'], 'rent' => ['Rent', 'money'],
        ], $ending, false, 'Active tenancies whose lease or agreement ends soon: time to renew or plan the next tenant.', 'No leases end in the next 90 days.');

        $result->section('On notice', [
            'given' => ['Notice given', 'date'], 'moves_out' => ['Moves out', 'date'], 'tenant' => ['Tenant', 'text'],
            'tenancy' => ['Tenancy', 'text'], 'property' => ['Property / unit', 'text'], 'rent' => ['Rent', 'money'],
        ], $notice, true, null, 'Nobody is on notice.');

        $result->section('Transfers between units', [
            'date' => ['Moving date', 'date'], 'reference' => ['Transfer', 'text'], 'tenant' => ['Tenant', 'text'], 'from' => ['From unit', 'text'],
            'to' => ['To unit', 'text'], 'status' => ['Status', 'text'], 'old_rent' => ['Old rent', 'money'], 'new_rent' => ['New rent', 'money'],
        ], $transfers, false, null, 'No transfers in the period.');

        $result->note('Leases ending and tenancies on notice are as of today; notices given and transfers follow the period.');

        return $result;
    }
}
