<?php

declare(strict_types=1);

function configuracion(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $ruta = dirname(__DIR__) . '/config/config.php';
    if (!is_file($ruta)) {
        throw new RuntimeException(
            'Falta config/config.php. Copia config/config.example.php y completa los datos de MySQL.'
        );
    }

    $cargada = require $ruta;
    if (!is_array($cargada)) {
        throw new RuntimeException('config/config.php tiene que devolver un array.');
    }

    $config = $cargada;
    return $config;
}

function zonaHoraria(): DateTimeZone
{
    $nombre = (string) (configuracion()['agenda']['timezone'] ?? '');
    try {
        return new DateTimeZone($nombre);
    } catch (Exception $e) {
        throw new RuntimeException('La zona horaria de config/config.php no es válida.');
    }
}

set_exception_handler(static function (Throwable $e): void {
    error_log($e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $mensaje = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Ocurrió un error inesperado. Intenta de nuevo.';

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Error</title></head><body>';
    echo '<p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
});

date_default_timezone_set(zonaHoraria()->getName());

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('agenda');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require __DIR__ . '/db.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/agenda.php';
require __DIR__ . '/vite.php';
require __DIR__ . '/vista.php';
