<?php
$pageTitle = isset($title) ? $title . ' | Gestión de Incidencias' : 'Gestión de Incidencias';
$signedInUser = current_user();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e(asset_url('public/favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(asset_url('public/assets/styles/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('public/assets/styles/fonts.css')) ?>">
</head>
<body class="<?= !empty($public) ? 'public-page' : '' ?>">
<?php if (empty($public)): ?>
    <header class="topbar">
        <a class="brand" href="<?= e(url('home')) ?>">
            <img src="<?= e(asset_url('public/assets/images/favicon.png')) ?>" alt="Logo de la Clínica" class="company-logo">
            <span>Clínica UGA<small>Gestión de Incidencias</small></span>
        </a>
        <div class="topbar-user">
            <span><?= e($signedInUser['nombre'] . ' ' . $signedInUser['apellido']) ?></span>
            <form method="post" action="<?= e(url('logout')) ?>">
                <?= csrf_field() ?>
                <button class="button button-quiet" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </header>
    <div class="app-shell">
        <aside class="sidebar">
            <p class="nav-caption">MENÚ</p>
            <a class="nav-link" href="<?= e(url('home')) ?>">Inicio</a>
            <a class="nav-link" href="<?= e(url('incidents')) ?>">Incidencias</a>
            <?php if ($signedInUser['role'] === 'ADMINISTRADOR'): ?>
                <a class="nav-link" href="<?= e(url('users')) ?>">Gestión de Usuarios</a>
                <a class="nav-link" href="<?= e(url('reports')) ?>">Reportes y Métricas</a>
            <?php endif; ?>
        </aside>
        <main class="main-content">
            <div class="page-heading"><div><p class="eyebrow">CLÍNICA UGA · VALERA</p><h1><?= e($title ?? 'Gestión') ?></h1></div></div>
            <?php foreach (take_flashes() as $message): ?>
                <div class="alert alert-<?= e($message['type']) ?>" role="status"><?= e($message['message']) ?></div>
            <?php endforeach; ?>
            <?php require $viewPath; ?>
        </main>
    </div>
<?php else: ?>
    <main class="auth-shell">
        <?php foreach (take_flashes() as $message): ?>
            <div class="alert alert-<?= e($message['type']) ?>" role="status"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
        <?php require $viewPath; ?>
    </main>
<?php endif; ?>
</body>
</html>
