<section class="auth-card">
    <div class="auth-brand"><span class="brand-mark">UGA</span><p>CLÍNICA UGA · VALERA</p></div>
    <h1>Bienvenido</h1>
    <p class="muted">Inicia sesión para gestionar tus incidencias.</p>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('login')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <label>Correo electrónico<input type="email" name="email" value="<?= e($email ?? '') ?>" autocomplete="username" required autofocus></label>
        <label>Contraseña
            <span class="password-field"><input id="login-password" type="password" name="password" autocomplete="current-password" required><button type="button" class="password-toggle" data-password-toggle="login-password" aria-label="Mostrar contraseña">Mostrar</button></span>
        </label>
        <button class="button button-primary button-wide" type="submit">Iniciar sesión</button>
    </form>
    <a class="auth-link" href="<?= e(url('password-help')) ?>">¿Olvidaste tu contraseña?</a>
</section>
<script>
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        input.type = input.type === 'password' ? 'text' : 'password';
        button.textContent = input.type === 'password' ? 'Mostrar' : 'Ocultar';
    });
});
</script>
