<?php

declare(strict_types=1);

const ARTICULOS_POR_PAGINA = 10;
const ARTICULOS_RECORTE = 800;
const IMAGEN_ARTICULO_MAX_BYTES = 5242880;
const IMAGEN_ARTICULO_MAX_LADO = 3000;
const IMAGEN_ARTICULO_MAX_PIXELS = 12000000;

function listarArticulosPublicados(int $pagina): array
{
    $porPagina = ARTICULOS_POR_PAGINA;
    $total = (int) base()->query(
        "SELECT COUNT(*) FROM articulos WHERE estado = 'publicado'"
    )->fetchColumn();
    $paginas = max(1, (int) ceil($total / $porPagina));
    if ($pagina < 1) {
        $pagina = 1;
    }
    if ($pagina > $paginas) {
        $pagina = $paginas;
    }

    $desde = ($pagina - 1) * $porPagina;
    $consulta = base()->prepare(
        'SELECT id, titulo, publicado_en,
            SUBSTRING(cuerpo, 1, ' . (int) ARTICULOS_RECORTE . ') AS recorte,
            REGEXP_SUBSTR(cuerpo, ?) AS linea_imagen
         FROM articulos
         WHERE estado = \'publicado\'
         ORDER BY publicado_en DESC, id DESC
         LIMIT ' . (int) $porPagina . ' OFFSET ' . (int) $desde
    );
    $consulta->execute([patronLineaImagenArticulo()]);

    return [
        'articulos' => $consulta->fetchAll(),
        'total' => $total,
        'pagina' => $pagina,
        'paginas' => $paginas,
    ];
}

function listarArticulosAdmin(): array
{
    $consulta = base()->query(
        'SELECT id, titulo, estado, publicado_en, SUBSTRING(cuerpo, 1, ' . (int) ARTICULOS_RECORTE . ') AS recorte
         FROM articulos
         ORDER BY publicado_en DESC, id DESC'
    );

    return $consulta->fetchAll();
}

function obtenerArticulo(int $id, bool $soloPublicado = false): ?array
{
    if ($id < 1) {
        return null;
    }

    $sql = 'SELECT id, titulo, cuerpo, estado, publicado_en FROM articulos WHERE id = ?';
    if ($soloPublicado) {
        $sql .= " AND estado = 'publicado'";
    }

    $consulta = base()->prepare($sql);
    $consulta->execute([$id]);
    $articulo = $consulta->fetch();

    return $articulo ?: null;
}

function guardarArticulo(?int $id, string $titulo, string $cuerpo, string $estado): array
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

    if ($estado !== 'borrador' && $estado !== 'publicado') {
        $errores[] = 'Elige si el artículo queda en borrador o publicado.';
    }

    if ($errores !== []) {
        return ['errores' => $errores, 'id' => $id];
    }

    if ($id === null) {
        $consulta = base()->prepare(
            'INSERT INTO articulos (titulo, cuerpo, estado) VALUES (?, ?, ?)'
        );
        $consulta->execute([$titulo, $cuerpo, $estado]);

        return ['errores' => [], 'id' => (int) base()->lastInsertId()];
    }

    if (obtenerArticulo($id) === null) {
        return ['errores' => ['Ese artículo ya no existe.'], 'id' => null];
    }

    $consulta = base()->prepare(
        'UPDATE articulos SET titulo = ?, cuerpo = ?, estado = ? WHERE id = ?'
    );
    $consulta->execute([$titulo, $cuerpo, $estado, $id]);

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

function directorioImagenesArticulo(): string
{
    return dirname(__DIR__) . '/public/img/articulos';
}

