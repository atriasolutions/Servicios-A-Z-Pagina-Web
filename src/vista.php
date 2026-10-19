<?php

declare(strict_types=1);

function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function aviso(string $tipo, string $mensaje): void
{
    $_SESSION['aviso'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomarAviso(): ?array
{
    if (empty($_SESSION['aviso']) || !is_array($_SESSION['aviso'])) {
        return null;
    }

    $aviso = $_SESSION['aviso'];
    unset($_SESSION['aviso']);

    return $aviso;
}

function etiquetaBloque(string $horaInicio): string
{
    $inicio = DateTimeImmutable::createFromFormat('H:i:s', $horaInicio, zonaHoraria());
    if (!$inicio) {
        return $horaInicio;
    }

    $minutos = (int) configuracion()['agenda']['duracion_minutos'];
    $fin = $inicio->modify('+' . $minutos . ' minutes');

    return $inicio->format('H:i') . ' – ' . $fin->format('H:i');
}

function fechaLarga(DateTimeInterface $fecha): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    return $dias[(int) $fecha->format('w')] . ' '
        . $fecha->format('j') . ' de ' . $meses[(int) $fecha->format('n')];
}

function fechaCorta(DateTimeInterface $fecha): string
{
    $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

    return $dias[(int) $fecha->format('w')];
}

function textoHorario(): string
{
    return 'Las asesorías se reservan en los horarios libres que publica la agenda.';
}

// Trazos dibujados para este sitio. No provienen de un set de terceros.
function icono(string $nombre): string
{
    $trazos = [
        'inicio' => '<path d="M3.2 9.1 10 3.4l6.8 5.7"/><path d="M5.1 8.2v8.2h9.8V8.2"/><path d="M8.5 16.4v-4h3v4"/>',
        'entrar' => '<rect x="8" y="4" width="8.5" height="12" rx="2.2"/><path d="M3 10h7"/>',
        'cuenta' => '<rect x="2.5" y="6" width="10" height="11" rx="2.2"/><path d="M16 2.5v4M14 4.5h4"/>',
        'horarios' => '<rect x="3" y="3.6" width="14" height="2.6" rx="1.1"/>'
            . '<rect x="3" y="8.7" width="9.2" height="2.6" rx="1.1"/>'
            . '<rect class="marca-llena" x="13.1" y="8.7" width="3.9" height="2.6" rx="1.1"/>'
            . '<rect x="3" y="13.8" width="14" height="2.6" rx="1.1"/>',
        'asesorias' => '<circle cx="10" cy="4.5" r="1.7"/><path d="M10 6.2v7.6"/><circle cx="10" cy="15.5" r="1.7"/>',
        'agenda' => '<rect x="3.2" y="3.2" width="5.6" height="5.6" rx="1.3"/>'
            . '<rect class="marca-llena" x="11.2" y="3.2" width="5.6" height="5.6" rx="1.3"/>'
            . '<rect x="3.2" y="11.2" width="5.6" height="5.6" rx="1.3"/>'
            . '<rect x="11.2" y="11.2" width="5.6" height="5.6" rx="1.3"/>',
        'salir' => '<rect x="3" y="4" width="8.5" height="12" rx="2.2"/><path d="M10 10h7"/>',
        'tributaria' => '<rect x="4.2" y="2.4" width="11.6" height="15.2" rx="1.4"/>'
            . '<path d="M7 7h6M7 10h6M7 13h3.2"/>',
        'financiera' => '<path d="M3.2 16.4h13.6M4.4 16.4V9.4M8.6 16.4V6.2M12.8 16.4V11.2M17 16.4V4.2"/>',
        'remuneraciones' => '<circle cx="6.8" cy="5.4" r="1.8"/>'
            . '<path d="M3.2 15.8c.35-2.7 1.85-4.1 3.6-4.1s3.25 1.4 3.6 4.1"/>'
            . '<circle cx="14.1" cy="6.2" r="1.45"/>'
            . '<path d="M11.3 15.8c.25-2.05 1.3-3.2 2.8-3.2s2.55 1.15 2.8 3.2"/>',
        'whatsapp' => '<path d="M4.2 4.4h8.8a2.2 2.2 0 0 1 2.2 2.2v5.2a2.2 2.2 0 0 1-2.2 2.2H8.4L5.2 16.6v-2.6H4.2A2.2 2.2 0 0 1 2 11.8V6.6a2.2 2.2 0 0 1 2.2-2.2z"/>',
        'chat' => '<path d="M4.2 4.4h8.8a2.2 2.2 0 0 1 2.2 2.2v5.2a2.2 2.2 0 0 1-2.2 2.2H8.4L5.2 16.6v-2.6H4.2A2.2 2.2 0 0 1 2 11.8V6.6a2.2 2.2 0 0 1 2.2-2.2z"/>',
        'direccion' => '<path d="M10 17.6s4.8-4.3 4.8-7.8a4.8 4.8 0 0 0-9.6 0c0 3.5 4.8 7.8 4.8 7.8z"/>'
            . '<circle cx="10" cy="9.7" r="1.6"/>',
        'correo' => '<rect x="2.4" y="4.6" width="15.2" height="10.8" rx="1.6"/>'
            . '<path d="M3.2 5.6 10 10.8l6.8-5.2"/>',
        'articulos' => '<path d="M5.4 2.6h6.1L15.8 6.9v9.3a1.2 1.2 0 0 1-1.2 1.2H5.4a1.2 1.2 0 0 1-1.2-1.2V3.8a1.2 1.2 0 0 1 1.2-1.2z"/>'
            . '<path d="M11.2 2.8v4.1h4.2M6.4 10h7.2M6.4 12.8h7.2M6.4 15.6h4.4"/>',
        'negrita' => '<path d="M6.2 3.4v13.2M6.2 3.4h5.2a3.1 3.1 0 0 1 0 6.2H6.2"/>'
            . '<path d="M6.2 9.6h6.1a3.4 3.4 0 0 1 0 6.8H6.2"/>',
        'cursiva' => '<path d="M12.2 3.2H8M13 16.8H8.8M11.5 3.4 9.1 16.6"/>',
        'lista' => '<circle class="marca-llena" cx="4.2" cy="5" r="1.05"/>'
            . '<circle class="marca-llena" cx="4.2" cy="10" r="1.05"/>'
            . '<circle class="marca-llena" cx="4.2" cy="15" r="1.05"/>'
            . '<path d="M7.6 5h8.6M7.6 10h8.6M7.6 15h8.6"/>',
        'enlace' => '<path d="M8.5 11.5 7.1 12.9a2.5 2.5 0 0 1-3.5-3.5l1.4-1.4"/>'
            . '<path d="M11.5 8.5 12.9 7.1a2.5 2.5 0 0 1 3.5 3.5l-1.4 1.4"/>'
            . '<path d="M8.7 11.3 11.3 8.7"/>',
    ];

    return '<svg class="icono" viewBox="0 0 20 20" aria-hidden="true" focusable="false">'
        . ($trazos[$nombre] ?? '')
        . '</svg>';
}

function abrirPagina(string $titulo, bool $portada = false): void
{
    $usuario = usuarioActual();
    $nombreSitio = (string) configuracion()['sitio']['nombre'];
    $aviso = tomarAviso();
    $actual = basename($_SERVER['SCRIPT_NAME'] ?? '');

    echo '<!DOCTYPE html><html lang="es"><head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($titulo) . ' · ' . e($nombreSitio) . '</title>';
    echo '<link rel="icon" type="image/png" sizes="128x128" href="/img/favicon-az.png">';
    etiquetasVite();
    if ($portada) {
        echo '<script>if(window.IntersectionObserver&&!window.matchMedia("(prefers-reduced-motion: reduce)").matches){document.documentElement.classList.add("con-revelado");}</script>';
    }
    if ($portada) {
        echo '</head><body class="portada">';
    } elseif ($actual === 'horarios.php') {
        echo '</head><body class="fondo-reserva">';
    } elseif (in_array($actual, ['articulos.php', 'articulo.php', 'login.php'], true)) {
        echo '</head><body class="fondo-articulos">';
    } else {
        echo '</head><body>';
    }
    echo '<div class="barra-superior" aria-hidden="true"></div>';
    echo '<header class="cabecera">';
    echo '<a class="marca" href="index.php" aria-label="' . e($nombreSitio) . '">';
    echo '<img src="/img/logo-az.png" width="747" height="631" alt="' . e($nombreSitio) . '">';
    echo '</a>';
    echo '<nav class="menu" aria-label="Principal">';

    $enlace = static function (string $archivo, string $texto, string $dibujar, array $tambien = []) use ($actual): void {
        $marca = ($actual === $archivo || in_array($actual, $tambien, true)) ? ' aria-current="page"' : '';
        echo '<a href="' . e($archivo) . '"' . $marca . '>' . icono($dibujar) . '<span>' . e($texto) . '</span></a>';
    };

    $enlace('index.php', 'Inicio', 'inicio');
    if ($usuario && $usuario['rol'] === 'anfitrion') {
        $enlace('articulos-admin.php', 'Blog', 'articulos', ['articulo-editar.php']);
        $enlace('agenda.php', 'Agenda', 'agenda');
    } else {
        $enlace('articulos.php', 'Artículos', 'articulos', ['articulo.php']);
        $enlace('horarios.php', 'Reservar', 'horarios');
        $enlace('login.php', 'Administración', 'entrar');
    }

    if ($usuario) {
        echo '<form method="post" action="logout.php">';
        echo campoCsrf();
        echo '<button type="submit">' . icono('salir') . '<span>Salir</span></button>';
        echo '</form>';
    }

    echo '</nav></header>';
    echo $portada ? '<main class="lienzo">' : '<main class="contenido">';

    if ($aviso) {
        $clase = ($aviso['tipo'] ?? '') === 'ok' ? 'aviso ok' : 'aviso error';
        echo '<p class="' . e($clase) . '" role="status">' . e((string) $aviso['mensaje']) . '</p>';
    }
}

function cerrarPagina(): void
{
    $nombreSitio = (string) configuracion()['sitio']['nombre'];

    $actual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $paginasAdmin = ['agenda.php', 'chat.php', 'articulos-admin.php', 'articulo-editar.php'];

    echo '</main>';
    if (in_array($actual, $paginasAdmin, true)) {
        echo '<footer class="pie"><p>' . e($nombreSitio) . '</p></footer>';
    } else {
        echo piePublico($nombreSitio);
    }
    echo accesoWhatsApp();
    echo '</body></html>';
}

function redesSociales(): array
{
    $redes = configuracion()['sitio']['redes'] ?? [];
    if (!is_array($redes)) {
        return [];
    }

    $nombres = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'x' => 'X',
        'twitter' => 'X',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
    ];
    $lista = [];
    foreach ($redes as $clave => $valor) {
        if (is_array($valor)) {
            $url = trim((string) ($valor['url'] ?? ''));
            $nombre = trim((string) ($valor['nombre'] ?? (is_string($clave) ? $clave : '')));
        } else {
            $url = trim((string) $valor);
            $nombre = is_string($clave) ? $clave : '';
        }

        $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($url === '' || !in_array($esquema, ['http', 'https'], true) || parse_url($url, PHP_URL_HOST) === null) {
            continue;
        }

        if ($nombre === '') {
            $nombre = (string) parse_url($url, PHP_URL_HOST);
        }
        $lista[] = ['nombre' => $nombres[strtolower($nombre)] ?? ucfirst($nombre), 'url' => $url];
    }

    return $lista;
}

