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
    $nombre = strtolower((string) preg_replace('/:\d+$/', '', $host));
    if ($host === '' || in_array($nombre, ['127.0.0.1', 'localhost', '[::1]'], true)) {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        return ($https ? 'https://' : 'http://') . ($host === '' ? '127.0.0.1' : $host);
    }

    $publica = rtrim(trim((string) (configuracion()['sitio']['url'] ?? '')), '/');
    if (preg_match('#^https://[a-z0-9.-]+$#i', $publica) === 1) {
        return $publica;
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

function enlaceDeConfirmacion(array $cita): string
{
    $consulta = http_build_query([
        'cita' => (int) $cita['id'],
        'token' => (string) ($cita['token'] ?? ''),
    ]);

    return origenDelSitio() . '/confirmar.php?' . $consulta;
}

function datosDeCita(array $cita): string
{
    $resumen = resumenCita($cita);

    return 'Servicio: ' . $resumen['servicio'] . "\n"
        . 'Fecha: ' . $resumen['fecha'] . "\n"
        . 'Hora: ' . $resumen['hora'];
}

function enviarCorreo(string $destino, string $asunto, string $texto, ?string $responderA = null): bool
{
    $desde = correoAdministrador();
    $clave = str_replace(' ', '', trim((string) (configuracion()['correo']['smtp_clave'] ?? '')));
    if ($desde === null || $clave === '' || filter_var($destino, FILTER_VALIDATE_EMAIL) === false) {
        error_log('Falta la contraseña de aplicación o el destinatario no es válido.');
        return false;
    }

    $contexto = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $canal = stream_socket_client(
        'ssl://smtp.gmail.com:465',
        $errorNumero,
        $errorTexto,
        8,
        STREAM_CLIENT_CONNECT,
        $contexto
    );
    if ($canal === false) {
        error_log('No se pudo conectar con el servidor de correo.');
        return false;
    }

    stream_set_timeout($canal, 8);
    $saludo = smtpLeer($canal);
    $enviado = str_starts_with($saludo, '220')
        && smtpOrden($canal, 'EHLO servicioscontablesaz', [250])
        && smtpOrden($canal, 'AUTH LOGIN', [334])
        && smtpOrden($canal, base64_encode($desde), [334])
        && smtpOrden($canal, base64_encode($clave), [235])
        && smtpOrden($canal, 'MAIL FROM:<' . $desde . '>', [250])
        && smtpOrden($canal, 'RCPT TO:<' . $destino . '>', [250, 251])
        && smtpOrden($canal, 'DATA', [354])
        && smtpOrden($canal, mensajeSmtp($desde, $destino, $asunto, $texto, $responderA) . "\r\n.", [250]);
    smtpOrden($canal, 'QUIT', [221]);
    fclose($canal);

    if (!$enviado) {
        error_log('No se pudo entregar el aviso a ' . $destino . '.');
    }

    return $enviado;
}

function smtpLeer($canal): string
{
    $respuesta = '';
    while (($linea = fgets($canal, 515)) !== false) {
        $respuesta .= $linea;
        if (isset($linea[3]) && $linea[3] === ' ') {
            break;
        }
    }

    return $respuesta;
}

function smtpOrden($canal, string $orden, array $esperados): bool
{
    if (fwrite($canal, $orden . "\r\n") === false) {
        return false;
    }

    $codigo = (int) substr(smtpLeer($canal), 0, 3);
    if (!in_array($codigo, $esperados, true)) {
        error_log('El servidor de correo respondió ' . $codigo . '.');
        return false;
    }

    return true;
}

function mensajeSmtp(string $desde, string $destino, string $asunto, string $texto, ?string $responderA): string
{
    $sitio = (string) configuracion()['sitio']['nombre'];
    $lineas = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . encabezadoCodificado($sitio) . ' <' . $desde . '>',
        'To: <' . $destino . '>',
        'Subject: ' . encabezadoCodificado($asunto),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    if ($responderA !== null && filter_var($responderA, FILTER_VALIDATE_EMAIL) !== false) {
        $lineas[] = 'Reply-To: <' . $responderA . '>';
    }

    $cuerpo = rtrim(chunk_split(base64_encode($texto), 76, "\r\n"));
    $mensaje = implode("\r\n", $lineas) . "\r\n\r\n" . $cuerpo;
    $protegido = preg_replace('/^\./m', '..', $mensaje);

    return $protegido ?? $mensaje;
}

function encabezadoCodificado(string $texto): string
{
    if (preg_match('/^[\x20-\x7E]+$/', $texto) === 1) {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $texto) . '"';
    }

    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}

function notificarReserva(array $cita): bool
{
    $administrador = correoAdministrador();
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];
    $cuando = $resumen['fecha'] . ' a las ' . $resumen['hora'];
    if ($administrador === null) {
        return false;
    }

    $alAdministrador = enviarCorreo(
        $administrador,
        'Solicitud de reserva: ' . $cuando,
        "Hay una solicitud de reserva en {$sitio}.\n\n"
            . 'Nombre: ' . $cita['cliente_nombre'] . "\n"
            . 'Correo: ' . $cita['cliente_email'] . "\n"
            . 'Teléfono: ' . $cita['cliente_telefono'] . "\n"
            . datosDeCita($cita) . "\n\n"
            . "Para confirmar o rechazar la solicitud, abre este enlace:\n"
            . enlaceDeConfirmacion($cita),
        (string) $cita['cliente_email']
    );
    $alCliente = enviarCorreo(
        (string) $cita['cliente_email'],
        'Solicitud en espera: ' . $cuando,
        'Hola ' . $cita['cliente_nombre'] . ".\n\n"
            . "Recibimos tu solicitud en {$sitio}. Todavía está en espera de confirmación.\n\n"
            . datosDeCita($cita) . "\n\n"
            . 'Te avisaremos cuando quede confirmada.',
        $administrador
    );

    return $alAdministrador && $alCliente;
}

function notificarConfirmacion(array $cita): bool
{
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];

    return enviarCorreo(
        (string) $cita['cliente_email'],
        'Reserva confirmada: ' . $resumen['fecha'] . ' a las ' . $resumen['hora'],
        'Hola ' . $cita['cliente_nombre'] . ".\n\n"
            . "Tu reserva en {$sitio} quedó confirmada.\n\n"
            . datosDeCita($cita),
        correoAdministrador()
    );
}

function notificarRechazo(array $cita): bool
{
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];

    return enviarCorreo(
        (string) $cita['cliente_email'],
        'Solicitud no confirmada: ' . $resumen['fecha'] . ' a las ' . $resumen['hora'],
        'Hola ' . $cita['cliente_nombre'] . ".\n\n"
            . "Tu solicitud en {$sitio} no fue confirmada.\n\n"
            . datosDeCita($cita) . "\n\n"
            . 'Ese horario volvió a quedar libre.',
        correoAdministrador()
    );
}

function notificarCancelacion(array $cita): bool
{
    $administrador = correoAdministrador();
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];
    $asunto = 'Reserva cancelada: ' . $resumen['fecha'] . ' a las ' . $resumen['hora'];
    $texto = 'Hola ' . $cita['cliente_nombre'] . ".\n\n"
        . "La reserva en {$sitio} fue cancelada.\n\n"
        . datosDeCita($cita);
    if ($administrador === null) {
        return false;
    }

    $alCliente = enviarCorreo((string) $cita['cliente_email'], $asunto, $texto, $administrador);
    $alAdministrador = enviarCorreo($administrador, $asunto, $texto, (string) $cita['cliente_email']);

    return $alCliente && $alAdministrador;
}
