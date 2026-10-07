<?php

declare(strict_types=1);

const HASH_FICTICIO = '$2y$10$eMTCBeJ.2UUwEJYBhF872.bHJxS2MgHH4DUcIipRnQ3e3hdz8FglK';

function usuarioActual(): ?array
{
    $id = $_SESSION['usuario_id'] ?? null;
    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
        return null;
    }

    $consulta = base()->prepare(
        'SELECT id, nombre, email, telefono, rol FROM usuarios WHERE id = ?'
    );
    $consulta->execute([(int) $id]);
    $usuario = $consulta->fetch();

    if (!$usuario) {
        unset($_SESSION['usuario_id']);
        return null;
    }

    return $usuario;
}

function establecerSesion(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $id;
}

function cerrarSesion(): void
{
    unset($_SESSION['usuario_id']);
    session_regenerate_id(true);
}

function redirigir(string $ruta): never
{
    header('Location: ' . $ruta);
    exit;
}

function exigirRol(string $rol): array
{
    $usuario = usuarioActual();
    if (!$usuario) {
        aviso('error', 'Tienes que entrar para continuar.');
        redirigir('login.php');
    }

    if ($usuario['rol'] !== $rol) {
        redirigir($usuario['rol'] === 'anfitrion' ? 'agenda.php' : 'horarios.php');
    }

    return $usuario;
}

function normalizarEmail(string $email): string
{
    $email = trim($email);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($email, 'UTF-8');
    }

    return strtolower($email);
}

function registrarCliente(string $nombre, string $email, string $telefono, string $clave): ?int
{
    $consulta = base()->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash, telefono, rol)
         VALUES (?, ?, ?, ?, \'cliente\')'
    );

    try {
        $consulta->execute([
            $nombre,
            $email,
            password_hash($clave, PASSWORD_DEFAULT),
            $telefono,
        ]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return null;
        }
        throw $e;
    }

    return (int) base()->lastInsertId();
}

function iniciarSesion(string $email, string $clave): bool
{
    $consulta = base()->prepare(
        'SELECT id, password_hash FROM usuarios WHERE email = ?'
    );
    $consulta->execute([$email]);
    $usuario = $consulta->fetch();
    $hash = $usuario['password_hash'] ?? HASH_FICTICIO;
    $coincide = password_verify($clave, $hash);

    if (!$usuario || !$coincide) {
        return false;
    }

    establecerSesion((int) $usuario['id']);
    return true;
}
