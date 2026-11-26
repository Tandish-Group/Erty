{{-- A report's figures: headline metrics, an optional chart, tables with totals, and notes. $result (ReportResult). --}}
@php use App\Services\Reports\ReportResult; $numeric = fn ($t) => in_array($t, ['money', 'number', 'percent'], true); @endphp

@if($result->metrics)
    <div class="tenant-metrics report-metrics mb-6" style="--report-cols: {{ min(4, count($result->metrics)) }}">
        @foreach($result->metrics as $m)
            <div>
                <span>{{ $m['label'] }}</span>
                <strong>{{ ReportResult::format($m['value'], $m['type'], $m['currency'] ?? null) }}</strong>
                @if($m['note'])<small>{{ $m['note'] }}</small>@endif
            </div>
        @endforeach
    </div>
@endif

@if($result->chart)
    <section class="tenant-panel tenant-panel--padded mb-6">
        <h2 class="report-section-title">{{ $result->chart['title'] }}</h2>
        <div class="report-chart"><canvas data-report-chart='@json($result->chart)' aria-label="{{ $result->chart['title'] }} chart" role="img"></canvas></div>
    </section>
@endif

@foreach($result->sections as $section)
    <section class="tenant-panel tenant-panel--padded mb-6" aria-labelledby="report-section-{{ $loop->index }}">
        <h2 class="report-section-title" id="report-section-{{ $loop->index }}">{{ $section['title'] }} @if($section['rows'])<em>{{ count($section['rows']) }}</em>@endif</h2>
        @if($section['description'])<p class="report-section-desc">{{ $section['description'] }}</p>@endif
        @if(!$section['rows'])
            <div class="report-empty"><i class="fas fa-check-circle" aria-hidden="true"></i>{{ $section['empty'] }}</div>
        @else
            <div class="tenant-table-scroll" role="region" aria-label="{{ $section['title'] }}" tabindex="0">
                <table class="tenant-table report-table">
                    <thead><tr>
                        @foreach($section['columns'] as [$label, $type])<th class="{{ $numeric($type) ? 'text-end' : '' }}">{{ $label }}</th>@endforeach
                    </tr></thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            <tr>
                                @foreach($section['columns'] as $key => [$label, $type])
                                    <td class="{{ $numeric($type) ? 'text-end text-nowrap' : '' }}{{ $loop->first ? ' fw-semibold' : '' }}">{{ ReportResult::format($row[$key] ?? null, $type, $row['currency'] ?? null) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if($totals = ReportResult::totalsFor($section))
                        <tfoot>
                            @foreach($totals as $total)
                                <tr>
                                    @foreach($section['columns'] as $key => [$label, $type])
                                        <td class="{{ in_array($type, ['money', 'number'], true) ? 'text-end text-nowrap' : '' }}">{{ $loop->first ? (count($totals) > 1 ? 'Total ' . $total['currency'] : 'Total') : (in_array($type, ['money', 'number'], true) ? ReportResult::format($total[$key] ?? 0, $type, $total['currency']) : '') }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </section>
@endforeach

@if($result->notes)
    <aside class="report-notes">
        <h2>How these figures are counted</h2>
        <ul>@foreach($result->notes as $note)<li>{{ $note }}</li>@endforeach</ul>
    </aside>
@endif
