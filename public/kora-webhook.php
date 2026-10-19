<?php

declare(strict_types=1);

$cuerpo = (string) file_get_contents('php://input');

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo '{"error":"method_not_allowed"}';
    exit;
}

try {
    $firma = (string) ($_SERVER['HTTP_X_KORA_SIGNATURE'] ?? '');
    if ($firma === '' || !koraFirmaWebhookValida($cuerpo, $firma)) {
        http_response_code(401);
        echo '{"error":"invalid_signature"}';
        exit;
    }

    $evento = json_decode($cuerpo, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($evento)) {
        throw new InvalidArgumentException('El cuerpo no contiene un evento.');
    }
    $tipoEncabezado = trim((string) ($_SERVER['HTTP_X_KORA_EVENT'] ?? ''));
    if ($tipoEncabezado === '' || !hash_equals((string) ($evento['type'] ?? ''), $tipoEncabezado)) {
        throw new InvalidArgumentException('El tipo del evento no coincide.');
    }

    koraRegistrarWebhook($evento);
    http_response_code(204);
} catch (JsonException | InvalidArgumentException $e) {
    http_response_code(400);
    echo '{"error":"invalid_event"}';
} catch (Throwable $e) {
    error_log('No se pudo registrar un webhook de Kora: ' . $e->getMessage());
    http_response_code(500);
    echo '{"error":"temporary_failure"}';
}
