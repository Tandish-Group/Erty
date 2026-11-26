<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\LeadTask;
use Illuminate\Database\Eloquent\Builder;

/**
 * What "open", "needs attention" and "overdue" mean for leads, in one place: the CRM dashboard and the
 * Leads & pipeline report both use these, so their figures always agree.
 * Queries go through the Lead and LeadTask models, so a team member limited to their properties sees only those.
 */
class LeadPipeline
{
    /** A lead not contacted for this many days (or in one phase this long) needs attention. */
    public const STALE_DAYS = 14;

    public const CLOSED_STATUSES = ['closed_won', 'closed_lost'];

    public const PRIORITIES = ['hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold'];

    /** The business's leads. */
    public static function leads(): Builder
    {
        return Lead::query()->where('leads.user_id', business_owner_id());
    }

    /** Leads still being worked: not closed and not yet a tenant or buyer. */
    public static function open(?Builder $leads = null): Builder
    {
        return ($leads ?? self::leads())->whereNotIn('leads.status', self::CLOSED_STATUSES)
            ->whereNull('leads.converted_tenant_id')->whereNull('leads.converted_sale_id');
    }

    /** Open leads not contacted for STALE_DAYS days, or past their follow-up date. */
    public static function needingAttention(Builder $open): Builder
    {
        $cutoff = now()->subDays(self::STALE_DAYS);

        return $open->where(fn ($q) => $q->whereNull('leads.last_contacted_at')->where('leads.created_at', '<', $cutoff)
            ->orWhere('leads.last_contacted_at', '<', $cutoff)
            ->orWhere('leads.follow_up_date', '<', now()->startOfDay()));
    }

    /** Follow-up tasks on the business's leads. */
    public static function tasks(): Builder
    {
        return LeadTask::query()->whereHas('lead', fn ($l) => $l->where('user_id', business_owner_id()));
    }

    public static function priorityLabel(?string $priority): string
    {
        return self::PRIORITIES[$priority] ?? 'Not set';
    }
}
