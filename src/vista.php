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

function abrirPagina(string $titulo): void
{
    $usuario = usuarioActual();
    $nombreSitio = (string) configuracion()['sitio']['nombre'];
    $aviso = tomarAviso();
    $actual = basename($_SERVER['SCRIPT_NAME'] ?? '');

    echo '<!DOCTYPE html><html lang="es"><head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($titulo) . ' · ' . e($nombreSitio) . '</title>';
    echo '<link rel="stylesheet" href="css/estilos.css">';
    echo '</head><body>';
    echo '<header class="cabecera">';
    echo '<a class="marca" href="index.php">' . e($nombreSitio) . '</a>';
    echo '<nav class="nav" aria-label="Principal">';

    $enlace = static function (string $archivo, string $texto) use ($actual): void {
        $marca = $actual === $archivo ? ' aria-current="page"' : '';
        echo '<a href="' . e($archivo) . '"' . $marca . '>' . e($texto) . '</a>';
    };

    $enlace('index.php', 'Inicio');
    if ($usuario && $usuario['rol'] === 'cliente') {
        $enlace('horarios.php', 'Horarios');
        $enlace('mis-citas.php', 'Mis asesorías');
    } elseif ($usuario && $usuario['rol'] === 'anfitrion') {
        $enlace('agenda.php', 'Agenda');
    } else {
        $enlace('login.php', 'Entrar');
        $enlace('registro.php', 'Crear cuenta');
    }

    if ($usuario) {
        echo '<form method="post" action="logout.php">';
        echo campoCsrf();
        echo '<button type="submit">Salir</button>';
        echo '</form>';
    }

    echo '</nav></header>';
    echo '<main class="contenido">';

    if ($aviso) {
        $clase = ($aviso['tipo'] ?? '') === 'ok' ? 'aviso ok' : 'aviso error';
        echo '<p class="' . e($clase) . '" role="status">' . e((string) $aviso['mensaje']) . '</p>';
    }
}

function cerrarPagina(): void
{
    echo '</main>';
    echo '<script src="js/agenda.js"></script>';
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
