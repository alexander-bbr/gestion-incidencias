<section class="panel narrow-panel">
    <div class="panel-heading"><div><h2><?= $editing ? 'Datos de la cuenta' : 'Nueva cuenta' ?></h2><p class="muted"><?= $editing ? 'Deja la contraseña vacía para conservarla; ingresa una nueva para restablecerla.' : 'Las cuentas son creadas por un administrador.' ?></p></div></div>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('user-edit', $editing ? ['id' => $values['id_usuario']] : [])) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-grid"><label>Nombre<input name="nombre" maxlength="100" value="<?= e($values['nombre'] ?? '') ?>" required></label><label>Apellido<input name="apellido" maxlength="100" value="<?= e($values['apellido'] ?? '') ?>" required></label></div>
        <label>Correo electrónico<input type="email" name="email" maxlength="150" value="<?= e($values['email'] ?? '') ?>" required></label>
        <div class="form-grid"><label>Rol<select name="id_rol" required><option value="">Selecciona un rol</option><?php foreach ($roles as $item): ?><option value="<?= (int) $item['id_rol'] ?>" <?= (string) ($values['id_rol'] ?? '') === (string) $item['id_rol'] ? 'selected' : '' ?>><?= e(ucfirst(strtolower($item['nombre']))) ?></option><?php endforeach; ?></select></label>
        <label>Departamento<select name="id_departamento"><option value="">Sin departamento</option><?php foreach ($departments as $item): ?><option value="<?= (int) $item['id_departamento'] ?>" <?= (string) ($values['id_departamento'] ?? '') === (string) $item['id_departamento'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select></label></div>
        <label>Teléfono<input name="telefono" maxlength="20" value="<?= e($values['telefono'] ?? '') ?>"></label>
        <label><?= $editing ? 'Nueva contraseña (opcional)' : 'Contraseña' ?><input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" <?= $editing ? '' : 'required' ?>><small>Mínimo 12 caracteres<?= $editing ? '; déjala vacía si no deseas cambiarla.' : '.' ?></small></label>
        <div class="form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Guardar' : 'Crear' ?></button><a class="button button-secondary" href="<?= e(url('users')) ?>">Cancelar</a></div>
    </form>
</section>
