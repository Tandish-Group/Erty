"use strict";

var reportCharts = {};

$(document).ready(function () {
    initCharts();

    // Period filter change
    $('#reportPeriod').on('change', function () {
        refreshReportData();
    });

    // Refresh button
    $('#btnRefreshReport').on('click', function () {
        refreshReportData();
    });
});

/**
 * Initialize Chart.js charts with server-rendered data.
 */
function initCharts() {
    initLeadTrendChart();
    initLeadSourcesChart();
}

/**
 * Lead Trend — bar + line combo chart (12 months).
 */
function initLeadTrendChart() {
    var canvas = document.getElementById('chartLeadTrend');
    if (!canvas) return;

    // Read data from the page — we'll embed it via a global or parse from DOM
    // For initial load, we use the blade-rendered data via a refresh call
    // For now, draw empty and let the AJAX fill it
    reportCharts.trend = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Created',
                    backgroundColor: 'rgba(62, 151, 255, 0.7)',
                    borderColor: '#3E97FF',
                    borderWidth: 1,
                    borderRadius: 4,
                    data: [],
                    order: 2,
                },
                {
                    label: 'Converted',
                    type: 'line',
                    borderColor: '#50CD89',
                    backgroundColor: 'rgba(80, 205, 137, 0.1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#50CD89',
                    fill: true,
                    tension: 0.3,
                    data: [],
                    order: 1,
                },
                {
                    label: 'Lost',
                    type: 'line',
                    borderColor: '#F1416C',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointRadius: 3,
                    pointBackgroundColor: '#F1416C',
                    fill: false,
                    tension: 0.3,
                    data: [],
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { usePointStyle: true, padding: 15 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(0,0,0,0.04)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // Trigger initial data load
    refreshReportData();
}

/**
 * Lead Sources — doughnut chart.
 */
function initLeadSourcesChart() {
    var canvas = document.getElementById('chartLeadSources');
    if (!canvas) return;

    reportCharts.sources = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [],
                borderWidth: 0,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { display: false }
            }
        }
    });
}

/**
 * Refresh all report data via AJAX based on selected period.
 */
function refreshReportData() {
    var period = $('#reportPeriod').val();
    var $btn = $('#btnRefreshReport');
    $btn.prop('disabled', true).html(
        '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Loading...'
    );

    $.ajax({
        url: '/customer_relationship/reports/data',
        type: 'GET',
        data: { period: period },
        success: function (response) {
            if (response.status === 200) {
                updateSummaryKPIs(response.summary);
                updateTrendChart(response.trend);
                updateSourcesChart(response.sources);
                updateConversionFunnel(response.conversion);
                updateTaskMetrics(response.tasks);
                updateAgentTable(response.agents);
                updatePriority(response.priority);
            }
        },
        error: function () {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load report data. Please try again.',
                confirmButtonText: 'OK'
            });
        },
        complete: function () {
            $btn.prop('disabled', false).html(
                '<i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i> Refresh'
            );
        }
    });
}

/**
 * Update the summary KPI cards.
 */
function updateSummaryKPIs(data) {
    $('#kpiTotalLeads').text(data.total_leads);
    $('#kpiConvertedLeads').text(data.converted_leads);
    $('#kpiConversionRate').text(data.conversion_rate + '%');
    $('#kpiLostLeads').text(data.lost_leads);
    $('#kpiAvgDays').text(data.avg_conversion_days);
}

/**
 * Update the lead trend chart with new monthly data.
 */
function updateTrendChart(trend) {
    if (!reportCharts.trend) return;

    var labels = [];
    var created = [];
    var converted = [];
    var lost = [];

    trend.forEach(function (m) {
        labels.push(m.month_short);
        created.push(m.created);
        converted.push(m.converted);
        lost.push(m.lost);
    });

    reportCharts.trend.data.labels = labels;
    reportCharts.trend.data.datasets[0].data = created;
    reportCharts.trend.data.datasets[1].data = converted;
    reportCharts.trend.data.datasets[2].data = lost;
    reportCharts.trend.update();
}

/**
 * Update lead sources doughnut chart and list.
 */
function updateSourcesChart(sources) {
    if (!reportCharts.sources) return;

    var labels = [];
    var data = [];
    var colors = [];

    sources.forEach(function (s) {
        labels.push(s.label);
        data.push(s.total);
        colors.push(s.color);
    });

    reportCharts.sources.data.labels = labels;
    reportCharts.sources.data.datasets[0].data = data;
    reportCharts.sources.data.datasets[0].backgroundColor = colors;
    reportCharts.sources.update();

    // Update the list below the chart
    var $list = $('#sourceBreakdownList');
    $list.empty();
    sources.forEach(function (s) {
        $list.append(
            '<div class="d-flex align-items-center mb-3">' +
                '<div class="bullet w-10px h-10px rounded-circle me-3" style="background-color: ' + s.color + '"></div>' +
                '<span class="text-gray-700 fw-semibold fs-7 flex-grow-1">' + s.label + '</span>' +
                '<span class="fw-bold fs-7 me-3">' + s.total + '</span>' +
                '<span class="badge badge-light-success fs-9">' + s.conversion_rate + '%</span>' +
            '</div>'
        );
    });
}

