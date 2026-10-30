<?php

declare(strict_types=1);

use App\Models\User;

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = 'home', array $params = []): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    $query = http_build_query(array_merge(['route' => $route], $params));
    return ($base === '' ? '' : $base) . '/?' . $query;
}

function asset_url(string $path): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

function redirect(string $route, array $params = []): void
{
    header('Location: ' . url($route, $params));
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $submitted = $_POST['_csrf'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('La sesión del formulario venció. Regresa e inténtalo nuevamente.');
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($flashes) ? $flashes : [];
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login');
    }
    $freshUser = (new \App\Models\User(\App\Models\Database::connection()))
        ->find((int) $user['id_usuario']);
    if ($freshUser === null || !(bool) $freshUser['activo']) {
        unset($_SESSION['user']);
        flash('error', 'Tu cuenta ya no está activa. Contacta al administrador.');
        redirect('login');
    }
    $_SESSION['user'] = $freshUser;
    return $freshUser;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta sección.');
    }
    return $user;
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewPath = ROOT_PATH . '/app/views/' . $view . '.php';
    if (!is_file($viewPath)) {
        throw new RuntimeException('No existe la vista solicitada.');
    }
    require ROOT_PATH . '/app/views/layout.php';
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === User::ROLE_ADMIN;
}