function piePublico(string $nombreSitio): string
{
    $html = '<footer class="pie pie-publico">';
    $html .= '<div class="pie-inner">';

    $html .= '<div class="pie-columna pie-marca">';
    $html .= '<p class="pie-nombre">' . e($nombreSitio) . '</p>';
    $html .= '<p>Asesoría contable, laboral y tributaria para pymes y emprendedores.</p>';
    $redes = redesSociales();
    if ($redes !== []) {
        $html .= '<ul class="pie-redes" aria-label="Redes sociales">';
        foreach ($redes as $red) {
            $html .= '<li><a href="' . e($red['url']) . '" target="_blank" rel="noopener noreferrer">' . e($red['nombre']) . '</a></li>';
        }
        $html .= '</ul>';
    }
    $html .= '</div>';

    $html .= '<nav class="pie-columna" aria-label="Pie de página">';
    $html .= '<h2>Navegación</h2><ul>';
    $html .= '<li><a href="index.php">Inicio</a></li>';
    $html .= '<li><a href="index.php#servicios">Servicios</a></li>';
    $html .= '<li><a href="index.php#nosotros">Nosotros</a></li>';
    $html .= '<li><a href="articulos.php">Artículos</a></li>';
    $html .= '<li><a href="horarios.php">Reservar una hora</a></li>';
    $html .= '<li><a href="index.php#contacto">Contacto</a></li>';
    $html .= '</ul></nav>';

    $html .= '<div class="pie-columna">';
    $html .= '<h2>Servicios</h2><ul>';
    foreach (servicios() as $clave => $nombre) {
        $html .= '<li><a href="horarios.php?servicio=' . e($clave) . '">' . e($nombre) . '</a></li>';
    }
    $html .= '</ul></div>';

    $html .= '<div class="pie-columna pie-datos">';
    $html .= '<h2>Contacto</h2><ul>';
    $html .= '<li>' . icono('whatsapp') . '<a href="https://wa.me/56974531422">+569 74531422</a></li>';
    $html .= '<li>' . icono('correo') . '<a href="mailto:servicioscontablesaz@gmail.com">servicioscontablesaz@gmail.com</a></li>';
    $html .= '<li>' . icono('direccion') . '<span>La Serena</span></li>';
    $html .= '</ul></div>';

    $html .= '</div>';
    $html .= '<p class="pie-legal">' . e($nombreSitio) . '</p>';
    $html .= '</footer>';

    return $html;
}

function marcaWhatsApp(): string
{
    return '<svg class="marca-whatsapp" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>'
        . '</svg>';
}

function enlaceWhatsApp(): string
{
    return 'https://wa.me/56854650115?text=' . rawurlencode('Hola, quisiera hacer una consulta.');
}

function accesoWhatsApp(): string
{
    $destino = enlaceWhatsApp();

    return '<a class="whatsapp-boton" href="' . e($destino) . '" target="_blank" rel="noopener noreferrer">'
        . marcaWhatsApp() . '<span>WhatsApp</span></a>';
}

function erroresDe(array $errores): void
{
    if ($errores === []) {
        return;
    }

    echo '<div class="aviso error" role="alert"><ul>';
    foreach ($errores as $error) {
        echo '<li>' . e($error) . '</li>';
    }
    echo '</ul></div>';
}
