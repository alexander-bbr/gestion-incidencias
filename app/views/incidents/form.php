<section class="panel narrow-panel">
    <div class="panel-heading"><div><h2><?= $editing ? 'Edita la incidencia' : 'Describe lo ocurrido' ?></h2><p class="muted"><?= $editing ? 'Actualiza los detalles y selecciona el técnico que atenderá el reporte.' : 'Los campos marcados son obligatorios. Puedes adjuntar hasta 5 MB por archivo y asignar un técnico.' ?></p></div></div>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e($editing ? url('incident-edit', ['id' => $incidentId]) : url('incident-create')) ?>" enctype="multipart/form-data" class="form-stack">
        <?= csrf_field() ?>
        <label>Título<input name="titulo" maxlength="200" value="<?= e($values['titulo'] ?? '') ?>" required></label>
        <div class="form-grid">
            <label>Categoría<select name="id_categoria" required><option value="">Selecciona una categoría</option><?php foreach ($catalogs['categories'] as $item): ?><option value="<?= (int) $item['id_categoria'] ?>" <?= (string) ($values['id_categoria'] ?? '') === (string) $item['id_categoria'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select></label>
            <fieldset><legend>Prioridad</legend><div class="radio-row"><?php foreach ($catalogs['priorities'] as $item): ?><label class="radio-option"><input type="radio" name="id_prioridad" value="<?= (int) $item['id_prioridad'] ?>" <?= (string) ($values['id_prioridad'] ?? '') === (string) $item['id_prioridad'] ? 'checked' : '' ?> required><span><?= e($item['nombre']) ?></span></label><?php endforeach; ?></div></fieldset>
        </div>
        <label>Técnico asignado<select name="id_tecnico"><option value="">Sin asignar</option><?php foreach ($technicians as $item): ?><option value="<?= (int) $item['id_usuario'] ?>" <?= (string) ($values['id_tecnico'] ?? '') === (string) $item['id_usuario'] ? 'selected' : '' ?>><?= e($item['nombre'] . ' ' . $item['apellido']) ?></option><?php endforeach; ?></select><small>Puedes asignar o cambiar el técnico que atenderá la incidencia.</small></label>
        <label>Descripción<textarea name="descripcion" rows="6" required><?= e($values['descripcion'] ?? '') ?></textarea></label>
        <?php if (!$editing): ?><label>Adjuntar archivos<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"><small>PDF, imágenes JPG/PNG, Word o Excel. Máximo 5 MB cada archivo.</small></label><?php endif; ?>
        <div class="form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Guardar cambios' : 'Guardar' ?></button><a class="button button-secondary" href="<?= e($editing ? url('incident-view', ['id' => $incidentId]) : url('incidents')) ?>">Cancelar</a></div>
    </form>
</section>
