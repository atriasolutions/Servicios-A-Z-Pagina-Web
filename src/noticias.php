<?php

declare(strict_types=1);

function noticiasTributarias(): array
{
    $ajustes = configuracion()['noticias'] ?? [];
    $rss = is_array($ajustes) ? (string) ($ajustes['rss'] ?? '') : '';
    $horas = is_array($ajustes) ? max(1, (int) ($ajustes['cache_horas'] ?? 4)) : 4;
    $limite = is_array($ajustes) ? max(1, min(9, (int) ($ajustes['limite'] ?? 9))) : 9;
    $cache = dirname(__DIR__) . '/datos-cache/noticias-sii.json';
    $guardada = leerCacheNoticias($cache);

    if ($guardada !== null && (time() - $guardada['obtenido']) < ($horas * 3600)) {
        return array_slice($guardada['items'], 0, $limite);
    }

    $nuevas = descargarNoticias($rss);
    if ($nuevas !== null) {
        guardarCacheNoticias($cache, $nuevas);

        return array_slice($nuevas, 0, $limite);
    }

    if ($guardada !== null) {
        return array_slice($guardada['items'], 0, $limite);
    }

    return [];
}

function leerCacheNoticias(string $ruta): ?array
{
    if (!is_file($ruta)) {
        return null;
    }

    $datos = json_decode((string) file_get_contents($ruta), true);
    if (!is_array($datos) || !isset($datos['obtenido'], $datos['items']) || !is_array($datos['items'])) {
        return null;
    }

    return [
        'obtenido' => (int) $datos['obtenido'],
        'items' => $datos['items'],
    ];
}

function guardarCacheNoticias(string $ruta, array $items): void
{
    $directorio = dirname($ruta);
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        return;
    }

    $json = json_encode(
        ['obtenido' => time(), 'items' => $items],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        return;
    }

    $temporal = $ruta . '.tmp';
    if (file_put_contents($temporal, $json, LOCK_EX) === false) {
        return;
    }

    rename($temporal, $ruta);
}

function descargarNoticias(string $url): ?array
{
    $xml = cuerpoRss($url);
    if ($xml === null) {
        return null;
    }

    return leerItemsRss($xml);
}

function cuerpoRss(string $url): ?string
{
    if (!enlaceDelSii($url) || !function_exists('curl_init')) {
        return null;
    }

    $canal = curl_init($url);
    if ($canal === false) {
        return null;
    }

    $opciones = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT => 'ServiciosContablesAZ/1.0',
    ];
    if (defined('CURLPROTO_HTTPS')) {
        $opciones[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        $opciones[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    curl_setopt_array($canal, $opciones);

    $cuerpo = curl_exec($canal);
    $final = (string) curl_getinfo($canal, CURLINFO_EFFECTIVE_URL);
    $codigo = (int) curl_getinfo($canal, CURLINFO_RESPONSE_CODE);
    curl_close($canal);

    if (!is_string($cuerpo) || $cuerpo === '' || $codigo < 200 || $codigo >= 300 || !enlaceDelSii($final)) {
        return null;
    }

    return $cuerpo;
}

function enlaceDelSii(string $url): bool
{
    $partes = parse_url($url);
    if (!is_array($partes) || strtolower((string) ($partes['scheme'] ?? '')) !== 'https') {
        return false;
    }

    $host = strtolower((string) ($partes['host'] ?? ''));

    return $host === 'www.sii.cl' || $host === 'sii.cl';
}

function leerItemsRss(string $xml): ?array
{
    if (preg_match('/encoding=["\']iso-8859-1["\']/i', substr($xml, 0, 220)) === 1) {
        $convertido = mb_convert_encoding($xml, 'UTF-8', 'ISO-8859-1');
        if (is_string($convertido) && $convertido !== '') {
            $xml = preg_replace('/encoding=["\']iso-8859-1["\']/i', 'encoding="UTF-8"', $convertido, 1) ?? $convertido;
        }
    }

    $documento = new DOMDocument();
    $anteriores = libxml_use_internal_errors(true);
    $cargado = $documento->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($anteriores);
    if (!$cargado) {
        return null;
    }

    $items = [];
    foreach ($documento->getElementsByTagName('item') as $nodo) {
        if (!$nodo instanceof DOMElement || count($items) >= 9) {
            break;
        }

        $titulo = textoDeItem($nodo, 'title');
        $enlace = textoDeItem($nodo, 'link');
        if ($titulo === '' || !enlaceDelSii($enlace)) {
            continue;
        }

        $categoria = textoDeItem($nodo, 'category');
        $items[] = [
            'titulo' => $titulo,
            'fecha' => fechaDeNoticia(textoDeItem($nodo, 'pubDate')),
            'extracto' => recortarNoticia(textoDeItem($nodo, 'description')),
            'enlace' => $enlace,
            'categoria' => $categoria !== '' ? $categoria : 'Actualidad tributaria',
        ];
    }

    return $items;
}

function textoDeItem(DOMElement $item, string $etiqueta): string
{
    $nodos = $item->getElementsByTagName($etiqueta);
    if ($nodos->length === 0 || $nodos->item(0) === null) {
        return '';
    }

    $texto = html_entity_decode(strip_tags($nodos->item(0)->textContent ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

    return repararTexto(trim($texto));
}

function repararTexto(string $texto): string
{
    if ($texto === '') {
        return '';
    }

    $salida = '';
    $largo = mb_strlen($texto, 'UTF-8');
    $indice = 0;
    while ($indice < $largo) {
        $codigo = mb_ord(mb_substr($texto, $indice, 1, 'UTF-8'), 'UTF-8');
        if ($codigo < 0x80 || $codigo > 0xFF) {
            $salida .= mb_substr($texto, $indice, 1, 'UTF-8');
            $indice++;
            continue;
        }

        $bytes = '';
        $inicio = $indice;
        while ($indice < $largo) {
            $codigo = mb_ord(mb_substr($texto, $indice, 1, 'UTF-8'), 'UTF-8');
            if ($codigo < 0x80 || $codigo > 0xFF) {
                break;
            }
            $bytes .= chr($codigo);
            $indice++;
        }

        if (mb_check_encoding($bytes, 'UTF-8')) {
            $salida .= $bytes;
            continue;
        }

        $salida .= mb_substr($texto, $inicio, $indice - $inicio, 'UTF-8');
    }

    return $salida;
}

function fechaDeNoticia(string $publicacion): string
{
    $publicacion = trim($publicacion, " ,\t");
    if ($publicacion === '') {
        return '';
    }

    $fecha = DateTimeImmutable::createFromFormat('j M Y H:i:s T', $publicacion);
    if (!$fecha) {
        try {
            $fecha = new DateTimeImmutable($publicacion);
        } catch (Exception $e) {
            return '';
        }
    }

    $meses = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    return $fecha->format('j') . ' de ' . $meses[(int) $fecha->format('n')] . ' de ' . $fecha->format('Y');
}

function recortarNoticia(string $texto): string
{
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
