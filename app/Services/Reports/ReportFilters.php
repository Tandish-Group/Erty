<?php

namespace App\Services\Reports;

use App\Models\Complex;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** What a report is run for: a period, and optionally one property and one agent. */
final class ReportFilters
{
    public const PERIODS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_quarter' => 'This quarter',
        'last_quarter' => 'Last quarter',
        'this_year' => 'This year',
        'last_year' => 'Last year',
        'last_12_months' => 'Last 12 months',
        'custom' => 'Custom dates',
    ];

    public function __construct(
        public readonly string $period,
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $complexId = null,
        public readonly ?int $agentId = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $complexId = $request->integer('property') ?: null;
        // Only a property this person can see (the assigned-properties scope applies to Complex).
        if ($complexId && !Complex::forBusiness()->whereKey($complexId)->exists()) {
            $complexId = null;
        }

        return self::make((string) $request->query('period', 'this_month'), $request->query('from'), $request->query('to'), $complexId, $request->integer('agent') ?: null);
    }

    public static function make(string $period = 'this_month', ?string $from = null, ?string $to = null, ?int $complexId = null, ?int $agentId = null): self
    {
        $now = Carbon::now();
        [$start, $end, $key] = match ($period) {
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), $period],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter(), $period],
            'last_quarter' => [$now->copy()->subQuarterNoOverflow()->startOfQuarter(), $now->copy()->subQuarterNoOverflow()->endOfQuarter(), $period],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), $period],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear(), $period],
            'last_12_months' => [$now->copy()->subMonthsNoOverflow(11)->startOfMonth(), $now->copy()->endOfMonth(), $period],
            'custom' => self::custom($from, $to) ?? [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this_month'],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this_month'],
        };

        return new self($key, $start->toDateString(), $end->toDateString(), $complexId, $agentId);
    }

    private static function custom(?string $from, ?string $to): ?array
    {
        try {
            $a = Carbon::parse((string) $from)->startOfDay();
            $b = Carbon::parse((string) $to)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
        if (!$from || !$to) {
            return null;
        }
        [$a, $b] = $a->lte($b) ? [$a, $b] : [$b, $a];
        // Keep reports to at most five years at a time.
        if ($a->diffInDays($b) > 366 * 5) {
            $a = $b->copy()->subYears(5);
        }

        return [$a, $b, 'custom'];
    }

    public function label(): string
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        return $this->period === 'custom' ? $from->format('j M Y') . ' – ' . $to->format('j M Y') : self::PERIODS[$this->period] . ' (' . $from->format('j M Y') . ' – ' . $to->format('j M Y') . ')';
    }

    public function propertyName(): ?string
    {
        return $this->complexId ? Complex::forBusiness()->whereKey($this->complexId)->value('title') : null;
    }

    /** Query string for links and downloads that keep the same filters. */
    public function query(array $extra = []): array
    {
        return array_filter([
            'period' => $this->period,
            'from' => $this->period === 'custom' ? $this->from : null,
            'to' => $this->period === 'custom' ? $this->to : null,
            'property' => $this->complexId,
            'agent' => $this->agentId,
        ] + $extra, fn ($v) => $v !== null && $v !== '');
    }
}
