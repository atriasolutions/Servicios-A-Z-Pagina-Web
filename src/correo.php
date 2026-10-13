<?php

declare(strict_types=1);

function resumenCita(array $cita): array
{
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $cita['fecha'], zonaHoraria());
    $fechaTexto = $fecha ? fechaLarga($fecha) : (string) $cita['fecha'];

    return [
        'servicio' => etiquetaServicio($cita['servicio'] ?? null),
        'fecha' => $fechaTexto,
        'hora' => etiquetaBloque((string) $cita['hora_inicio']),
    ];
}

function origenDelSitio(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return 'http://127.0.0.1';
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    return ($https ? 'https://' : 'http://') . $host;
}

function correoAdministrador(): ?string
{
    $correo = trim((string) (configuracion()['correo']['administrador'] ?? ''));
    if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
        error_log('El correo del administrador en config/config.php no es válido.');
        return null;
    }

    return $correo;
}

function enviarFormSubmit(array $campos): bool
{
    $administrador = correoAdministrador();
    if ($administrador === null || !function_exists('curl_init')) {
        error_log('No se pudo preparar el aviso por FormSubmit.');
        return false;
    }

    $cuerpo = json_encode($campos, JSON_UNESCAPED_UNICODE);
    if ($cuerpo === false) {
        return false;
    }

    $url = 'https://formsubmit.co/ajax/' . rawurlencode($administrador);
    $origen = origenDelSitio();
    $canal = curl_init($url);
    if ($canal === false) {
        return false;
    }

    $opciones = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $cuerpo,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Origin: ' . $origen,
        ],
        CURLOPT_REFERER => $origen . '/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT => 'ServiciosContablesAZ/1.0',
    ];
    if (defined('CURLPROTO_HTTPS')) {
        $opciones[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        $opciones[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    curl_setopt_array($canal, $opciones);

    $respuesta = curl_exec($canal);
    $final = (string) curl_getinfo($canal, CURLINFO_EFFECTIVE_URL);
    $codigo = (int) curl_getinfo($canal, CURLINFO_RESPONSE_CODE);
    curl_close($canal);

    $host = strtolower((string) (parse_url($final, PHP_URL_HOST) ?? ''));
    if (!is_string($respuesta) || $codigo < 200 || $codigo >= 300 || !in_array($host, ['formsubmit.co', 'www.formsubmit.co'], true)) {
        error_log('FormSubmit no respondió correctamente.');
        return false;
    }

    $datos = json_decode($respuesta, true);
    $exito = is_array($datos) ? ($datos['success'] ?? false) : false;
    if ($exito === true || $exito === 1 || $exito === '1' || $exito === 'true') {
        return true;
    }

    $mensaje = is_array($datos) ? (string) ($datos['message'] ?? '') : '';
    error_log('FormSubmit rechazó el aviso.' . ($mensaje !== '' ? ' ' . $mensaje : ''));
    return false;
}

function notificarReserva(array $cita): bool
{
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];

    return enviarFormSubmit([
        'name' => (string) $cita['cliente_nombre'],
        'email' => (string) $cita['cliente_email'],
        '_replyto' => (string) $cita['cliente_email'],
        '_subject' => 'Nueva reserva: ' . $resumen['fecha'] . ' a las ' . $resumen['hora'],
        '_template' => 'box',
        '_captcha' => 'false',
        '_autoresponse' => 'Hola ' . $cita['cliente_nombre'] . '. Tu reserva en ' . $sitio
            . ' quedó confirmada. Servicio: ' . $resumen['servicio']
            . '. Fecha: ' . $resumen['fecha'] . '. Hora: ' . $resumen['hora'] . '.',
        'Nombre' => (string) $cita['cliente_nombre'],
        'Correo' => (string) $cita['cliente_email'],
        'Teléfono' => (string) $cita['cliente_telefono'],
        'Servicio' => $resumen['servicio'],
        'Fecha' => $resumen['fecha'],
        'Hora' => $resumen['hora'],
    ]);
}

function notificarCancelacion(array $cita): bool
{
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];

    return enviarFormSubmit([
        'name' => (string) $cita['cliente_nombre'],
        'email' => (string) $cita['cliente_email'],
        '_replyto' => (string) $cita['cliente_email'],
        '_subject' => 'Reserva cancelada: ' . $resumen['fecha'] . ' a las ' . $resumen['hora'],
        '_template' => 'box',
        '_captcha' => 'false',
        '_autoresponse' => 'Hola ' . $cita['cliente_nombre'] . '. Tu reserva en ' . $sitio
            . ' fue cancelada. Servicio: ' . $resumen['servicio']
            . '. Fecha: ' . $resumen['fecha'] . '. Hora: ' . $resumen['hora'] . '.',
        'Nombre' => (string) $cita['cliente_nombre'],
        'Correo' => (string) $cita['cliente_email'],
        'Teléfono' => (string) $cita['cliente_telefono'],
        'Servicio' => $resumen['servicio'],
        'Fecha' => $resumen['fecha'],
        'Hora' => $resumen['hora'],
    ]);
}
