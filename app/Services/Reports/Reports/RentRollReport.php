<?php

namespace App\Services\Reports\Reports;

use App\Models\TenantManagement;
use App\Models\User;
use App\Services\Rent\TenantReportService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/** Every tenancy's account today: rent, balance, overdue and credit, arrears by age, and deposits. */
class RentRollReport extends Report
{
    public function __construct(private TenantReportService $reports)
    {
    }

    public function key(): string { return 'rent-roll'; }
    public function title(): string { return 'Rent roll & arrears'; }
    public function description(): string { return 'Every tenancy with its rent and account balance today, arrears by age, and deposits held.'; }
    public function icon(): string { return 'fa-file-invoice-dollar'; }
    public function rights(): array { return ['reports.financial']; }
    public function usesPeriod(): bool { return false; }

    public function build(ReportFilters $f, User $user): ReportResult
    {
        $accounts = collect($this->reports->getAccounts($f->complexId));
        $arrears = collect($this->reports->getArrears('2000-01-01', now()->toDateString(), $f->complexId));
        $oldest = $arrears->groupBy('tenancy_ref')->map(fn ($g) => $g->max('days_overdue'));
        $tenancies = TenantManagement::query()->whereIn('id', $accounts->pluck('tenancy_id'))->get(['id', 'monthly_payment', 'payment_frequency', 'unit_number'])->keyBy('id');

        $roll = $accounts->map(fn ($a) => [
            'tenant' => $a['tenant_name'],
            'reference' => $a['tenancy_ref'],
            'property' => $a['property'],
            'status' => $a['status'],
            'currency' => $a['currency'],
            'rent' => (float) ($tenancies[$a['tenancy_id']]->monthly_payment ?? 0),
            'frequency' => $tenancies[$a['tenancy_id']]->payment_frequency ?? null,
            'balance' => $a['outstanding'],
            'overdue' => $a['overdue'],
            'days' => $oldest[$a['tenancy_ref']] ?? null,
            'credit' => $a['credit'],
            'deposit' => $a['deposit_held'],
        ])->sortBy([['overdue', 'desc'], ['property', 'asc']])->values();

        $aging = $this->reports->agingTotals($f->complexId);
        $deposits = collect($this->reports->getDeposits($f->complexId));

        $result = (new ReportResult())
            ->moneyMetric('Overdue now', $roll->groupBy('currency')->map(fn ($g) => round($g->sum('overdue'), 2))->all(), ($n = $roll->where('overdue', '>', 0)->count()) . ' ' . \Illuminate\Support\Str::plural('tenancy', $n) . ' behind')
            ->moneyMetric('Not yet due', $roll->groupBy('currency')->map(fn ($g) => round($g->sum('balance') - $g->sum('overdue'), 2))->all(), 'Billed, due later')
            ->moneyMetric('Credit held', $roll->groupBy('currency')->map(fn ($g) => round($g->sum('credit'), 2))->all(), 'Paid ahead by tenants')
            ->moneyMetric('Deposits held', $deposits->groupBy('currency')->map(fn ($g) => round($g->sum('held'), 2))->all(), ($d = $deposits->where('outstanding', '>', 0)->count()) ? $d . ' not fully paid yet' : 'All paid in full');

        $result->section('Rent roll', [
            'tenant' => ['Tenant', 'text'], 'reference' => ['Tenancy', 'text'], 'property' => ['Property / unit', 'text'], 'status' => ['Status', 'text'],
            'rent' => ['Rent', 'money'], 'frequency' => ['Paid', 'text'], 'balance' => ['Balance', 'money'], 'overdue' => ['Overdue', 'money'],
            'days' => ['Oldest overdue (days)', 'number'], 'credit' => ['Credit', 'money'], 'deposit' => ['Deposit held', 'money'],
        ], $roll, true, 'Current tenancies, and ended ones still owing or holding a deposit, as of today.', 'No tenancies.');

        $result->section('Arrears by age', [
            'bucket' => ['Days overdue', 'text'], 'amount' => ['Amount', 'money'],
        ], collect($aging)->map(fn ($amount, $bucket) => ['bucket' => $bucket, 'amount' => $amount, 'currency' => default_currency()])->values(), true);

        $result->section('Overdue charges', [
            'tenant_name' => ['Tenant', 'text'], 'invoice_number' => ['Invoice', 'text'], 'kind' => ['For', 'text'],
            'complex_name' => ['Property', 'text'], 'unit_number' => ['Unit', 'text'], 'due_date' => ['Due', 'text'],
            'days_overdue' => ['Days', 'number'], 'balance' => ['Balance', 'money'],
        ], $arrears->sortByDesc('days_overdue')->values(), true, null, 'Nothing is overdue.');

        $result->section('Deposits', [
            'tenant_name' => ['Tenant', 'text'], 'tenancy_ref' => ['Tenancy', 'text'], 'property' => ['Property / unit', 'text'], 'status' => ['Status', 'text'],
            'required' => ['Required', 'money'], 'paid' => ['Paid', 'money'], 'applied' => ['Used for charges', 'money'],
            'refunded' => ['Refunded', 'money'], 'held' => ['Held', 'money'], 'outstanding' => ['Still to pay', 'money'],
        ], $deposits, true, null, 'No deposits.');

        $result->note('Balances are as of today and follow the tenant account: payments settle the oldest charge first, and extra payments are held as credit.');
        $result->note('The arrears-by-age table is in ' . default_currency() . '; amounts in other currencies show in the rent roll.');

        return $result;
    }
}
