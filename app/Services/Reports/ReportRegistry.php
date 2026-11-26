<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\Reports\AgentsReport;
use App\Services\Reports\Reports\IncomeExpensesReport;
use App\Services\Reports\Reports\LeasesReport;
use App\Services\Reports\Reports\MaintenanceReport;
use App\Services\Reports\Reports\OccupancyReport;
use App\Services\Reports\Reports\OverviewReport;
use App\Services\Reports\Reports\RentRollReport;
use App\Services\Reports\Reports\SalesReport;
use Illuminate\Support\Collection;

/** The reports Erty offers, in menu order, and which of them a person may open. */
class ReportRegistry
{
    private const REPORTS = [
        OverviewReport::class,
        IncomeExpensesReport::class,
        RentRollReport::class,
        OccupancyReport::class,
        LeasesReport::class,
        AgentsReport::class,
        SalesReport::class,
        MaintenanceReport::class,
    ];

    /** @return Collection<string, Report> keyed by report key */
    public function all(): Collection
    {
        return collect(self::REPORTS)->map(fn ($class) => app($class))->keyBy(fn (Report $r) => $r->key());
    }

    /** @return Collection<string, Report> */
    public function availableTo(User $user): Collection
    {
        return $this->all()->filter(fn (Report $r) => $r->allows($user));
    }

    public function find(string $key): ?Report
    {
        return $this->all()->get($key);
    }
}
