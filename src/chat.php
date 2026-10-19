<?php

declare(strict_types=1);

function temasChat(): array
{
    return servicios() + ['consulta_general' => 'Consulta general'];
}

function asegurarVisitaChat(): string
{
    $token = $_COOKIE['chat_visita'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token = bin2hex(random_bytes(32));
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('chat_visita', $token, [
            'expires' => time() + 60 * 60 * 24 * 180,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['chat_visita'] = $token;
    }

    return $token;
}

function textoChat(string $texto): ?string
{
    $texto = str_replace(["\r\n", "\r"], "\n", trim($texto));
    $texto = preg_replace("/[ \t]+/u", ' ', $texto) ?? '';
    $texto = preg_replace("/\n{3,}/u", "\n\n", $texto) ?? '';
    $largo = mb_strlen($texto);
    if ($largo < 1 || $largo > 1000) {
        return null;
    }

    return $texto;
}

function nombreChat(string $nombre): ?string
{
    $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
    $largo = mb_strlen($nombre);
    if ($largo < 2 || $largo > 80) {
        return null;
    }

    return $nombre;
}

function horaChat(string $marca): string
{
    $fecha = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $marca, zonaHoraria());
    if (!$fecha) {
        return '';
    }

    $hoy = new DateTimeImmutable('today', zonaHoraria());
    if ($fecha->format('Y-m-d') === $hoy->format('Y-m-d')) {
        return $fecha->format('H:i');
    }

    return $fecha->format('j/n') . ', ' . $fecha->format('H:i');
}

function conversacionPorToken(string $token): ?array
{
    $consulta = base()->prepare(
        'SELECT id, nombre, tema FROM conversaciones WHERE token_hash = ?'
    );
    $consulta->execute([hash('sha256', $token)]);
    $fila = $consulta->fetch();

    return $fila ?: null;
}

function mensajesDeChat(int $conversacionId): array
{
    $consulta = base()->prepare(
        'SELECT id, autor, texto, creado_en FROM (
            SELECT id, autor, texto, creado_en
            FROM mensajes
            WHERE conversacion_id = ?
            ORDER BY id DESC
            LIMIT 200
        ) recientes ORDER BY id ASC'
    );
    $consulta->execute([$conversacionId]);
    $mensajes = [];
    foreach ($consulta->fetchAll() as $fila) {
        $mensajes[] = [
            'id' => (int) $fila['id'],
            'autor' => (string) $fila['autor'],
            'texto' => (string) $fila['texto'],
            'hora' => horaChat((string) $fila['creado_en']),
        ];
    }

    return $mensajes;
}

function sinLeerChat(int $conversacionId, string $autor): int
{
    $consulta = base()->prepare(
        'SELECT COUNT(*) FROM mensajes
         WHERE conversacion_id = ? AND autor = ? AND leido_en IS NULL'
    );
    $consulta->execute([$conversacionId, $autor]);

    return (int) $consulta->fetchColumn();
}

function marcarLeidosChat(int $conversacionId, string $autor): void
{
    $consulta = base()->prepare(
        'UPDATE mensajes SET leido_en = CURRENT_TIMESTAMP
         WHERE conversacion_id = ? AND autor = ? AND leido_en IS NULL'
    );
    $consulta->execute([$conversacionId, $autor]);
}

function chatPuedeEnviar(int $conversacionId, string $autor): bool
{
    $consulta = base()->prepare(
        'SELECT COUNT(*) FROM mensajes
         WHERE conversacion_id = ? AND autor = ? AND creado_en >= (NOW() - INTERVAL 1 MINUTE)'
    );
    $consulta->execute([$conversacionId, $autor]);

    return (int) $consulta->fetchColumn() < 8;
}

function estadoChatVisita(string $token, bool $marcar): array
{
    $conversacion = conversacionPorToken($token);
    if (!$conversacion) {
        return ['ok' => true, 'nueva' => true];
    }

    $id = (int) $conversacion['id'];
    $pendientes = sinLeerChat($id, 'anfitrion');
    if ($marcar) {
        marcarLeidosChat($id, 'anfitrion');
        $pendientes = 0;
    }

    $temas = temasChat();
    $tema = (string) $conversacion['tema'];

    return [
        'ok' => true,
        'nueva' => false,
        'nombre' => (string) $conversacion['nombre'],
        'tema_clave' => $tema,
        'tema' => $temas[$tema] ?? $tema,
        'mensajes' => mensajesDeChat($id),
        'sin_leer' => $pendientes,
    ];
}

function guardarMensajeChat(int $conversacionId, string $autor, string $texto): void
{
    $consulta = base()->prepare(
        'INSERT INTO mensajes (conversacion_id, autor, texto) VALUES (?, ?, ?)'
    );
    $consulta->execute([$conversacionId, $autor, $texto]);
    $actualizacion = base()->prepare(
        'UPDATE conversaciones SET actualizada_en = CURRENT_TIMESTAMP WHERE id = ?'
    );
    $actualizacion->execute([$conversacionId]);
}