/**
 * Update the conversion funnel bars.
 */
function updateConversionFunnel(data) {
    var total = data.total || 1;

    data.funnel.forEach(function (phase) {
        var $el = $('#funnel_' + phase.phase);
        if ($el.length) {
            $el.find('.funnel-count').text(phase.count);
            var pct = Math.round((phase.count / total) * 100);
            $el.find('.rounded.h-6px').first().next().css('width', pct + '%');
            // Update inner bar width
            $el.find('[class*="bg-"]').not('.bg-light').css('width', pct + '%');
        }
    });

    $('#funnelConverted').text(data.converted);
}

/**
 * Update task metrics section.
 */
function updateTaskMetrics(data) {
    $('#taskCompletionRate').text(data.completion_rate + '%');
    $('#taskOverdueRate').text(data.overdue_rate + '%');
    $('#taskSlaBreachRate').text(data.sla_breach_rate + '%');
    $('#taskAvgHours').text(data.avg_completion_hours);
    $('#taskCompleted').text(data.completed);
    $('#taskPending').text(data.pending);
    $('#taskInProgress').text(data.in_progress);
    $('#taskOverdue').text(data.overdue);
    $('#taskBlocked').text(data.blocked);
}

/**
 * Update agent performance table.
 */
function updateAgentTable(agents) {
    var $body = $('#agentTableBody');
    $body.empty();

    if (!agents || agents.length === 0) {
        $body.html('<tr><td colspan="10" class="text-center text-muted py-5">No active agents found</td></tr>');
        return;
    }

    agents.forEach(function (a) {
        var typeColor = a.type === 'employee' ? 'primary' : (a.type === 'agency' ? 'info' : 'warning');
        var convColor = a.conversion_rate >= 50 ? 'success' : (a.conversion_rate >= 25 ? 'warning' : 'danger');
        var taskColor = a.task_completion_rate >= 80 ? 'success' : (a.task_completion_rate >= 50 ? 'warning' : 'danger');

        $body.append(
            '<tr>' +
                '<td>' +
                    '<div class="d-flex align-items-center">' +
                        '<div class="symbol symbol-35px symbol-circle me-3">' +
                            '<span class="symbol-label bg-light-primary text-primary fw-bold">' + a.name.charAt(0) + '</span>' +
                        '</div>' +
                        '<span class="text-gray-800 fw-bold">' + a.name + '</span>' +
                    '</div>' +
                '</td>' +
                '<td class="text-center"><span class="badge badge-light-' + typeColor + '">' + capitalize(a.type) + '</span></td>' +
                '<td class="text-center fw-bold">' + a.total_leads + '</td>' +
                '<td class="text-center">' + a.active_leads + '</td>' +
                '<td class="text-center text-success fw-bold">' + a.converted_leads + '</td>' +
                '<td class="text-center"><span class="badge badge-light-' + convColor + '">' + a.conversion_rate + '%</span></td>' +
                '<td class="text-center">' + a.tasks_completed + '/' + a.total_tasks + '</td>' +
                '<td class="text-center text-danger">' + a.tasks_overdue + '</td>' +
                '<td class="text-center"><span class="badge badge-light-' + taskColor + '">' + a.task_completion_rate + '%</span></td>' +
                '<td class="text-center text-muted">' + a.avg_completion_hours + 'h</td>' +
            '</tr>'
        );
    });
}

/**
 * Update priority section.
 */
function updatePriority(data) {
    var $container = $('#priorityContainer');
    $container.empty();

    data.forEach(function (p) {
        $container.append(
            '<div class="d-flex flex-column mb-5">' +
                '<div class="d-flex justify-content-between mb-2">' +
                    '<span class="badge badge-light-' + p.color + ' fs-7 fw-bold">' + p.label + '</span>' +
                    '<span class="fw-bold fs-7">' + p.total + '</span>' +
                '</div>' +
                '<div class="d-flex align-items-center">' +
                    '<div class="flex-grow-1 me-3">' +
                        '<div class="h-5px w-100 bg-light rounded">' +
                            '<div class="bg-' + p.color + ' rounded h-5px" style="width: ' + p.conversion_rate + '%;"></div>' +
                        '</div>' +
                    '</div>' +
                    '<span class="text-muted fs-8">' + p.conversion_rate + '% conv.</span>' +
                '</div>' +
            '</div>'
        );
    });
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
