<?php

namespace App\Exports;

use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Any report as a workbook: a summary sheet, then one sheet per table. Amounts are numbers, not text. */
class ReportExport implements Export, WithMultipleSheets
{
    public function __construct(private Report $report, private ReportResult $result, private ReportFilters $filters)
    {
    }

    public function sheets(): array
    {
        $sheets = [new Sheets\ReportSummarySheet($this->report, $this->result, $this->filters)];
        $used = ['Summary' => true];
        foreach ($this->result->sections as $section) {
            // Sheet names: at most 31 characters, unique, without []:*?/\
            $name = mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $section['title']), 0, 28);
            $base = $name;
            for ($i = 2; isset($used[$name]); $i++) {
                $name = $base . ' ' . $i;
            }
            $used[$name] = true;
            $sheets[] = new Sheets\ReportSectionSheet($name, $section);
        }

        return $sheets;
    }
}
