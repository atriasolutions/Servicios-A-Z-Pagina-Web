<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function salirImagen(int $codigo, array $datos): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    salirImagen(405, ['ok' => false, 'error' => 'La solicitud no es válida.']);
}

$usuario = usuarioActual();
if (!$usuario || $usuario['rol'] !== 'anfitrion') {
    salirImagen(403, ['ok' => false, 'error' => 'Tienes que entrar para continuar.']);
}

if (!csrfValido()) {
    salirImagen(403, ['ok' => false, 'error' => 'La solicitud no es válida. Vuelve a intentar.']);
}

$archivo = $_FILES['imagen'] ?? null;
$resultado = guardarImagenArticulo(is_array($archivo) ? $archivo : null);
salirImagen($resultado['ok'] ? 200 : 422, $resultado);
