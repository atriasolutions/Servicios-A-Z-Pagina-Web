<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function salirChat(int $codigo, array $datos): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    salirChat(405, ['ok' => false, 'error' => 'La solicitud no es válida.']);
}

$cuerpo = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($cuerpo)) {
    salirChat(400, ['ok' => false, 'error' => 'La solicitud no es válida.']);
}

$csrf = (string) ($cuerpo['csrf'] ?? '');
if (!hash_equals(tokenCsrf(), $csrf)) {
    salirChat(403, ['ok' => false, 'error' => 'La solicitud no es válida. Vuelve a intentar.']);
}

$accion = (string) ($cuerpo['accion'] ?? '');
$token = asegurarVisitaChat();

if ($accion === 'estado') {
    salirChat(200, estadoChatVisita($token, !empty($cuerpo['marcar'])));
}

if ($accion === 'iniciar') {
    $resultado = iniciarChatVisita(
        $token,
        (string) ($cuerpo['nombre'] ?? ''),
        (string) ($cuerpo['tema'] ?? ''),
        (string) ($cuerpo['texto'] ?? '')
    );
    salirChat($resultado['ok'] ? 200 : 422, $resultado);
}

if ($accion === 'enviar') {
    $resultado = enviarChatVisita($token, (string) ($cuerpo['texto'] ?? ''));
    salirChat($resultado['ok'] ? 200 : 422, $resultado);
}

$usuario = usuarioActual();
if (!$usuario || $usuario['rol'] !== 'anfitrion') {
    salirChat(403, ['ok' => false, 'error' => 'No tienes permiso para ver las conversaciones.']);
}

if ($accion === 'hilo') {
    $conversacion = conversacionPorId((int) ($cuerpo['id'] ?? 0));
    if (!$conversacion) {
        salirChat(404, ['ok' => false, 'error' => 'Esa conversación no existe.']);
    }
    marcarLeidosChat($conversacion['id'], 'visita');
    salirChat(200, ['ok' => true, 'mensajes' => $conversacion['mensajes']]);
}

salirChat(400, ['ok' => false, 'error' => 'La solicitud no es válida.']);
