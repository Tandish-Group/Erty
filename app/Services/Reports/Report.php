<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Access\ModuleService;

/** A report: who may open it, and how it is built for a set of filters. */
abstract class Report
{
    /** URL key, e.g. "income". */
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /** Font Awesome icon for the report list. */
    public function icon(): string
    {
        return 'fa-chart-bar';
    }

    /** Any one of these rights ("group.action") opens the report. */
    abstract public function rights(): array;

    /** Optional module that must be switched on. */
    public function module(): ?string
    {
        return null;
    }

    /** Whether the period filter changes the report (false for "as of today" reports). */
    public function usesPeriod(): bool
    {
        return true;
    }

    /** Whether the report offers the agent filter. */
    public function usesAgentFilter(): bool
    {
        return false;
    }

    abstract public function build(ReportFilters $filters, User $user): ReportResult;

    public function allows(User $user): bool
    {
        if ($this->module() && !app(ModuleService::class)->enabled($this->module(), $user)) {
            return false;
        }
        if ($user->isBusinessOwner()) {
            return true;
        }
        foreach ($this->rights() as $right) {
            [$group, $action] = explode('.', $right, 2);
            if ($user->hasBusinessPermission($group, $action)) {
                return true;
            }
        }

        return false;
    }

    /** Whether the user holds a right, for parts of a report that need more than the report itself. */
    protected function can(User $user, string $right): bool
    {
        [$group, $action] = explode('.', $right, 2);

        return $user->isBusinessOwner() || $user->hasBusinessPermission($group, $action);
    }
}
