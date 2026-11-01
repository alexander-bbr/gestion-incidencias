<?php
$roleLabels = ['USUARIO' => 'Usuario', 'TECNICO' => 'Técnico', 'ADMINISTRADOR' => 'Administrador'];
?>
<section class="welcome-panel">
    <div><p class="eyebrow">PANEL DE CONTROL</p><h2>Hola, <?= e($user['nombre']) ?></h2><p>Resumen de tus incidencias como <?= e($roleLabels[$user['role']] ?? $user['role']) ?>.</p></div>
    <?php if (in_array($user['role'], ['USUARIO', 'ADMINISTRADOR'], true)): ?>
        <a class="button button-primary" href="<?= e(url('incident-create')) ?>">＋ Nueva Incidencia</a>
    <?php endif; ?>
</section>
<div class="stat-grid">
    <article class="stat-card"><span>Total visibles</span><strong><?= (int) $counts['total'] ?></strong></article>
    <article class="stat-card"><span>Abiertas</span><strong><?= (int) $counts['open_count'] ?></strong></article>
    <article class="stat-card"><span>En progreso</span><strong><?= (int) $counts['progress_count'] ?></strong></article>
    <article class="stat-card"><span>Resueltas</span><strong><?= (int) $counts['resolved_count'] ?></strong></article>
</div>
<section class="panel">
    <div class="panel-heading"><div><h2>Incidencias recientes</h2><p class="muted">Acceso rápido a los últimos reportes visibles.</p></div><a class="text-link" href="<?= e(url('incidents')) ?>">Ver todas →</a></div>
    <?php if (!$recent): ?><div class="empty-state">Todavía no hay incidencias para mostrar.</div><?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Código / título</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Fecha</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
        <?php foreach ($recent as $item): ?><tr>
            <td>
                <a class="table-title" href="<?= e(url('incident-view', ['id' => $item['id_incidencia']])) ?>">
                    <?= e($item['codigo']) ?> · <?= e($item['titulo']) ?>
                </a>
            </td>
            <td>
                <span class="badge" style="--badge-color:<?= e($item['status_color']) ?>">
                    <?= e(str_replace('_', ' ', $item['status'])) ?>
                </span>
            </td>
            <td>
                <?= e($item['priority']) ?>
            </td>
            <td>
                <?= e(date('d/m/Y', strtotime($item['fecha_creacion']))) ?>
            </td>
            <td>
                <a class="text-link" href="<?= e(url('incident-view', ['id' => $item['id_incidencia']])) ?>">Abrir</a>
            </td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
