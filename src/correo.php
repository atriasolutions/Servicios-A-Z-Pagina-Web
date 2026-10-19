<?php

declare(strict_types=1);

function resumenCita(array $cita): array
{
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $cita['fecha'], zonaHoraria());
    $fechaTexto = $fecha ? fechaLarga($fecha) : (string) $cita['fecha'];

    return [
        'servicio' => etiquetaServicio($cita['servicio'] ?? null),
        'fecha' => $fechaTexto,
        'hora' => isset($cita['hora_fin'])
            ? substr((string) $cita['hora_inicio'], 0, 5) . ' – ' . substr((string) $cita['hora_fin'], 0, 5)
            : etiquetaBloque((string) $cita['hora_inicio']),
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

function datosDeCita(array $cita): string
{
    $resumen = resumenCita($cita);

    return 'Servicio: ' . $resumen['servicio'] . "\n"
        . 'Fecha: ' . $resumen['fecha'] . "\n"
        . 'Hora: ' . $resumen['hora'];
}

function enviarCorreo(
    string $destino,
    string $asunto,
    string $texto,
    ?string $responderA = null,
    ?string $html = null
): bool
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

    stream_set_timeout($canal, 15);
    $saludo = smtpLeer($canal);
    $enviado = str_starts_with($saludo, '220')
        && smtpOrden($canal, 'EHLO servicioscontablesaz', [250])
        && smtpOrden($canal, 'AUTH LOGIN', [334])
        && smtpOrden($canal, base64_encode($desde), [334])
        && smtpOrden($canal, base64_encode($clave), [235])
        && smtpOrden($canal, 'MAIL FROM:<' . $desde . '>', [250])
        && smtpOrden($canal, 'RCPT TO:<' . $destino . '>', [250, 251])
        && smtpOrden($canal, 'DATA', [354])
        && smtpOrden(
            $canal,
            mensajeSmtp($desde, $destino, $asunto, $texto, $responderA, $html) . "\r\n.",
            [250]
        );
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

function mensajeSmtp(
    string $desde,
    string $destino,
    string $asunto,
    string $texto,
    ?string $responderA,
    ?string $html = null
): string
{
    $sitio = (string) configuracion()['sitio']['nombre'];
    $lineas = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . encabezadoCodificado($sitio) . ' <' . $desde . '>',
        'To: <' . $destino . '>',
        'Subject: ' . encabezadoCodificado($asunto),
        'MIME-Version: 1.0',
    ];
    if ($responderA !== null && filter_var($responderA, FILTER_VALIDATE_EMAIL) !== false) {
        $lineas[] = 'Reply-To: <' . $responderA . '>';
    }

    if ($html === null) {
        $lineas[] = 'Content-Type: text/plain; charset=UTF-8';
        $lineas[] = 'Content-Transfer-Encoding: base64';
        $cuerpo = rtrim(chunk_split(base64_encode($texto), 76, "\r\n"));
        $mensaje = implode("\r\n", $lineas) . "\r\n\r\n" . $cuerpo;
    } else {
        $alterno = 'agenda_' . bin2hex(random_bytes(12));
        $relacion = 'agenda_' . bin2hex(random_bytes(12));
        $lineas[] = 'Content-Type: multipart/related; boundary="' . $relacion . '"';
        $textoCodificado = rtrim(chunk_split(base64_encode($texto), 76, "\r\n"));
        $htmlCodificado = rtrim(chunk_split(base64_encode($html), 76, "\r\n"));
        $cuerpo = '--' . $relacion . "\r\n"
            . 'Content-Type: multipart/alternative; boundary="' . $alterno . "\"\r\n\r\n"
            . '--' . $alterno . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . $textoCodificado . "\r\n"
            . '--' . $alterno . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . $htmlCodificado . "\r\n"
            . '--' . $alterno . "--\r\n";
        foreach (imagenesDelCorreo() as $imagen) {
            $contenido = is_file($imagen['ruta']) ? file_get_contents($imagen['ruta']) : false;
            if (!is_string($contenido) || $contenido === '') {
                continue;
            }
            $cuerpo .= '--' . $relacion . "\r\n"
                . 'Content-Type: ' . $imagen['tipo'] . "\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-ID: <' . $imagen['id'] . ">\r\n"
                . 'Content-Disposition: inline; filename="' . $imagen['nombre'] . "\"\r\n\r\n"
                . rtrim(chunk_split(base64_encode($contenido), 76, "\r\n")) . "\r\n";
        }
        $cuerpo .= '--' . $relacion . '--';
        $mensaje = implode("\r\n", $lineas) . "\r\n\r\n" . $cuerpo;
    }
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

function imagenesDelCorreo(): array
{
    $carpeta = dirname(__DIR__) . '/public/img/';

    return [
        ['ruta' => $carpeta . 'fondo-correo.jpg', 'tipo' => 'image/jpeg', 'id' => 'fondo-correo', 'nombre' => 'fondo-correo.jpg'],
        ['ruta' => $carpeta . 'logo-correo.png', 'tipo' => 'image/png', 'id' => 'logo-az', 'nombre' => 'logo-az.png'],
    ];
}

function urlPublica(): string
{
    $publica = rtrim(trim((string) (configuracion()['sitio']['url'] ?? '')), '/');
    if (preg_match('#^https://[a-z0-9.-]+$#i', $publica) === 1) {
        return $publica;
    }

    return 'https://www.scontablesayz.cl';
}

function textoCorreo(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function filasDeCita(array $cita, bool $contacto = false): array
{
    $resumen = resumenCita($cita);
    $filas = [
        ['Servicio', $resumen['servicio']],
        ['Fecha', $resumen['fecha']],
        ['Hora', $resumen['hora']],
    ];
    if (!$contacto) {
        return $filas;
    }

    return [
        ['Nombre', nombreCliente((string) $cita['cliente_nombre'], (string) ($cita['cliente_apellido'] ?? ''))],
        ['Tipo de persona', etiquetaTipoPersona($cita['cliente_tipo_persona'] ?? null)],
        ['Correo', (string) $cita['cliente_email']],
        ['Teléfono', (string) $cita['cliente_telefono']],
        ...$filas,
    ];
}

function textoDeFilas(array $filas): string
{
    $lineas = [];
    foreach ($filas as $fila) {
        $lineas[] = $fila[0] . ': ' . $fila[1];
    }

    return implode("\n", $lineas);
}

function estiloEstado(string $estado): array
{
    if ($estado === 'confirmada') {
        return ['Confirmada', '#e9f2e1', '#2f6a12'];
    }
    if ($estado === 'cancelada') {
        return ['Cancelada', '#f8e8e8', '#9a3030'];
    }
    if ($estado === 'rechazada') {
        return ['No confirmada', '#f8e8e8', '#9a3030'];
    }

    return ['Pendiente', '#f2d487', '#102a4c'];
}

function correoHtml(array $aviso): string
{
    $sitio = (string) configuracion()['sitio']['nombre'];
    [$etiqueta, $fondo, $color] = estiloEstado((string) ($aviso['estado'] ?? 'pendiente'));
    $parrafos = '';
    foreach ($aviso['parrafos'] ?? [] as $parrafo) {
        $parrafos .= '<p style="margin:0 0 16px;color:#102a4c;font-size:16px;line-height:1.6;">'
            . textoCorreo((string) $parrafo) . '</p>';
    }

    $filas = '';
    foreach ($aviso['filas'] ?? [] as $fila) {
        $filas .= '<tr>'
            . '<td style="padding:11px 0;border-bottom:1px solid #d8e1e8;color:#506174;font-size:13px;width:148px;vertical-align:top;">'
            . textoCorreo((string) $fila[0]) . '</td>'
            . '<td style="padding:11px 0;border-bottom:1px solid #d8e1e8;color:#102a4c;font-size:16px;font-weight:bold;vertical-align:top;">'
            . textoCorreo((string) $fila[1]) . '</td></tr>';
    }

    $accion = '';
    if (isset($aviso['boton'])) {
        $accion .= '<p style="margin:28px 0 12px;">'
            . '<a href="' . textoCorreo((string) $aviso['boton']['url']) . '" '
            . 'style="display:inline-block;padding:13px 22px;border-radius:8px;background:#0c3763;color:#ffffff;text-decoration:none;font-weight:bold;">'
            . textoCorreo((string) $aviso['boton']['texto']) . '</a></p>';
    }
    if (isset($aviso['enlace'])) {
        $accion .= '<p style="margin:20px 0 0;font-size:15px;">'
            . '<a href="' . textoCorreo((string) $aviso['enlace']['url']) . '" style="color:#0c3763;font-weight:bold;">'
            . textoCorreo((string) $aviso['enlace']['texto']) . '</a></p>';
    }
    if (isset($aviso['nota'])) {
        $accion .= '<p style="margin:16px 0 0;color:#506174;font-size:13px;line-height:1.5;">'
            . textoCorreo((string) $aviso['nota']) . '</p>';
    }

    return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background-color:#f5f8fa;font-family:Arial,sans-serif;">'
        . '<div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;color:#f5f8fa;">'
        . textoCorreo((string) ($aviso['preheader'] ?? '')) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f8fa;">'
        . '<tr><td align="center" background="cid:fondo-correo" style="padding:48px 16px;background-color:#f5f8fa;background-image:url(cid:fondo-correo);background-repeat:no-repeat;background-position:center center;background-size:cover;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border:1px solid #d8e1e8;border-radius:14px;">'
        . '<tr><td style="padding:0;font-size:0;line-height:0;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        . '<td width="72%" height="6" style="background:#0c3763;font-size:0;line-height:6px;">&nbsp;</td>'
        . '<td width="28%" height="6" style="background:#c48718;font-size:0;line-height:6px;">&nbsp;</td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding:28px 28px 8px;">'
        . '<img src="cid:logo-az" alt="' . textoCorreo($sitio) . '" height="48" '
        . 'style="display:block;border:0;height:48px;width:auto;max-width:180px;"></td></tr>'
        . '<tr><td style="padding:12px 28px 0;">'
        . '<span style="display:inline-block;padding:5px 10px;border-radius:999px;background:' . $fondo . ';color:' . $color . ';font-size:12px;font-weight:bold;">'
        . textoCorreo($etiqueta) . '</span></td></tr>'
        . '<tr><td style="padding:16px 28px 0;">'
        . '<h1 style="margin:0 0 16px;color:#102a4c;font-size:24px;line-height:1.3;">'
        . textoCorreo((string) ($aviso['titulo'] ?? '')) . '</h1>'
        . $parrafos . '</td></tr>'
        . '<tr><td style="padding:0 28px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . $filas . '</table></td></tr>'
        . '<tr><td style="padding:0 28px 8px;">' . $accion . '</td></tr>'
        . '<tr><td style="padding:20px 28px 28px;color:#506174;font-size:13px;">' . textoCorreo($sitio) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function notificarSolicitudCliente(array $cita): bool
{
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];
    $filas = filasDeCita($cita);
    $cierre = 'Te enviaremos otro correo cuando la cita sea confirmada o anulada desde Kora.';
    $texto = 'Hola ' . $cita['cliente_nombre'] . ".\n\n"
        . "Recibimos tu solicitud de asesoría en {$sitio}. La hora quedó retenida y está pendiente de confirmación.\n\n"
        . textoDeFilas($filas) . "\n\n"
        . $cierre;

    return enviarCorreo(
        (string) $cita['cliente_email'],
        'Solicitud de asesoría recibida: ' . $resumen['fecha'] . ' a las ' . $resumen['hora'],
        $texto,
        correoAdministrador(),
        correoHtml([
            'preheader' => 'Tu solicitud quedó pendiente de confirmación.',
            'estado' => 'pendiente',
            'titulo' => 'Recibimos tu solicitud',
            'parrafos' => [
                'Hola ' . $cita['cliente_nombre'] . '.',
                'La hora quedó retenida y está pendiente de confirmación.',
            ],
            'filas' => $filas,
            'nota' => $cierre,
        ])
    );
}

function notificarContacto(array $datos): bool
{
    $administrador = correoAdministrador();
    if ($administrador === null) {
        return false;
    }

    $sitio = (string) configuracion()['sitio']['nombre'];
    $cliente = nombreCliente((string) ($datos['nombre'] ?? ''), (string) ($datos['apellido'] ?? ''));

    return enviarCorreo(
        $administrador,
        'Solicitud de contacto',
        "Hay una solicitud de contacto en {$sitio}.\n\n"
            . 'Nombre: ' . $cliente . "\n"
            . 'Tipo de persona o régimen: ' . etiquetaTipoPersona($datos['tipo_persona'] ?? null) . "\n"
            . 'Correo: ' . ($datos['email'] ?? '') . "\n"
            . 'Teléfono: ' . ($datos['telefono'] ?? '') . "\n",
        (string) ($datos['email'] ?? '')
    );
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

    $filasAdmin = filasDeCita($cita, true);
    $alAdministrador = enviarCorreo(
        $administrador,
        'Reserva confirmada: ' . $cuando,
        "Hay una reserva confirmada en {$sitio}.\n\n" . textoDeFilas($filasAdmin),
        (string) $cita['cliente_email'],
        correoHtml([
            'preheader' => 'Hay una reserva confirmada.',
            'estado' => 'confirmada',
            'titulo' => 'Reserva confirmada',
            'parrafos' => ["Hay una reserva confirmada en {$sitio}."],
            'filas' => $filasAdmin,
        ])
    );
    $filasCliente = filasDeCita($cita);
    $alCliente = enviarCorreo(
        (string) $cita['cliente_email'],
        'Reserva confirmada: ' . $cuando,
        'Hola ' . $cita['cliente_nombre'] . ".\n\n"
            . "Tu reserva en {$sitio} quedó confirmada.\n\n"
            . textoDeFilas($filasCliente),
        $administrador,
        correoHtml([
            'preheader' => 'Tu reserva quedó confirmada.',
            'estado' => 'confirmada',
            'titulo' => 'Tu reserva quedó confirmada',
            'parrafos' => [
                'Hola ' . $cita['cliente_nombre'] . '.',
                "Tu reserva en {$sitio} quedó confirmada.",
            ],
            'filas' => $filasCliente,
        ])
    );

    return $alAdministrador && $alCliente;
}

function notificarConfirmacion(array $cita): bool
{
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];
    $filas = filasDeCita($cita);

    return enviarCorreo(
        (string) $cita['cliente_email'],
        'Reserva confirmada: ' . $resumen['fecha'] . ' a las ' . $resumen['hora'],
        'Hola ' . $cita['cliente_nombre'] . ".\n\n"
            . "Tu reserva en {$sitio} quedó confirmada.\n\n"
            . textoDeFilas($filas),
        correoAdministrador(),
        correoHtml([
            'preheader' => 'Tu reserva quedó confirmada.',
            'estado' => 'confirmada',
            'titulo' => 'Tu reserva quedó confirmada',
            'parrafos' => [
                'Hola ' . $cita['cliente_nombre'] . '.',
                "Tu reserva en {$sitio} quedó confirmada.",
            ],
            'filas' => $filas,
        ])
    );
}

function notificarRechazo(array $cita, bool $vuelveDisponible = true): bool
{
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];

    $motivo = $vuelveDisponible
        ? 'Ese horario volvió a quedar libre.'
        : 'El horario ya no estaba disponible en Kora.';
    $otroHorario = urlPublica() . '/horarios.php';
    $filas = filasDeCita($cita);

    return enviarCorreo(
        (string) $cita['cliente_email'],
        'Solicitud no confirmada: ' . $resumen['fecha'] . ' a las ' . $resumen['hora'],
        'Hola ' . $cita['cliente_nombre'] . ".\n\n"
            . "Tu solicitud en {$sitio} no fue confirmada.\n\n"
            . textoDeFilas($filas) . "\n\n"
            . $motivo . "\n"
            . 'Elegir otro horario: ' . $otroHorario,
        correoAdministrador(),
        correoHtml([
            'preheader' => 'Tu solicitud no fue confirmada.',
            'estado' => 'rechazada',
            'titulo' => 'Tu solicitud no fue confirmada',
            'parrafos' => [
                'Hola ' . $cita['cliente_nombre'] . '.',
                "Tu solicitud en {$sitio} no fue confirmada.",
                $motivo,
            ],
            'filas' => $filas,
            'enlace' => ['texto' => 'Elegir otro horario', 'url' => $otroHorario],
        ])
    );
}

