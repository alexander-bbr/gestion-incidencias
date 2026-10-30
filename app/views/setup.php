<section class="auth-card">
    <div class="auth-brand"><span class="brand-mark">UGA</span><p>CONFIGURACIÓN INICIAL</p></div>
    <h1>Crear administrador</h1>
    <p class="muted">Crea la primera cuenta administrativa. Esta pantalla se desactivará al guardarla.</p>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('setup')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Nombre<input name="nombre" maxlength="100" value="<?= e($values['nombre'] ?? '') ?>" required></label>
            <label>Apellido<input name="apellido" maxlength="100" value="<?= e($values['apellido'] ?? '') ?>" required></label>
        </div>
        <label>Correo electrónico<input type="email" name="email" maxlength="150" value="<?= e($values['email'] ?? '') ?>" required></label>
        <label>Contraseña segura<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required><small>Mínimo 12 caracteres, máximo 72 bytes.</small></label>
        <button class="button button-primary button-wide" type="submit">Crear cuenta de administrador</button>
    </form>
</section>
