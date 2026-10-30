<section class="panel">
    <div class="panel-heading"><div><h2>Listado de incidencias</h2><p class="muted">Filtra y consulta los reportes disponibles para tu perfil.</p></div>
        <?php if (in_array($user['role'], ['USUARIO', 'ADMINISTRADOR'], true)): ?><a class="button button-primary" href="<?= e(url('incident-create')) ?>">＋ Nueva incidencia</a><?php endif; ?>
    </div>
    <form method="get" class="filter-form">
        <input type="hidden" name="route" value="incidents">
        <label class="filter-search">Buscar por título<input name="q" value="<?= e($filters['q']) ?>" placeholder="Ej. Impresora sin conexión"></label>
        <label>Estado<select name="status"><option value="">Todos</option><?php foreach ($catalogs['statuses'] as $option): ?><option value="<?= (int) $option['id_estado'] ?>" <?= $filters['status'] === (string) $option['id_estado'] ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $option['nombre'])) ?></option><?php endforeach; ?></select></label>
        <label>Prioridad<select name="priority"><option value="">Todas</option><?php foreach ($catalogs['priorities'] as $option): ?><option value="<?= (int) $option['id_prioridad'] ?>" <?= $filters['priority'] === (string) $option['id_prioridad'] ? 'selected' : '' ?>><?= e($option['nombre']) ?></option><?php endforeach; ?></select></label>
        <label>Categoría<select name="category"><option value="">Todas</option><?php foreach ($catalogs['categories'] as $option): ?><option value="<?= (int) $option['id_categoria'] ?>" <?= $filters['category'] === (string) $option['id_categoria'] ? 'selected' : '' ?>><?= e($option['nombre']) ?></option><?php endforeach; ?></select></label>
        <label>Desde<input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
        <label>Hasta<input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
        <div class="filter-actions"><button class="button button-primary" type="submit">Filtrar</button><a class="button button-secondary" href="<?= e(url('incidents')) ?>">Limpiar</a></div>
    </form>
    <?php if (!$result['items']): ?><div class="empty-state">No hay incidencias que coincidan con los filtros.</div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>ID</th><th>Título</th><?php if ($user['role'] === 'ADMINISTRADOR'): ?><th>Reportante</th><?php endif; ?><th>Estado</th><th>Prioridad</th><th>Fecha</th><th>Acción</th></tr></thead><tbody>
        <?php foreach ($result['items'] as $item): ?><tr>
            <td class="mono"><?= e($item['codigo']) ?></td>
            <td><a class="table-title" href="<?= e(url('incident-view', ['id' => $item['id_incidencia']])) ?>"><?= e($item['titulo']) ?></a><small class="table-subtitle"><?= e($item['category']) ?></small></td>
            <?php if ($user['role'] === 'ADMINISTRADOR'): ?><td><?= e($item['reporter']) ?></td><?php endif; ?>
            <td><span class="badge" style="--badge-color:<?= e($item['status_color']) ?>"><?= e(str_replace('_', ' ', $item['status'])) ?></span></td>
            <td><span class="priority-dot" style="--priority-color:<?= e($item['priority_color']) ?>"></span><?= e($item['priority']) ?></td>
            <td><?= e(date('d/m/Y', strtotime($item['fecha_creacion']))) ?></td>
            <td class="actions"><a class="text-link" href="<?= e(url('incident-view', ['id' => $item['id_incidencia']])) ?>">Ver</a>
                <?php if ($user['role'] === 'ADMINISTRADOR'): ?><form method="post" action="<?= e(url('incident-delete', ['id' => $item['id_incidencia']])) ?>" onsubmit="return confirm('¿Eliminar esta incidencia? Esta acción no se puede deshacer.');"><?= csrf_field() ?><button class="link-danger" type="submit">Eliminar</button></form><?php endif; ?>
            </td>
        </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <div class="pagination"><span>Mostrando <?= (int) $result['first'] ?>–<?= (int) $result['last'] ?> de <?= (int) $result['total'] ?></span><div>
        <?php $pageParams = array_filter($filters, static fn($value) => $value !== ''); ?>
        <?php if ($result['page'] > 1): ?><a class="button button-secondary" href="<?= e(url('incidents', $pageParams + ['page' => $result['page'] - 1])) ?>">← Anterior</a><?php else: ?><span class="button button-disabled">← Anterior</span><?php endif; ?>
        <span class="page-count">Página <?= (int) $result['page'] ?> de <?= (int) $result['pages'] ?></span>
        <?php if ($result['page'] < $result['pages']): ?><a class="button button-secondary" href="<?= e(url('incidents', $pageParams + ['page' => $result['page'] + 1])) ?>">Siguiente →</a><?php else: ?><span class="button button-disabled">Siguiente →</span><?php endif; ?>
    </div></div>
</section>
