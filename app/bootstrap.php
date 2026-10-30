<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

$localConfigPath = ROOT_PATH . '/config/local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];

define('DB_CONFIG', array_merge([
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_NAME') ?: 'gestion_incidencias',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
], is_array($localConfig) ? $localConfig : []));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

require ROOT_PATH . '/app/helpers.php';

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    http_response_code(500);
    if (PHP_SAPI === 'cli') {
        echo $exception->getMessage() . PHP_EOL;
        return;
    }

    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Error</title>'
        . '<main style="font:16px sans-serif;max-width:640px;margin:10vh auto">'
        . '<h1>No se pudo completar la solicitud</h1>'
        . '<p>Verifica la conexión con la base de datos y los registros de PHP en XAMPP.</p>'
        . '</main></html>';
});