function guardarImagenArticulo(?array $archivo): array
{
    if ($archivo === null || !isset($archivo['error']) || is_array($archivo['error'])) {
        return ['ok' => false, 'error' => 'Elige una imagen JPEG, PNG o WebP.'];
    }

    $codigo = (int) $archivo['error'];
    if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'La imagen puede pesar hasta 5 MB.'];
    }
    if ($codigo === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Elige una imagen JPEG, PNG o WebP.'];
    }
    if ($codigo !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'No se pudo subir la imagen.'];
    }

    $temporal = (string) ($archivo['tmp_name'] ?? '');
    if ($temporal === '' || !is_uploaded_file($temporal)) {
        return ['ok' => false, 'error' => 'No se pudo subir la imagen.'];
    }

    $revisada = revisarImagenArticulo($temporal);
    if (!$revisada['ok']) {
        return $revisada;
    }

    $directorio = directorioImagenesArticulo();
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        return ['ok' => false, 'error' => 'No se pudo guardar la imagen.'];
    }

    $destino = '';
    $nombre = '';
    for ($intento = 0; $intento < 5; $intento++) {
        $candidato = bin2hex(random_bytes(16)) . '.' . $revisada['extension'];
        $ruta = $directorio . DIRECTORY_SEPARATOR . $candidato;
        if (!is_file($ruta)) {
            $destino = $ruta;
            $nombre = $candidato;
            break;
        }
    }

    if ($destino === '' || $nombre === '') {
        return ['ok' => false, 'error' => 'No se pudo guardar la imagen.'];
    }

    if (!move_uploaded_file($temporal, $destino)) {
        return ['ok' => false, 'error' => 'No se pudo guardar la imagen.'];
    }

    $confirmada = revisarImagenArticulo($destino);
    if (!$confirmada['ok']) {
        unlink($destino);

        return ['ok' => false, 'error' => 'No se pudo guardar la imagen.'];
    }

    $url = '/img/articulos/' . $nombre;
    if (lineaImagenArticulo('![Imagen](' . $url . ')') === null) {
        unlink($destino);

        return ['ok' => false, 'error' => 'No se pudo guardar la imagen.'];
    }

    return ['ok' => true, 'url' => $url];
}

function revisarImagenArticulo(string $ruta): array
{
    if (!is_file($ruta)) {
        return ['ok' => false, 'error' => 'No se pudo revisar la imagen.'];
    }

    $tamano = filesize($ruta);
    if ($tamano === false || $tamano < 1) {
        return ['ok' => false, 'error' => 'Elige una imagen JPEG, PNG o WebP.'];
    }
    if ($tamano > IMAGEN_ARTICULO_MAX_BYTES) {
        return ['ok' => false, 'error' => 'La imagen puede pesar hasta 5 MB.'];
    }

    $muestra = file_get_contents($ruta, false, null, 0, 512);
    if (!is_string($muestra) || preg_match('/<svg[\s>]/i', $muestra)) {
        return ['ok' => false, 'error' => 'No se aceptan archivos SVG.'];
    }

    if (!class_exists(finfo::class)) {
        return ['ok' => false, 'error' => 'No se pudo revisar la imagen.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
    if (!is_string($mime)) {
        return ['ok' => false, 'error' => 'Usa una imagen JPEG, PNG o WebP.'];
    }
    if (str_contains(strtolower($mime), 'svg')) {
        return ['ok' => false, 'error' => 'No se aceptan archivos SVG.'];
    }

    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($permitidos[$mime])) {
        return ['ok' => false, 'error' => 'Usa una imagen JPEG, PNG o WebP.'];
    }

    $info = @getimagesize($ruta);
    if ($info === false) {
        return ['ok' => false, 'error' => 'Usa una imagen JPEG, PNG o WebP.'];
    }

    $tipos = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
    ];
    if (defined('IMAGETYPE_WEBP')) {
        $tipos[IMAGETYPE_WEBP] = 'image/webp';
    }

    $tipo = (int) ($info[2] ?? 0);
    $mimeImagen = (string) ($info['mime'] ?? '');
    if (!isset($tipos[$tipo]) || $tipos[$tipo] !== $mime || $mimeImagen !== $mime) {
        return ['ok' => false, 'error' => 'Usa una imagen JPEG, PNG o WebP.'];
    }

    $ancho = (int) ($info[0] ?? 0);
    $alto = (int) ($info[1] ?? 0);
    if ($ancho < 1 || $alto < 1 || $ancho > IMAGEN_ARTICULO_MAX_LADO || $alto > IMAGEN_ARTICULO_MAX_LADO) {
        return ['ok' => false, 'error' => 'La imagen puede medir hasta 3000 × 3000 píxeles.'];
    }
    if (($ancho * $alto) > IMAGEN_ARTICULO_MAX_PIXELS) {
        return ['ok' => false, 'error' => 'La imagen puede tener hasta 12 megapíxeles.'];
    }

    return ['ok' => true, 'extension' => $permitidos[$mime]];
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

        $html .= htmlBloqueArticulo($parrafo);
    }

    return $html;
}

