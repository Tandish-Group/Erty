<?php

namespace App\Services\Reports;

/**
 * What a report produces, shown on screen and written to Excel and PDF the same way.
 *
 * - metrics: headline figures [label, value, note?, type (money|number|percent|text), currency?]
 * - sections: tables [title, description?, columns [key => [label, type]], rows, totals (bool)]
 *   Money columns use each row's "currency" (or the business currency); totals are per currency.
 * - chart: optional [title, labels[], series [[name, values[]]]]
 * - notes: short explanations of how figures are counted
 */
final class ReportResult
{
    public array $metrics = [];
    public array $sections = [];
    public ?array $chart = null;
    public array $notes = [];

    public function metric(string $label, mixed $value, string $type = 'money', ?string $note = null, ?string $currency = null): self
    {
        $this->metrics[] = compact('label', 'value', 'type', 'note', 'currency');

        return $this;
    }

    /** One metric per currency for money totals that may span currencies ([currency => amount]). */
    public function moneyMetric(string $label, array $byCurrency, ?string $note = null): self
    {
        if (!$byCurrency) {
            return $this->metric($label, 0, 'money', $note);
        }
        foreach ($byCurrency as $currency => $amount) {
            $this->metric($label . (count($byCurrency) > 1 ? " ({$currency})" : ''), $amount, 'money', $note, $currency);
        }

        return $this;
    }

    /** @param array<string, array{0: string, 1: string}> $columns key => [label, type] */
    public function section(string $title, array $columns, iterable $rows, bool $totals = false, ?string $description = null, ?string $empty = null): self
    {
        $this->sections[] = [
            'title' => $title,
            'description' => $description,
            'columns' => $columns,
            'rows' => collect($rows)->map(fn ($r) => (array) $r)->values()->all(),
            'totals' => $totals,
            'empty' => $empty ?? 'Nothing for this period.',
        ];

        return $this;
    }

    public function chart(string $title, array $labels, array $series): self
    {
        $this->chart = compact('title', 'labels', 'series');

        return $this;
    }

    public function note(string $text): self
    {
        $this->notes[] = $text;

        return $this;
    }

    /** Totals rows for a section, one per currency when it has money columns. */
    public static function totalsFor(array $section): array
    {
        if (!$section['totals'] || !$section['rows']) {
            return [];
        }
        $groups = collect($section['rows'])->groupBy(fn ($r) => $r['currency'] ?? default_currency());
        $sumKeys = collect($section['columns'])->filter(fn ($c) => in_array($c[1], ['money', 'number'], true))->keys();

        return $groups->map(function ($rows, $currency) use ($sumKeys) {
            $total = ['currency' => $currency];
            foreach ($sumKeys as $key) {
                $total[$key] = round($rows->sum(fn ($r) => (float) ($r[$key] ?? 0)), 2);
            }

            return $total;
        })->values()->all();
    }

    /** Text for one cell, as shown on screen and in the PDF. */
    public static function format(mixed $value, string $type, ?string $currency = null): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'money' => money((float) $value, $currency),
            'number' => number_format((float) $value, fmod((float) $value, 1.0) === 0.0 ? 0 : 1),
            'percent' => number_format((float) $value, 1) . '%',
            'date' => $value instanceof \DateTimeInterface ? $value->format('j M Y') : \Carbon\Carbon::parse($value)->format('j M Y'),
            default => (string) $value,
        };
    }
}
