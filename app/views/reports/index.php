<?php
$chartData = [];
foreach (['statuses', 'priorities', 'categories'] as $key) {
    $chartData[$key] = [
        'labels' => array_column($reports[$key], 'label'),
        'values' => array_map(static fn(array $row): int => (int) $row['total'], $reports[$key]),
        'colors' => array_map(static fn(array $row): string => $row['color'] ?? '#315f91', $reports[$key]),
    ];
}
$resolution = $reports['resolution'];
$resolutionData = [
    'labels' => array_column($resolution, 'month'),
    'values' => array_map(static fn(array $row): float => round((float) $row['hours'], 1), $resolution),
];
$dateRangeLabel = $dateFrom && $dateTo
    ? $dateFrom . ' — ' . $dateTo
    : ($dateFrom ? 'Desde ' . $dateFrom : ($dateTo ? 'Hasta ' . $dateTo : 'Todo el periodo'));
$exportParams = array_filter(
    ['from' => $dateFrom, 'to' => $dateTo],
    static fn(?string $value): bool => $value !== null && $value !== ''
);
?>
<section class="panel report-filter-panel">
    <div class="panel-heading">
        <div><h2>Periodo del reporte</h2><p class="muted">Filtra las incidencias por fecha de creación. Deja una fecha vacía para no limitar ese extremo.</p></div>
    </div>
    <form method="get" action="<?= e(url('reports')) ?>" class="report-filter-form">
        <label>Fecha desde<input type="date" name="from" value="<?= e($dateFrom) ?>"></label>
        <label>Fecha hasta<input type="date" name="to" value="<?= e($dateTo) ?>"></label>
        <div class="filter-actions"><button class="button button-primary" type="submit">Aplicar</button><a class="button button-secondary" href="<?= e(url('reports')) ?>">Limpiar</a></div>
    </form>
</section>

<?php if (!empty($error)): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($chartsAvailable): ?>
    <div class="report-actions">
        <a class="button button-secondary excel-button" href="<?= e(url('reports-export', $exportParams)) ?>">Descargar Registros (Excel)</a>
        <button class="button button-primary pdf-button" id="download-charts-pdf" type="button">Descargar Gráficos (PDF)</button>
    </div>
    <p class="report-period">Periodo aplicado: <strong><?= e($dateRangeLabel) ?></strong> · <?= count($reports['records']) ?> registros</p>
    <div class="report-grid">
        <section class="panel chart-panel"><div class="panel-heading"><div><h2>Incidencias por estado</h2><p class="muted">Distribución del periodo</p></div></div><canvas id="status-chart" aria-label="Gráfico de incidencias por estado"></canvas></section>
        <section class="panel chart-panel"><div class="panel-heading"><div><h2>Incidencias por prioridad</h2><p class="muted">Distribución del periodo</p></div></div><canvas id="priority-chart" aria-label="Gráfico de incidencias por prioridad"></canvas></section>
        <section class="panel chart-panel"><div class="panel-heading"><div><h2>Top categorías</h2><p class="muted">Categorías más reportadas del periodo</p></div></div><canvas id="category-chart" aria-label="Gráfico de incidencias por categoría"></canvas></section>
        <section class="panel chart-panel"><div class="panel-heading"><div><h2>Tiempo promedio de resolución</h2><p class="muted">Horas desde la creación hasta la resolución, por mes</p></div></div><canvas id="resolution-chart" aria-label="Gráfico de tiempo de resolución"></canvas></section>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
    const reportData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const resolutionData = <?= json_encode($resolutionData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const reportPeriod = <?= json_encode($dateRangeLabel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const chartInstances = [
        new Chart(document.getElementById('status-chart'), {
            type: 'bar',
            data: {labels: reportData.statuses.labels, datasets: [{label: 'Incidencias', data: reportData.statuses.values, backgroundColor: reportData.statuses.colors, borderRadius: 6}]},
            options: {responsive: true, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {precision: 0}}}}
        }),
        new Chart(document.getElementById('priority-chart'), {
            type: 'doughnut',
            data: {labels: reportData.priorities.labels, datasets: [{data: reportData.priorities.values, backgroundColor: reportData.priorities.colors}]},
            options: {responsive: true, plugins: {legend: {position: 'bottom'}}}
        }),
        new Chart(document.getElementById('category-chart'), {
            type: 'bar',
            data: {labels: reportData.categories.labels, datasets: [{label: 'Reportes', data: reportData.categories.values, backgroundColor: '#315f91', borderRadius: 6}]},
            options: {indexAxis: 'y', responsive: true, plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true, ticks: {precision: 0}}}}
        }),
        new Chart(document.getElementById('resolution-chart'), {
            type: 'line',
            data: {labels: resolutionData.labels, datasets: [{label: 'Horas promedio', data: resolutionData.values, borderColor: '#315f91', backgroundColor: 'rgba(49,95,145,.13)', fill: true, tension: .3}]},
            options: {responsive: true, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, title: {display: true, text: 'Horas'}}}}
        })
    ];
    document.getElementById('download-charts-pdf').addEventListener('click', () => {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            alert('No se pudo cargar el generador de PDF. Revisa tu conexión a Internet e inténtalo de nuevo.');
            return;
        }
        const pdf = new window.jspdf.jsPDF({orientation: 'landscape', unit: 'mm', format: 'a4'});
        const titles = ['Incidencias por estado', 'Incidencias por prioridad', 'Top categorías reportadas', 'Tiempo promedio de resolución'];
        pdf.setFontSize(16);
        pdf.text('Reportes y Métricas — Clínica UGA', 14, 14);
        pdf.setFontSize(10);
        pdf.text('Periodo: ' + reportPeriod, 14, 21);
        chartInstances.forEach((chart, index) => {
            const column = index % 2;
            const row = Math.floor(index / 2);
            const x = column === 0 ? 14 : 153;
            const y = row === 0 ? 31 : 120;
            pdf.setFontSize(11);
            pdf.text(titles[index], x, y);
            pdf.addImage(chart.toBase64Image(), 'PNG', x, y + 3, 130, 76);
        });
        pdf.save('graficos-incidencias-<?= date('Y-m-d') ?>.pdf');
    });
    </script>
<?php endif; ?>
