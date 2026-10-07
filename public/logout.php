<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValido()) {
    aviso('error', 'No se pudo cerrar la sesión. Vuelve a intentar.');
    redirigir('index.php');
}

cerrarSesion();
aviso('ok', 'Cerraste la sesión.');
redirigir('index.php');
