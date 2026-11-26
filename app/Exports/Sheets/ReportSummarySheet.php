<?php

namespace App\Exports\Sheets;

use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** First sheet: what the report is, for which business, period and property, and its headline figures. */
class ReportSummarySheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private Report $report, private ReportResult $result, private ReportFilters $filters)
    {
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $rows = [
            [$this->report->title()],
            ['Business', auth()->user()?->business?->business_name ?? config('app.name')],
            ['Period', $this->report->usesPeriod() ? $this->filters->label() : 'As of ' . now()->format('j M Y')],
            ['Property', $this->filters->propertyName() ?? 'All properties'],
            ['Created', now()->format('j M Y H:i')],
            [],
            ['Figure', 'Value', 'Currency', 'Note'],
        ];
        foreach ($this->result->metrics as $m) {
            $value = in_array($m['type'], ['money', 'number', 'percent'], true) && is_numeric($m['value']) ? (float) $m['value'] : $m['value'];
            $rows[] = [$m['label'], $value, $m['type'] === 'money' ? ($m['currency'] ?? default_currency()) : ($m['type'] === 'percent' ? '%' : ''), $m['note'] ?? ''];
        }
        if ($this->result->notes) {
            $rows[] = [];
            foreach ($this->result->notes as $note) {
                $rows[] = [$note];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 14]], 7 => ['font' => ['bold' => true]]];
    }
}