function iniciarChatVisita(string $token, string $nombre, string $tema, string $texto): array
{
    $nombre = nombreChat($nombre);
    if ($nombre === null) {
        return ['ok' => false, 'error' => 'Escribe tu nombre, con 2 a 80 caracteres.'];
    }

    $temas = temasChat();
    if (!isset($temas[$tema])) {
        return ['ok' => false, 'error' => 'Elige un tema de la lista.'];
    }

    $texto = textoChat($texto);
    if ($texto === null) {
        return ['ok' => false, 'error' => 'Escribe un mensaje de hasta 1000 caracteres.'];
    }

    $conversacion = conversacionPorToken($token);
    if (!$conversacion) {
        $alta = base()->prepare(
            'INSERT INTO conversaciones (token_hash, nombre, tema) VALUES (?, ?, ?)'
        );
        try {
            $alta->execute([hash('sha256', $token), $nombre, $tema]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
        }
        $conversacion = conversacionPorToken($token);
    }

    if (!$conversacion) {
        return ['ok' => false, 'error' => 'No pudimos abrir la conversación. Intenta de nuevo.'];
    }

    $id = (int) $conversacion['id'];
    if (!chatPuedeEnviar($id, 'visita')) {
        return ['ok' => false, 'error' => 'Espera un momento antes de enviar otro mensaje.'];
    }

    guardarMensajeChat($id, 'visita', $texto);

    return estadoChatVisita($token, true);
}

function enviarChatVisita(string $token, string $texto): array
{
    $conversacion = conversacionPorToken($token);
    if (!$conversacion) {
        return ['ok' => false, 'error' => 'Escribe tu nombre para empezar la conversación.'];
    }

    $texto = textoChat($texto);
    if ($texto === null) {
        return ['ok' => false, 'error' => 'Escribe un mensaje de hasta 1000 caracteres.'];
    }

    $id = (int) $conversacion['id'];
    if (!chatPuedeEnviar($id, 'visita')) {
        return ['ok' => false, 'error' => 'Espera un momento antes de enviar otro mensaje.'];
    }

    guardarMensajeChat($id, 'visita', $texto);

    return estadoChatVisita($token, true);
}

function mensajesChatSinLeer(): int
{
    return (int) base()->query(
        "SELECT COUNT(*) FROM mensajes WHERE autor = 'visita' AND leido_en IS NULL"
    )->fetchColumn();
}

function listarConversaciones(): array
{
    $consulta = base()->query(
        'SELECT c.id, c.nombre, c.tema, c.actualizada_en,
            (SELECT m.texto FROM mensajes m
             WHERE m.conversacion_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultimo,
            (SELECT COUNT(*) FROM mensajes m
             WHERE m.conversacion_id = c.id AND m.autor = \'visita\' AND m.leido_en IS NULL) AS sin_leer
         FROM conversaciones c
         ORDER BY c.actualizada_en DESC, c.id DESC'
    );

    $temas = temasChat();
    $lista = [];
    foreach ($consulta->fetchAll() as $fila) {
        $tema = (string) $fila['tema'];
        $lista[] = [
            'id' => (int) $fila['id'],
            'nombre' => (string) $fila['nombre'],
            'tema' => $temas[$tema] ?? $tema,
            'ultimo' => extractoChat((string) ($fila['ultimo'] ?? '')),
            'hora' => horaChat((string) $fila['actualizada_en']),
            'sin_leer' => (int) $fila['sin_leer'],
        ];
    }

    return $lista;
}

function extractoChat(string $texto): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    if (mb_strlen($texto) <= 90) {
        return $texto;
    }

    return rtrim(mb_substr($texto, 0, 90)) . '…';
}

function conversacionPorId(int $id): ?array
{
    $consulta = base()->prepare(
        'SELECT id, nombre, tema, actualizada_en FROM conversaciones WHERE id = ?'
    );
    $consulta->execute([$id]);
    $fila = $consulta->fetch();
    if (!$fila) {
        return null;
    }

    $temas = temasChat();
    $tema = (string) $fila['tema'];

    return [
        'id' => (int) $fila['id'],
        'nombre' => (string) $fila['nombre'],
        'tema' => $temas[$tema] ?? $tema,
        'mensajes' => mensajesDeChat((int) $fila['id']),
    ];
}

function publicarRespuestaChat(int $id, string $texto): ?string
{
    if (!conversacionPorId($id)) {
        return 'Esa conversación no existe.';
    }

    $texto = textoChat($texto);
    if ($texto === null) {
        return 'Escribe un mensaje de hasta 1000 caracteres.';
    }

    if (!chatPuedeEnviar($id, 'anfitrion')) {
        return 'Espera un momento antes de enviar otro mensaje.';
    }

    guardarMensajeChat($id, 'anfitrion', $texto);
    marcarLeidosChat($id, 'visita');

    return null;
}
