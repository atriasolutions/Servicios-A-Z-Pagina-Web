<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#contacto');
}

if (trim((string) ($_POST['sitio_web'] ?? '')) !== '') {
    aviso('ok', 'Recibimos su solicitud. Nos pondremos en contacto a la brevedad.', true);
    redirigir('index.php#contacto');
}

if (!csrfValido()) {
    aviso('error', 'La solicitud no es válida. Vuelva a intentar.', true);
    redirigir('index.php#contacto');
}

$validacion = validarContacto($_POST);
$datos = $validacion['datos'];

if ($validacion['errores'] !== []) {
    $_SESSION['contacto'] = [
        'datos' => $datos,
        'errores' => $validacion['errores'],
    ];
    aviso('error', 'Revise los datos del formulario.', true);
    redirigir('index.php#contacto');
}

$alta = koraRegistrarContacto($datos);
if (!$alta['ok']) {
    $_SESSION['contacto'] = [
        'datos' => $datos,
        'errores' => [],
    ];
    aviso(
        'error',
        (int) $alta['estado'] === 409
            ? 'Esa empresa o ese correo ya están registrados.'
            : 'No pudimos enviar su solicitud. Inténtelo de nuevo o escríbanos por WhatsApp.',
        true
    );
    redirigir('index.php#contacto');
}

notificarContacto($datos);

aviso('ok', 'Recibimos su solicitud. Nos pondremos en contacto a la brevedad.', true);
redirigir('index.php#contacto');
