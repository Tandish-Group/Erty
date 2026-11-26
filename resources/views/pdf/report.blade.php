@php use App\Services\Reports\ReportResult; @endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $report->title() }}</title>
<style>
    @page { margin: 28px 30px; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5px; color: #2f3044; }
    h1 { font-size: 17px; margin: 0 0 3px; }
    h2 { font-size: 12px; margin: 18px 0 2px; }
    .muted { color: #7e8299; }
    .desc { color: #7e8299; margin: 0 0 4px; }
    table { width: 100%; border-collapse: collapse; }
    .head td { border: none; padding: 0; vertical-align: top; }
    .metrics td { border: 1px solid #e4e6ef; padding: 7px 9px; width: 25%; vertical-align: top; }
    .metrics strong { display: block; font-size: 13px; margin: 2px 0; }
    .data { margin-top: 4px; }
    .data th { text-align: left; font-size: 8.5px; color: #7e8299; border-bottom: 1px solid #d9dce6; padding: 5px 4px; }
    .data td { border-bottom: 1px solid #f0f1f5; padding: 4px; vertical-align: top; }
    .data tr.total td { font-weight: bold; border-top: 1px solid #d9dce6; background: #f7f8fa; }
    .r { text-align: right; white-space: nowrap; }
    .notes { margin-top: 16px; color: #7e8299; }
    .notes li { margin-bottom: 3px; }
</style>
</head>
<body>
    <table class="head"><tr>
        <td><h1>{{ $report->title() }}</h1><div class="muted">{{ $business }}</div></td>
        <td class="r">
            {{ $report->usesPeriod() ? $filters->label() : 'As of ' . now()->format('j M Y') }}<br>
            {{ $filters->propertyName() ?? 'All properties' }}<br>
            <span class="muted">Created {{ now()->format('j M Y H:i') }}</span>
        </td>
    </tr></table>

    @if($result->metrics)
        <table class="metrics" style="margin-top:12px;">
            @foreach(array_chunk($result->metrics, 4) as $chunk)
                <tr>
                    @foreach($chunk as $m)
                        <td><span class="muted">{{ $m['label'] }}</span><strong>{{ ReportResult::format($m['value'], $m['type'], $m['currency'] ?? null) }}</strong>@if($m['note'])<span class="muted">{{ $m['note'] }}</span>@endif</td>
                    @endforeach
                    @for($i = count($chunk); $i < 4; $i++)<td style="border:none;"></td>@endfor
                </tr>
            @endforeach
        </table>
    @endif

    @foreach($result->sections as $section)
        <h2>{{ $section['title'] }}</h2>
        @if($section['description'])<p class="desc">{{ $section['description'] }}</p>@endif
        @if(!$section['rows'])
            <p class="muted">{{ $section['empty'] }}</p>
        @else
            <table class="data">
                <thead><tr>
                    @foreach($section['columns'] as [$label, $type])<th class="{{ in_array($type, ['money', 'number', 'percent'], true) ? 'r' : '' }}">{{ $label }}</th>@endforeach
                </tr></thead>
                <tbody>
                    @foreach($section['rows'] as $row)
                        <tr>
                            @foreach($section['columns'] as $key => [$label, $type])
                                <td class="{{ in_array($type, ['money', 'number', 'percent'], true) ? 'r' : '' }}">{{ ReportResult::format($row[$key] ?? null, $type, $row['currency'] ?? null) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    @foreach(ReportResult::totalsFor($section) as $total)
                        <tr class="total">
                            @foreach($section['columns'] as $key => [$label, $type])
                                <td class="{{ in_array($type, ['money', 'number'], true) ? 'r' : '' }}">{{ $loop->first ? 'Total' : (in_array($type, ['money', 'number'], true) ? ReportResult::format($total[$key] ?? 0, $type, $total['currency']) : '') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @if($result->notes)
        <ul class="notes">@foreach($result->notes as $note)<li>{{ $note }}</li>@endforeach</ul>
    @endif
</body>
</html>
