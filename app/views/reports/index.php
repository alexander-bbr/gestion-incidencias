<?php
$chartData = [];
foreach ($data as $key => $rows) {
    $chartData[$key] = [
        'labels' => array_column($rows, 'label'),
        'values' => array_map(static fn($row) => (float) ($row['total'] ?? $row['hours']), $rows),
        'colors' => array_map(static fn($row) => $row['color'] ?? '#12345A', $rows),
    ];
}
$resolution = array_reverse($data['resolution']);
?>
<div class="report-actions">
    <a class="button button-secondary" href="<?= e(url('reports-export')) ?>">Descargar Excel (.xlsx)</a>
    <button class="button button-primary" type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
</div>
<div class="report-grid">
    <section class="panel chart-panel"><div class="panel-heading"><div><h2>Incidencias por estado</h2><p class="muted">Distribución actual</p></div></div><canvas id="status-chart" aria-label="Gráfico de incidencias por estado"></canvas></section>
    <section class="panel chart-panel"><div class="panel-heading"><div><h2>Incidencias por prioridad</h2><p class="muted">Distribución actual</p></div></div><canvas id="priority-chart" aria-label="Gráfico de incidencias por prioridad"></canvas></section>
    <section class="panel chart-panel"><div class="panel-heading"><div><h2>Top categorías</h2><p class="muted">Categorías con más incidencias</p></div></div><canvas id="category-chart" aria-label="Gráfico de incidencias por categoría"></canvas></section>
    <section class="panel chart-panel"><div class="panel-heading"><div><h2>Tiempo promedio de resolución</h2><p class="muted">Horas desde la creación hasta la resolución, por mes</p></div></div><canvas id="resolution-chart" aria-label="Gráfico de tiempo de resolución"></canvas></section>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const reportData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const resolutionData = <?= json_encode([
    'labels' => array_column($resolution, 'month'),
    'values' => array_map(static fn($row) => round((float) $row['hours'], 1), $resolution),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
new Chart(document.getElementById('status-chart'), {
    type: 'bar',
    data: {labels: reportData.statuses.labels, datasets: [{label: 'Incidencias', data: reportData.statuses.values, backgroundColor: reportData.statuses.colors, borderRadius: 6}]},
    options: {responsive: true, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {precision: 0}}}}
});
new Chart(document.getElementById('priority-chart'), {
    type: 'doughnut',
    data: {labels: reportData.priorities.labels, datasets: [{data: reportData.priorities.values, backgroundColor: reportData.priorities.colors}]},
    options: {responsive: true, plugins: {legend: {position: 'bottom'}}}
});
new Chart(document.getElementById('category-chart'), {
    type: 'bar',
    data: {labels: reportData.categories.labels, datasets: [{label: 'Reportes', data: reportData.categories.values, backgroundColor: '#315f91', borderRadius: 6}]},
    options: {indexAxis: 'y', responsive: true, plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true, ticks: {precision: 0}}}}
});
new Chart(document.getElementById('resolution-chart'), {
    type: 'line',
    data: {labels: resolutionData.labels, datasets: [{label: 'Horas promedio', data: resolutionData.values, borderColor: '#315f91', backgroundColor: 'rgba(49,95,145,.13)', fill: true, tension: .3}]},
    options: {responsive: true, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, title: {display: true, text: 'Horas'}}}}
});
</script>
