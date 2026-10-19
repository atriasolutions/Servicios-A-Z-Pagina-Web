<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

$resultado = koraProcesarCola(20);
echo sprintf(
    "Procesadas: %d; fallidas: %d\n",
    $resultado['procesadas'],
    $resultado['fallidas']
);

exit($resultado['fallidas'] > 0 ? 1 : 0);
