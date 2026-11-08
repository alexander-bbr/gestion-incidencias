<?php
$actionLabels = [
    'INCIDENT_CREATED' => 'Incidencia creada',
    'INCIDENT_UPDATED' => 'Incidencia actualizada',
    'INCIDENT_DELETED' => 'Incidencia eliminada',
    'COMMENT_PUBLIC' => 'Comentario público',
    'COMMENT_INTERNAL' => 'Nota interna',
    'USER_CREATED' => 'Usuario creado',
    'USER_UPDATED' => 'Usuario actualizado',
    'USER_ACTIVATED' => 'Usuario activado',
    'USER_DEACTIVATED' => 'Usuario desactivado',
    'LOGIN_SUCCESS' => 'Inicio de sesión',
    'LOGIN_FAILED' => 'Intento de inicio fallido',
    'LOGOUT' => 'Cierre de sesión',
];
$formatValues = static function (?array $values): string {
    if ($values === null || !$values) {
        return '—';
    }
    $formatted = [];
    foreach ($values as $label => $value) {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        } elseif (is_bool($value)) {
            $value = $value ? 'Sí' : 'No';
        } elseif ($value === null || $value === '') {
            $value = '—';
        }
        $formatted[] = '<span><strong>' . e((string) $label) . ':</strong> ' . e((string) $value) . '</span>';
    }
    return implode('<br>', $formatted);
};
?>
<section class="panel">
    <div class="panel-heading">
        <h2>Registro de movimientos</h2>
    </div>
    <?php if (!$result['items']): ?>
        <div class="empty-state">Todavía no hay movimientos registrados.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="audit-table">
                <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Movimiento</th><th>Elemento</th><th>Detalles del cambio</th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $item): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i:s', strtotime($item['fecha']))) ?></td>
                        <td><?= e($item['actor_name']) ?><small class="table-subtitle"><?= e($item['actor_role'] ? ucfirst(strtolower($item['actor_role'])) : 'Sin sesión') ?></small></td>
                        <td><?= e($actionLabels[$item['accion']] ?? $item['accion']) ?></td>
                        <td><?= e($item['entidad_nombre']) ?><small class="table-subtitle"><?= e(ucfirst($item['entidad_tipo'])) ?><?= $item['entidad_id'] !== null ? ' · #' . e($item['entidad_id']) : '' ?></small></td>
                        <td>
                            <?php if ($item['valores_anteriores'] !== null || $item['valores_nuevos'] !== null): ?>
                                <details class="audit-details">
                                    <summary>Ver valores</summary>
                                    <div class="audit-values">
                                        <div><strong>Antes</strong><p><?= $formatValues($item['valores_anteriores']) ?></p></div>
                                        <div><strong>Después</strong><p><?= $formatValues($item['valores_nuevos']) ?></p></div>
                                    </div>
                                </details>
                            <?php else: ?>
                                <span class="muted">Sin valores detallados</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <span>Mostrando <?= (int) $result['first'] ?>–<?= (int) $result['last'] ?> de <?= (int) $result['total'] ?></span>
            <div>
                <?php if ($result['page'] > 1): ?><a class="button button-secondary" href="<?= e(url('audit', ['page' => $result['page'] - 1])) ?>">← Anterior</a><?php else: ?><span class="button button-disabled">← Anterior</span><?php endif; ?>
                <span class="page-count">Página <?= (int) $result['page'] ?> de <?= (int) $result['pages'] ?></span>
                <?php if ($result['page'] < $result['pages']): ?><a class="button button-secondary" href="<?= e(url('audit', ['page' => $result['page'] + 1])) ?>">Siguiente →</a><?php else: ?><span class="button button-disabled">Siguiente →</span><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
