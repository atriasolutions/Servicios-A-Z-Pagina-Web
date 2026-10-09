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

    return 'Cada asesoría dura ' . $minutos . ' minutos, todos los días, de ' . $desde . ' a ' . $hasta . '.';
}

// Trazos dibujados para este sitio. No provienen de un set de terceros.
function icono(string $nombre): string
{
    $trazos = [
        'inicio' => '<path d="M2.2 16.5 5.6 3.5 9 16.5M3.5 11.6h4.2M11.2 4h6.6L11.2 16.5h6.6"/>',
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
    etiquetasVite();
    echo $portada ? '</head><body class="portada">' : '</head><body>';
    echo '<div class="barra-superior" aria-hidden="true"></div>';
    echo '<header class="cabecera">';
    echo '<a class="marca" href="index.php">' . e($nombreSitio) . '</a>';
    echo '<nav class="menu" aria-label="Principal">';

    $enlace = static function (string $archivo, string $texto, string $dibujar) use ($actual): void {
        $marca = $actual === $archivo ? ' aria-current="page"' : '';
        echo '<a href="' . e($archivo) . '"' . $marca . '>' . icono($dibujar) . '<span>' . e($texto) . '</span></a>';
    };

    $enlace('index.php', 'Inicio', 'inicio');
    if ($usuario && $usuario['rol'] === 'cliente') {
        $enlace('horarios.php', 'Horarios', 'horarios');
        $enlace('mis-citas.php', 'Mis asesorías', 'asesorias');
    } elseif ($usuario && $usuario['rol'] === 'anfitrion') {
        $enlace('agenda.php', 'Agenda', 'agenda');
    } else {
        $enlace('login.php', 'Entrar', 'entrar');
        $enlace('registro.php', 'Crear cuenta', 'cuenta');
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
