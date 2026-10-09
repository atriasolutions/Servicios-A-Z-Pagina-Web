<?php

declare(strict_types=1);

function etiquetasVite(): void
{
    $raiz = dirname(__DIR__);
    $hot = $raiz . '/public/hot';
    if (is_file($hot)) {
        $origen = trim((string) file_get_contents($hot));
        if ($origen !== '') {
            echo '<script type="module" src="' . e($origen) . '/@vite/client"></script>';
            echo '<script type="module" src="' . e($origen) . '/recursos/app.js"></script>';
            return;
        }
    }

    $manifiesto = manifiestoVite($raiz);
    $entrada = is_array($manifiesto) ? ($manifiesto['recursos/app.js'] ?? null) : null;
    if (!is_array($entrada)) {
        throw new RuntimeException(
            'Faltan los archivos compilados. En la carpeta del proyecto ejecuta npm run build.'
        );
    }

    $css = $entrada['css'] ?? [];
    if (is_array($css)) {
        foreach ($css as $archivo) {
            echo '<link rel="stylesheet" href="' . e(rutaBuild((string) $archivo)) . '">';
        }
    }

    $archivo = (string) ($entrada['file'] ?? '');
    if ($archivo !== '') {
        echo '<script type="module" src="' . e(rutaBuild($archivo)) . '"></script>';
    }
}

function manifiestoVite(string $raiz): ?array
{
    foreach (['/public/build/.vite/manifest.json', '/public/build/manifest.json'] as $relativo) {
        $ruta = $raiz . $relativo;
        if (!is_file($ruta)) {
            continue;
        }

        $datos = json_decode((string) file_get_contents($ruta), true);
        if (is_array($datos)) {
            return $datos;
        }
    }

    return null;
}

function rutaBuild(string $archivo): string
{
    return '/build/' . ltrim($archivo, '/');
}
