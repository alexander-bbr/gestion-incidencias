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
            <details class="notification-menu">
                <summary class="notification-trigger" aria-label="Notificaciones">
                    <svg class="notification-bell" aria-hidden="true" viewBox="0 0 24 24" fill="none">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M10 21h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <span class="notification-bell-label">Notificaciones</span>
                    <?php if ($unreadNotificationCount > 0): ?><span class="notification-count"><?= $unreadNotificationCount > 99 ? '99+' : (int) $unreadNotificationCount ?></span><?php endif; ?>
                </summary>
                <div class="notification-dropdown">
                    <div class="notification-dropdown-heading"><strong>Notificaciones</strong>
                        <?php if ($unreadNotificationCount > 0): ?>
                            <form method="post" action="<?= e(url('notification-read-all')) ?>"><?= csrf_field() ?><button type="submit">Marcar todas leídas</button></form>
                        <?php endif; ?>
                    </div>
                    <?php if (!$notifications): ?>
                        <p class="notification-empty">No tienes notificaciones.</p>
                    <?php else: ?>
                        <ul class="notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <li class="<?= !(bool) $notification['leida'] ? 'notification-unread' : '' ?>">
                                    <form method="post" action="<?= e(url('notification-open', ['id' => $notification['id_notificacion']])) ?>">
                                        <?= csrf_field() ?>
                                        <button class="notification-item" type="submit">
                                            <span class="notification-message" style="--notification-color:<?= e($notification['accent_color']) ?>"><?= notification_message($notification) ?></span>
                                            <small><?= e(date('d/m/Y H:i', strtotime($notification['fecha']))) ?></small>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </details>
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
    <?php if ($visibleNotifications): ?>
        <section class="notification-announcements" aria-label="Notificaciones nuevas" aria-live="polite">
            <?php foreach ($visibleNotifications as $notification): ?>
                <form method="post" action="<?= e(url('notification-open', ['id' => $notification['id_notificacion']])) ?>" class="notification-announcement">
                    <?= csrf_field() ?>
                    <span class="notification-announcement-icon" style="--notification-color:<?= e($notification['accent_color']) ?>" aria-hidden="true">●</span>
                    <span class="notification-announcement-content">
                        <span class="notification-announcement-message"><?= notification_message($notification) ?></span>
                        <small class="notification-announcement-date"><?= e(date('d/m/Y H:i', strtotime($notification['fecha']))) ?></small>
                    </span>
                    <button type="submit">Ver incidencia</button>
                </form>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
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
