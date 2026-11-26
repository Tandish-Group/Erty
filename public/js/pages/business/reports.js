// Reports: show the date fields for a custom period, and draw a report's chart (Chart.js).
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-report-period]').forEach(function (select) {
        var custom = select.closest('form').querySelector('[data-report-custom]');
        select.addEventListener('change', function () {
            if (custom) custom.hidden = select.value !== 'custom';
            if (select.value !== 'custom') select.form.submit();
        });
    });

    if (typeof Chart === 'undefined') return;
    var colours = ['#087d84', '#7fb8bc', '#d98c3a', '#5b6fb5', '#b05a7a'];
    document.querySelectorAll('[data-report-chart]').forEach(function (canvas) {
        var chart;
        try { chart = JSON.parse(canvas.getAttribute('data-report-chart')); } catch (e) { return; }
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: chart.labels,
                datasets: chart.series.map(function (s, i) {
                    return { label: s.name, data: s.values, backgroundColor: colours[i % colours.length], borderRadius: 4, maxBarThickness: 28 };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return Number(v).toLocaleString(); } } }, x: { grid: { display: false } } }
            }
        });
    });
});