function notificarCancelacion(array $cita): bool
{
    $administrador = correoAdministrador();
    $resumen = resumenCita($cita);
    $sitio = (string) configuracion()['sitio']['nombre'];
    $asunto = 'Reserva cancelada: ' . $resumen['fecha'] . ' a las ' . $resumen['hora'];
    $filas = filasDeCita($cita);
    $otroHorario = urlPublica() . '/horarios.php';
    $texto = 'Hola ' . $cita['cliente_nombre'] . ".\n\n"
        . "La cita solicitada en {$sitio} fue anulada.\n\n"
        . textoDeFilas($filas) . "\n\n"
        . "El horario volvió a quedar disponible.\n"
        . 'Elegir otro horario: ' . $otroHorario;
    $html = correoHtml([
        'preheader' => 'La cita solicitada fue anulada.',
        'estado' => 'cancelada',
        'titulo' => 'La cita fue anulada',
        'parrafos' => [
            'Hola ' . $cita['cliente_nombre'] . '.',
            "La cita solicitada en {$sitio} fue anulada.",
            'El horario volvió a quedar disponible.',
        ],
        'filas' => $filas,
        'enlace' => ['texto' => 'Elegir otro horario', 'url' => $otroHorario],
    ]);
    if ($administrador === null) {
        return false;
    }

    return enviarCorreo((string) $cita['cliente_email'], $asunto, $texto, $administrador, $html);
}