function htmlBloqueArticulo(string $parrafo): string
{
    $html = '';
    $lineasTexto = [];
    $volcar = static function () use (&$html, &$lineasTexto): void {
        if ($lineasTexto === []) {
            return;
        }
        $html .= '<p>' . implode('<br>', $lineasTexto) . '</p>';
        $lineasTexto = [];
    };

    foreach (explode("\n", $parrafo) as $linea) {
        $imagen = lineaImagenArticulo(trim($linea));
        if ($imagen !== null) {
            $volcar();
            $html .= htmlFiguraArticulo($imagen['alt'], $imagen['url']);
            continue;
        }
        $lineasTexto[] = marcasDeArticulo($linea);
    }
    $volcar();

    return $html;
}

function htmlFiguraArticulo(string $alt, string $url): string
{
    return '<figure><img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy" decoding="async"></figure>';
}

function patronLineaImagenArticulo(): string
{
    return '(?m)^[ \t]*!\\[[^\\]\\r\\n]{1,200}\\]\\(/img/articulos/[a-f0-9]{32}\\.(?:jpg|png|webp)\\)[ \t]*\\r?$';
}

function primeraImagenArticulo(string $fragmento): ?array
{
    if ($fragmento === '') {
        return null;
    }

    $normalizado = str_replace(["\r\n", "\r"], "\n", $fragmento);
    foreach (explode("\n", $normalizado) as $linea) {
        $imagen = lineaImagenArticulo(trim($linea));
        if ($imagen !== null) {
            return $imagen;
        }
    }

    return null;
}

function imagenListadoArticulo(array $articulo): ?array
{
    $desdeLinea = primeraImagenArticulo((string) ($articulo['linea_imagen'] ?? ''));
    if ($desdeLinea !== null) {
        return $desdeLinea;
    }

    return primeraImagenArticulo((string) ($articulo['recorte'] ?? ''));
}

function lineaImagenArticulo(string $linea): ?array
{
    if (!preg_match(
        '/^!\[([^\]\r\n]{1,200})\]\((\/img\/articulos\/[a-f0-9]{32}\.(?:jpg|png|webp))\)$/u',
        $linea,
        $coincidencia
    )) {
        return null;
    }

    $alt = trim($coincidencia[1]);
    if ($alt === '') {
        return null;
    }

    return [
        'alt' => $alt,
        'url' => $coincidencia[2],
    ];
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

            return '<a href="' . e($direccion) . '" target="_blank" rel="noopener noreferrer">' . $coincidencia[1] . '</a>';
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
    $texto = preg_replace('/!\[([^\]\n]*)\]\([^)\n]*\)/u', '$1', $texto) ?? $texto;
    $texto = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $texto) ?? $texto;
    $texto = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '$1', $texto) ?? $texto;
    $texto = preg_replace('/\[([^\[\]]+)\]\([^)]+\)/u', '$1', $texto) ?? $texto;
    $texto = preg_replace('/^- /mu', '', $texto) ?? $texto;

    return $texto;
}

function extractoArticulo(string $cuerpo): string
{
    $texto = '';
    foreach (parrafosDe($cuerpo) as $parrafo) {
        $lineas = [];
        foreach (explode("\n", $parrafo) as $linea) {
            if (lineaImagenArticulo(trim($linea)) !== null) {
                continue;
            }
            $lineas[] = $linea;
        }
        if ($lineas === []) {
            continue;
        }

        $limpio = textoSinMarcas(implode("\n", $lineas));
        $limpio = trim(preg_replace('/\s+/u', ' ', $limpio) ?? '');
        if ($limpio !== '') {
            $texto = $limpio;
            break;
        }
    }

    $maximo = 180;
    if (mb_strlen($texto) <= $maximo) {
        return $texto;
    }

    $corte = mb_substr($texto, 0, $maximo);
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
