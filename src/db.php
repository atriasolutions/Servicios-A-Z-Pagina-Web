<?php

declare(strict_types=1);

function base(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $datos = configuracion()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $datos['host'],
        (int) $datos['port'],
        $datos['name'],
        $datos['charset']
    );

    try {
        $pdo = new PDO($dsn, (string) $datos['user'], (string) $datos['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException(
            'No se pudo conectar con MySQL. Revisa config/config.php: host, usuario, contraseña y que la base exista.'
        );
    }

    return $pdo;
}
