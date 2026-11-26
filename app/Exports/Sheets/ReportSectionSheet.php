<?php

namespace App\Exports\Sheets;

use App\Services\Reports\ReportResult;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** One report table: a currency column when it has money, numbers and dates as real cells, and totals. */
class ReportSectionSheet implements FromArray, WithTitle, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    private array $columns;
    private bool $hasMoney;

    public function __construct(private string $name, private array $section)
    {
        $this->columns = $section['columns'];
        $this->hasMoney = collect($this->columns)->contains(fn ($c) => $c[1] === 'money');
    }

    public function title(): string
    {
        return $this->name;
    }

    public function array(): array
    {
        $header = array_map(fn ($c) => $c[0], array_values($this->columns));
        if ($this->hasMoney) {
            $header[] = 'Currency';
        }
        $rows = [$header];
        foreach ($this->section['rows'] as $row) {
            $rows[] = $this->line($row);
        }
        foreach (ReportResult::totalsFor($this->section) as $total) {
            $line = $this->line($total);
            $line[0] = 'Total';
            $rows[] = $line;
        }

        return $rows;
    }

    private function line(array $row): array
    {
        $out = [];
        foreach ($this->columns as $key => [$label, $type]) {
            $value = $row[$key] ?? null;
            $out[] = match (true) {
                $value === null || $value === '' => null,
                in_array($type, ['money', 'number', 'percent'], true) && is_numeric($value) => $type === 'percent' ? (float) $value / 100 : (float) $value,
                $type === 'date' => Date::PHPToExcel(\Carbon\Carbon::parse($value)),
                default => (string) $value,
            };
        }
        if ($this->hasMoney) {
            $out[] = $row['currency'] ?? default_currency();
        }

        return $out;
    }

    public function columnFormats(): array
    {
        $formats = [];
        $i = 1;
        foreach ($this->columns as [$label, $type]) {
            $letter = Coordinate::stringFromColumnIndex($i++);
            $format = match ($type) {
                'money' => '#,##0.00',
                'number' => '#,##0.##',
                'percent' => '0.0%',
                'date' => 'd mmm yyyy',
                default => null,
            };
            if ($format) {
                $formats[$letter] = $format;
            }
        }

        return $formats;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [1 => ['font' => ['bold' => true]]];
        $totals = count(ReportResult::totalsFor($this->section));
        $last = count($this->section['rows']) + 1;
        for ($r = $last + 1; $r <= $last + $totals; $r++) {
            $styles[$r] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}
