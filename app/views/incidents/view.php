<div class="detail-grid">
    <section class="panel detail-main">
        <div class="panel-heading"><div><p class="eyebrow"><?= e($incident['codigo']) ?></p><h2><?= e($incident['titulo']) ?></h2><p class="muted">Registrada el <?= e(date('d/m/Y H:i', strtotime($incident['fecha_creacion']))) ?> por <?= e($incident['reporter']) ?></p></div>
            <span class="badge" style="--badge-color:<?= e($incident['status'] === 'ABIERTA' ? '#3498DB' : ($incident['status'] === 'RESUELTA' ? '#2ECC71' : '#F1C40F')) ?>"><?= e(str_replace('_', ' ', $incident['status'])) ?></span></div>
        <div class="detail-facts"><span><small>Categoría</small><?= e($incident['category']) ?></span><span><small>Prioridad</small><?= e($incident['priority']) ?></span><span><small>Técnico asignado</small><?= e($incident['technician'] ?? 'Sin asignar') ?></span></div>
        <?php if ($reporterCanEdit): ?><p class="form-actions"><a class="button button-primary button-edit" href="<?= e(url('incident-edit', ['id' => $incident['id_incidencia']])) ?>">Editar</a></p><?php endif; ?>
        <h3>Descripción</h3><div class="prose"><?= nl2br(e($incident['descripcion'])) ?></div>
        <?php if (!empty($incident['solucion'])): ?><h3>Solución</h3><div class="prose"><?= nl2br(e($incident['solucion'])) ?></div><?php endif; ?>
        <section class="incident-comments" aria-labelledby="comments-heading">
            <div class="comments-heading">
                <div><h3 id="comments-heading">Comentarios</h3></div>
            </div>
            <?php if (!$comments): ?>
                <p class="comments-empty">Aún no hay comentarios.</p>
            <?php else: ?>
                <ol class="comment-list">
                    <?php foreach ($comments as $comment): ?>
                        <li class="comment-item <?= (bool) $comment['es_interno'] ? 'comment-internal' : '' ?>">
                            <div class="comment-meta">
                                <span class="comment-author"><?= e($comment['nombre'] . ' ' . $comment['apellido']) ?></span>
                                <span class="comment-role"><?= e(ucfirst(strtolower($comment['role']))) ?></span>
                                <?php if ((bool) $comment['es_interno']): ?><span class="comment-visibility">Nota interna · solo técnicos</span><?php endif; ?>
                                <time datetime="<?= e(date(DATE_ATOM, strtotime($comment['fecha']))) ?>"><?= e(date('d/m/Y H:i', strtotime($comment['fecha']))) ?></time>
                            </div>
                            <div class="comment-text"><?= nl2br(e($comment['texto'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <?php if ($canComment): ?>
                <form method="post" action="<?= e(url('incident-comment', ['id' => $incident['id_incidencia']])) ?>" class="comment-form">
                    <?= csrf_field() ?>
                    <label for="comment-text">Agregar comentario</label>
                    <textarea id="comment-text" name="texto" rows="4" maxlength="5000" placeholder="Escribe un comentario..." required></textarea>
                    <?php if ($canCommentInternally): ?>
                        <label class="comment-internal-toggle"><input type="checkbox" name="es_interno" value="1"><span>Nota interna, visible solo para técnicos</span></label>
                    <?php endif; ?>
                    <div class="comment-form-footer"><small>Máximo 5.000 caracteres.</small><button class="button button-primary" type="submit">Publicar comentario</button></div>
                </form>
            <?php endif; ?>
        </section>
        <h3>Archivos adjuntos</h3>
        <?php if (!$attachments): ?><p class="muted">No hay archivos adjuntos.</p><?php else: ?><ul class="attachment-list"><?php foreach ($attachments as $file): ?><li><a href="<?= e(url('attachment', ['id' => $file['id_adjunto']])) ?>"><?= e($file['nombre_archivo']) ?></a><small><?= number_format((int) $file['tamano_bytes'] / 1024, 0) ?> KB</small></li><?php endforeach; ?></ul><?php endif; ?>
        <a class="button button-secondary button-go-back" href="<?= e(url('incidents')) ?>">← Volver a Incidencias</a>
    </section>
    <?php if ($canManage): ?>
        <section class="panel">
            <h2>Gestionar incidencia</h2>
            <form method="post" action="<?= e(url('incident-edit', ['id' => $incident['id_incidencia']])) ?>" class="form-stack compact-form">
                <?= csrf_field() ?>
                <label>Título<input name="titulo" value="<?= e($incident['titulo']) ?>" maxlength="200" required></label>
                <label>Descripción<textarea name="descripcion" rows="5" required><?= e($incident['descripcion']) ?></textarea></label>
                <label>Categoría<select name="id_categoria"><?php foreach ($catalogs['categories'] as $item): ?><option value="<?= (int) $item['id_categoria'] ?>" <?= (int) $incident['id_categoria'] === (int) $item['id_categoria'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select></label>
                <label>Prioridad<select name="id_prioridad"><?php foreach ($catalogs['priorities'] as $item): ?><option value="<?= (int) $item['id_prioridad'] ?>" <?= (int) $incident['id_prioridad'] === (int) $item['id_prioridad'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select></label>
                <label>Estado<select name="id_estado"><?php foreach ($catalogs['statuses'] as $item): ?><option value="<?= (int) $item['id_estado'] ?>" <?= (int) $incident['id_estado'] === (int) $item['id_estado'] ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $item['nombre'])) ?></option><?php endforeach; ?></select></label>
                <label>Asignar técnico<select name="id_tecnico"><option value="">Sin asignar</option><?php foreach ($technicians as $item): ?><option value="<?= (int) $item['id_usuario'] ?>" <?= (int) $incident['id_tecnico'] === (int) $item['id_usuario'] ? 'selected' : '' ?>><?= e($item['nombre'] . ' ' . $item['apellido']) ?></option><?php endforeach; ?></select></label>
                <label>Solución<textarea name="solucion" rows="4"><?= e($incident['solucion']) ?></textarea></label>
                <button class="button button-primary" type="submit">Guardar cambios</button>
            </form>
        </section>
    <?php elseif ($technicianCanUpdate): ?>
        <section class="panel">
            <h2>Atender incidencia</h2><p class="muted">Actualiza el estado y registra la solución aplicada.</p>
            <form method="post" action="<?= e(url('incident-edit', ['id' => $incident['id_incidencia']])) ?>" class="form-stack compact-form">
                <?= csrf_field() ?>
                <label>Estado<select name="id_estado" required><?php foreach ($catalogs['statuses'] as $item): ?><?php if (in_array($item['nombre'], ['EN_PROGRESO', 'RESUELTA', 'RECHAZADA'], true)): ?><option value="<?= (int) $item['id_estado'] ?>" <?= $incident['status'] === $item['nombre'] ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $item['nombre'])) ?></option><?php endif; ?><?php endforeach; ?></select></label>
                <label>Solución aplicada<textarea name="solucion" rows="6"><?= e($incident['solucion']) ?></textarea></label>
                <button class="button button-primary" type="submit">Actualizar atención</button>
            </form>
        </section>
    <?php endif; ?>
</div>
