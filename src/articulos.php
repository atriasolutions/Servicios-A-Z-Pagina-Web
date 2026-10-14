<?php

declare(strict_types=1);

function listarArticulos(): array
{
    $consulta = base()->query(
        'SELECT id, titulo, cuerpo, publicado_en
         FROM articulos
         ORDER BY publicado_en DESC, id DESC'
    );

    return $consulta->fetchAll();
}

function obtenerArticulo(int $id): ?array
{
    if ($id < 1) {
        return null;
    }

    $consulta = base()->prepare(
        'SELECT id, titulo, cuerpo, publicado_en FROM articulos WHERE id = ?'
    );
    $consulta->execute([$id]);
    $articulo = $consulta->fetch();

    return $articulo ?: null;
}

function guardarArticulo(?int $id, string $titulo, string $cuerpo): array
{
    $titulo = trim($titulo);
    $cuerpo = trim(str_replace(["\r\n", "\r"], "\n", $cuerpo));
    $errores = [];

    if ($titulo === '') {
        $errores[] = 'Escribe un título.';
    } elseif (mb_strlen($titulo) > 160) {
        $errores[] = 'El título puede tener hasta 160 caracteres.';
    }

    if ($cuerpo === '') {
        $errores[] = 'Escribe el texto del artículo.';
    } elseif (mb_strlen($cuerpo) > 20000) {
        $errores[] = 'El texto puede tener hasta 20.000 caracteres.';
    }

    if ($errores !== []) {
        return ['errores' => $errores, 'id' => $id];
    }

    if ($id === null) {
        $consulta = base()->prepare('INSERT INTO articulos (titulo, cuerpo) VALUES (?, ?)');
        $consulta->execute([$titulo, $cuerpo]);

        return ['errores' => [], 'id' => (int) base()->lastInsertId()];
    }

    if (obtenerArticulo($id) === null) {
        return ['errores' => ['Ese artículo ya no existe.'], 'id' => null];
    }

    $consulta = base()->prepare('UPDATE articulos SET titulo = ?, cuerpo = ? WHERE id = ?');
    $consulta->execute([$titulo, $cuerpo, $id]);

    return ['errores' => [], 'id' => $id];
}

function eliminarArticulo(int $id): bool
{
    if ($id < 1) {
        return false;
    }

    $consulta = base()->prepare('DELETE FROM articulos WHERE id = ?');
    $consulta->execute([$id]);

    return $consulta->rowCount() > 0;
}

function parrafosDe(string $cuerpo): array
{
    $normalizado = str_replace(["\r\n", "\r"], "\n", trim($cuerpo));
    $bloques = preg_split("/\n{2,}/", $normalizado) ?: [];
    $parrafos = [];

    foreach ($bloques as $bloque) {
        $bloque = trim($bloque);
        if ($bloque !== '') {
            $parrafos[] = $bloque;
        }
    }

    return $parrafos;
}

function htmlParrafos(string $cuerpo): string
{
    $html = '';
    foreach (parrafosDe($cuerpo) as $parrafo) {
        if (esListaArticulo($parrafo)) {
            $html .= '<ul>';
            foreach (explode("\n", $parrafo) as $linea) {
                $linea = trim($linea);
                if ($linea === '' || !str_starts_with($linea, '- ')) {
                    continue;
                }
                $html .= '<li>' . marcasDeArticulo(substr($linea, 2)) . '</li>';
            }
            $html .= '</ul>';
            continue;
        }

        $lineas = array_map(
            static fn (string $linea): string => marcasDeArticulo($linea),
            explode("\n", $parrafo)
        );
        $html .= '<p>' . implode('<br>', $lineas) . '</p>';
    }

    return $html;
}

function esListaArticulo(string $parrafo): bool
{
    $alguna = false;
    foreach (explode("\n", $parrafo) as $linea) {
        if (trim($linea) === '') {
            continue;
        }
        if (!str_starts_with($linea, '- ')) {
            return false;
        }
        $alguna = true;
    }

    return $alguna;
}

function marcasDeArticulo(string $linea): string
{
    $texto = e($linea);
    $texto = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $texto) ?? $texto;
    $texto = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $texto) ?? $texto;
    $convertido = preg_replace_callback(
        '/\[([^\[\]]+)\]\((https?:\/\/[^\s)]+)\)/iu',
        static function (array $coincidencia): string {
            $direccion = html_entity_decode($coincidencia[2], ENT_QUOTES, 'UTF-8');
            if (!enlaceDeArticulo($direccion)) {
                return $coincidencia[0];
            }

            return '<a href="' . e($direccion) . '">' . $coincidencia[1] . '</a>';
        },
        $texto
    );

    return $convertido ?? $texto;
}

function enlaceDeArticulo(string $direccion): bool
{
    $partes = parse_url($direccion);
    if (!is_array($partes) || empty($partes['scheme']) || empty($partes['host'])) {
        return false;
    }

    $esquema = strtolower((string) $partes['scheme']);

    return $esquema === 'http' || $esquema === 'https';
}

function textoSinMarcas(string $texto): string
{
    $texto = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $texto) ?? $texto;
    $texto = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '$1', $texto) ?? $texto;
    $texto = preg_replace('/\[([^\[\]]+)\]\([^)]+\)/u', '$1', $texto) ?? $texto;
    $texto = preg_replace('/^- /mu', '', $texto) ?? $texto;

    return $texto;
}

function extractoArticulo(string $cuerpo): string
{
    $parrafos = parrafosDe($cuerpo);
    $primero = textoSinMarcas($parrafos[0] ?? '');
    $primero = preg_replace('/\s+/u', ' ', $primero) ?? '';
    $maximo = 180;

    if (mb_strlen($primero) <= $maximo) {
        return $primero;
    }

    $corte = mb_substr($primero, 0, $maximo);
    $espacio = mb_strrpos($corte, ' ');
    if ($espacio !== false && $espacio > 80) {
        $corte = mb_substr($corte, 0, $espacio);
    }

    return rtrim($corte, '.,;:') . '…';
}

function fechaDeArticulo(string $marca): DateTimeImmutable
{
    $fecha = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $marca, zonaHoraria());
    if ($fecha instanceof DateTimeImmutable) {
        return $fecha;
    }

    return new DateTimeImmutable('now', zonaHoraria());
}

function fechaArticulo(string $marca): string
{
    $fecha = fechaDeArticulo($marca);

    return fechaLarga($fecha) . ' de ' . $fecha->format('Y');
}
