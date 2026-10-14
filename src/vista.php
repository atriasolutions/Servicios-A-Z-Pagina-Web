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
    $agenda = configuracion()['agenda'];
    $desde = sprintf('%02d:00', (int) $agenda['hora_inicio']);
    $hasta = sprintf('%02d:00', (int) $agenda['hora_fin']);
    $minutos = (int) $agenda['duracion_minutos'];

    return 'Cada asesoría dura ' . $minutos . ' minutos, de lunes a viernes, de ' . $desde . ' a ' . $hasta . '.';
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
    echo $portada ? '</head><body class="portada">' : '</head><body>';
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
    $enlace('articulos.php', 'Artículos', 'articulos', ['articulo.php', 'articulo-editar.php']);
    if ($usuario && $usuario['rol'] === 'anfitrion') {
        $enlace('agenda.php', 'Agenda', 'agenda');
    } else {
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

    echo '</main>';
    echo '<footer class="pie"><p>' . e($nombreSitio) . '</p></footer>';
    echo '</body></html>';
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
