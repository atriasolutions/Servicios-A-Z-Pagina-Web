<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#contacto');
}

if (trim((string) ($_POST['sitio_web'] ?? '')) !== '') {
    aviso('ok', 'Recibimos su solicitud. Nos pondremos en contacto a la brevedad.');
    redirigir('index.php#contacto');
}

if (!csrfValido()) {
    aviso('error', 'La solicitud no es válida. Vuelva a intentar.');
    redirigir('index.php#contacto');
}

$validacion = validarClienteReserva($_POST);
$datos = $validacion['datos'];

if ($validacion['errores'] !== []) {
    $_SESSION['contacto'] = [
        'datos' => $datos,
        'errores' => $validacion['errores'],
    ];
    aviso('error', 'Revise los datos del formulario.');
    redirigir('index.php#contacto');
}

if (!notificarContacto($datos)) {
    $_SESSION['contacto'] = [
        'datos' => $datos,
        'errores' => [],
    ];
    aviso('error', 'No pudimos enviar su solicitud. Inténtelo de nuevo o escríbanos por WhatsApp.');
    redirigir('index.php#contacto');
}

aviso('ok', 'Recibimos su solicitud. Nos pondremos en contacto a la brevedad.');
redirigir('index.php#contacto');
