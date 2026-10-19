<?php

declare(strict_types=1);

function koraConfig(): array
{
    $kora = configuracion()['kora'] ?? null;
    if (!is_array($kora)) {
        throw new RuntimeException('Falta la configuración de Kora.');
    }

    $url = rtrim(trim((string) ($kora['url'] ?? '')), '/');
    $llave = trim((string) ($kora['llave'] ?? ''));
    $calendario = trim((string) ($kora['calendario'] ?? ''));
    if ($url === '' || $llave === '' || $calendario === '') {
        throw new RuntimeException('La configuración de Kora está incompleta.');
    }

    return [
        'url' => $url,
        'llave' => $llave,
        'calendario' => $calendario,
    ];
}

function koraUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-'
        . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-'
        . substr($hex, 20);
}

function koraUuidValido(string $valor): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $valor) === 1;
}

function koraLlaveIdempotencia(string $propuesta = ''): string
{
    $llave = trim($propuesta);
    if (!koraUuidValido($llave)) {
        $llave = (string) ($_SESSION['kora_idempotencia'] ?? '');
    }
    if (!koraUuidValido($llave)) {
        $llave = koraUuid();
    }
    $_SESSION['kora_idempotencia'] = $llave;

    return $llave;
}

function koraRenovarIdempotencia(): void
{
    unset($_SESSION['kora_idempotencia']);
}

function koraSolicitud(string $metodo, string $ruta, ?array $cuerpo = null, ?string $idempotencia = null): array
{
    $config = koraConfig();
    $canal = curl_init($config['url'] . $ruta);
    if ($canal === false) {
        return ['ok' => false, 'estado' => 0, 'datos' => null];
    }

    $encabezados = [
        'Authorization: Bearer ' . $config['llave'],
        'Accept: application/json',
    ];
    if ($cuerpo !== null) {
        $encabezados[] = 'Content-Type: application/json';
    }
    if ($idempotencia !== null && $idempotencia !== '') {
        $encabezados[] = 'Idempotency-Key: ' . $idempotencia;
    }

    curl_setopt_array($canal, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => $encabezados,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    if ($cuerpo !== null) {
        curl_setopt($canal, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    }

    $crudo = curl_exec($canal);
    $fallo = curl_errno($canal);
    $estado = (int) curl_getinfo($canal, CURLINFO_RESPONSE_CODE);
    curl_close($canal);

    if ($fallo !== 0 || !is_string($crudo)) {
        error_log('Kora no respondió. Código de transporte ' . $fallo . '.');
        return ['ok' => false, 'estado' => 0, 'datos' => null];
    }

    $datos = json_decode($crudo, true);
    if ($estado < 200 || $estado >= 300) {
        error_log('Kora respondió con estado HTTP ' . $estado . '.');
    }

    return [
        'ok' => $estado >= 200 && $estado < 300 && is_array($datos),
        'estado' => $estado,
        'datos' => is_array($datos) ? $datos : null,
    ];
}

function koraMomento(string $valor): ?DateTimeImmutable
{
    try {
        return (new DateTimeImmutable($valor))->setTimezone(zonaHoraria());
    } catch (Exception $e) {
        return null;
    }
}

function koraEtiqueta(DateTimeImmutable $inicio, DateTimeImmutable $fin): string
{
    return $inicio->format('H:i') . ' – ' . $fin->format('H:i');
}

function koraBloqueDesdeItem(array $item): ?array
{
    if ((string) ($item['calendar_id'] ?? '') !== koraConfig()['calendario']) {
        return null;
    }

    $id = (string) ($item['id'] ?? '');
    $inicio = koraMomento((string) ($item['starts_at'] ?? ''));
    $fin = koraMomento((string) ($item['ends_at'] ?? ''));
    if (!koraUuidValido($id) || !$inicio || !$fin || $fin <= $inicio) {
        return null;
    }

    $ahora = new DateTimeImmutable('now', zonaHoraria());
    $cupos = (int) ($item['seats_left'] ?? 0);
    $abierto = (string) ($item['availability'] ?? '') === 'open' && $cupos > 0;
    if ($inicio <= $ahora) {
        $estado = 'pasado';
    } elseif ($abierto) {
        $estado = 'libre';
    } elseif ((string) ($item['availability'] ?? '') !== 'open') {
        $estado = 'cerrado';
    } else {
        $estado = 'reservado';
    }

    return [
        'id' => $id,
        'fecha' => $inicio->format('Y-m-d'),
        'dia' => $inicio->setTime(0, 0),
        'hora' => $inicio->format('H:i:s'),
        'hora_fin' => $fin->format('H:i:s'),
        'etiqueta' => koraEtiqueta($inicio, $fin),
        'estado' => $estado,
    ];
}

function koraConsultarBloques(): array
{
    static $consulta = null;
    if (is_array($consulta)) {
        return $consulta;
    }

    $calendario = koraConfig()['calendario'];
    $respuesta = koraSolicitud('GET', '/agenda/blocks?' . http_build_query([
        'calendar_id' => $calendario,
    ]));
    if (!$respuesta['ok'] || !is_array($respuesta['datos']['data'] ?? null)) {
        $consulta = [
            'error' => 'La agenda no está disponible en este momento. Intenta de nuevo en unos minutos.',
            'bloques' => [],
            'fechas' => [],
        ];
        return $consulta;
    }
    if (!empty($respuesta['datos']['has_more'])) {
        error_log('Kora devolvió más bloques de los que esta consulta incluye.');
    }

    $bloques = [];
    foreach ($respuesta['datos']['data'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $bloque = koraBloqueDesdeItem($item);
        if ($bloque !== null) {
            $bloques[] = $bloque;
        }
    }

    usort($bloques, static function (array $a, array $b): int {
        return [$a['fecha'], $a['hora']] <=> [$b['fecha'], $b['hora']];
    });

    $fechas = [];
    foreach ($bloques as $bloque) {
        $fechas[$bloque['fecha']] = $bloque['dia'];
    }

    $consulta = [
        'error' => null,
        'bloques' => $bloques,
        'fechas' => array_values($fechas),
    ];

    return $consulta;
}

function koraFecha(string $valor, array $fechas): ?DateTimeImmutable
{
    foreach ($fechas as $fecha) {
        if ($fecha->format('Y-m-d') === $valor) {
            return $fecha;
        }
    }

    return null;
}

function koraBloquesDelDia(DateTimeImmutable $fecha, array $bloques): array
{
    $dia = $fecha->format('Y-m-d');
    $delDia = [];
    foreach ($bloques as $bloque) {
        if ($bloque['fecha'] === $dia) {
            $delDia[] = $bloque;
        }
    }

    return $delDia;
}

function koraBloqueLibre(string $id, array $bloques): ?array
{
    foreach ($bloques as $bloque) {
        if ($bloque['id'] === $id && $bloque['estado'] === 'libre') {
            return $bloque;
        }
    }

    return null;
}

function koraBloquesPublicos(array $bloques): array
{
    $consulta = base()->query(
        'SELECT kora_bloque_id FROM citas
         WHERE estado = \'pendiente\' AND kora_bloque_id IS NOT NULL'
    );
    $pendientes = [];
    foreach ($consulta as $fila) {
        $pendientes[(string) $fila['kora_bloque_id']] = true;
    }

    return array_values(array_filter(
        $bloques,
        static fn (array $bloque): bool => !isset($pendientes[$bloque['id']])
    ));
}

function koraObtenerBloque(string $id): array
{
    if (!koraUuidValido($id)) {
        return ['error' => 'Ese horario no está disponible.', 'bloque' => null, 'cupo' => false];
    }

    $respuesta = koraSolicitud('GET', '/agenda/blocks/' . $id);
    $bloque = is_array($respuesta['datos']) ? koraBloqueDesdeItem($respuesta['datos']) : null;
    if (!$respuesta['ok'] || $bloque === null || $bloque['estado'] !== 'libre') {
        $cupo = (int) $respuesta['estado'] === 404 || ($bloque !== null && $bloque['estado'] !== 'libre');

        return [
            'error' => $cupo
                ? 'Ese horario acaba de ser reservado.'
                : 'La agenda no está disponible en este momento. Intenta de nuevo en unos minutos.',
            'bloque' => null,
            'cupo' => $cupo,
        ];
    }

    return ['error' => null, 'bloque' => $bloque, 'cupo' => false];
}

function koraCerrarSolicitudLocal(int $citaId): void
{
    $cerrar = base()->prepare(
        'UPDATE citas SET estado = \'cancelada\', token_hash = NULL
         WHERE id = ? AND estado = \'pendiente\''
    );
    $cerrar->execute([$citaId]);
}

function koraAnularReserva(string $koraId): bool
{
    if (!koraUuidValido($koraId)) {
        return false;
    }

    $respuesta = koraSolicitud(
        'POST',
        '/agenda/reservations/' . $koraId . '/cancel',
        ['reason' => 'Solicitud rechazada desde el sitio'],
        'anulacion-' . $koraId
    );

    return $respuesta['ok'] || (int) $respuesta['estado'] === 404;
}

function koraSolicitar(array $cliente, string $bloqueId, string $servicio): array
{
    $validacion = validarClienteReserva($cliente);
    if ($validacion['errores'] !== []) {
        return ['error' => $validacion['errores'][0], 'cita' => null, 'cupo' => false];
    }
    $cliente = $validacion['datos'];

    if (servicioValido($servicio) === null) {
        return ['error' => 'Elige un tipo de trámite.', 'cita' => null, 'cupo' => false];
    }
    if (!koraUuidValido($bloqueId)) {
        return ['error' => 'Ese horario no está disponible.', 'cita' => null, 'cupo' => false];
    }

    $obtenido = koraObtenerBloque($bloqueId);
    if ($obtenido['error'] !== null) {
        return ['error' => $obtenido['error'], 'cita' => null, 'cupo' => $obtenido['cupo']];
    }
    $bloque = $obtenido['bloque'];

    $token = bin2hex(random_bytes(32));
    $pdo = base();
    try {
        $alta = $pdo->prepare(
            'INSERT INTO citas
                (usuario_id, cliente_nombre, cliente_apellido, cliente_email, cliente_telefono,
                 cliente_tipo_persona, fecha, hora_inicio, hora_fin, servicio, estado,
                 token_hash, kora_bloque_id)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pendiente\', ?, ?)'
        );
        $alta->execute([
            $cliente['nombre'],
            $cliente['apellido'],
            $cliente['email'],
            $cliente['telefono'],
            $cliente['tipo_persona'],
            $bloque['fecha'],
            $bloque['hora'],
            $bloque['hora_fin'],
            $servicio,
            hash('sha256', $token),
            $bloqueId,
        ]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['error' => 'Ese horario acaba de ser solicitado.', 'cita' => null, 'cupo' => true];
        }
        throw $e;
    }

    $citaId = (int) $pdo->lastInsertId();
    $referencia = 'cita-' . $citaId;
    $respuesta = koraSolicitud('POST', '/agenda/bookings', [
        'block_id' => $bloqueId,
        'contact' => [
            'name' => nombreCliente($cliente['nombre'], $cliente['apellido']),
            'email' => $cliente['email'],
            'phone' => $cliente['telefono'],
        ],
        'notes' => 'Trámite: ' . etiquetaServicio($servicio) . "\n"
            . 'Tipo de persona o régimen: ' . etiquetaTipoPersona($cliente['tipo_persona']),
        'external_ref' => $referencia,
    ], $referencia);

    $datos = is_array($respuesta['datos']) ? $respuesta['datos'] : [];
    $reserva = is_array($datos['reservation'] ?? null) ? $datos['reservation'] : null;
    $koraId = is_array($reserva) ? (string) ($reserva['id'] ?? '') : '';
    $aceptada = $respuesta['ok']
        && is_array($reserva)
        && (string) ($reserva['status'] ?? '') === 'reserved'
        && koraUuidValido($koraId);
    if (!$aceptada) {
        koraCerrarSolicitudLocal($citaId);
        if ((int) $respuesta['estado'] === 422) {
            error_log('Kora rechazó la idempotencia de la solicitud ' . $citaId . '.');
        }

        return [
            'error' => (int) $respuesta['estado'] === 409
                ? 'Ese horario acaba de ser reservado.'
                : 'La agenda no está disponible en este momento. Intenta de nuevo en unos minutos.',
            'cita' => null,
            'cupo' => (int) $respuesta['estado'] === 409,
        ];
    }

    $guardar = $pdo->prepare(
        'UPDATE citas SET kora_reserva_id = ? WHERE id = ? AND estado = \'pendiente\''
    );
    $guardar->execute([$koraId, $citaId]);
    if ($guardar->rowCount() !== 1) {
        error_log('La reserva de Kora ' . $koraId . ' no quedó en la solicitud ' . $citaId . '.');
        koraAnularReserva($koraId);
        koraCerrarSolicitudLocal($citaId);

        return [
            'error' => 'La agenda no está disponible en este momento. Intenta de nuevo en unos minutos.',
            'cita' => null,
            'cupo' => false,
        ];
    }

    return [
        'error' => null,
        'cupo' => false,
        'cita' => [
            'id' => $citaId,
            'cliente_nombre' => $cliente['nombre'],
            'cliente_apellido' => $cliente['apellido'],
            'cliente_email' => $cliente['email'],
            'cliente_telefono' => $cliente['telefono'],
            'cliente_tipo_persona' => $cliente['tipo_persona'],
            'fecha' => $bloque['fecha'],
            'hora_inicio' => $bloque['hora'],
            'hora_fin' => $bloque['hora_fin'],
            'servicio' => $servicio,
            'estado' => 'pendiente',
            'kora_bloque_id' => $bloqueId,
            'kora_id' => $koraId,
            'token' => $token,
            'etiqueta' => $bloque['etiqueta'],
        ],
    ];
}

function koraSolicitudPendiente(int $citaId, string $token): array
{
    $cita = citaPorId($citaId);
    $hash = hash('sha256', $token);
    if (
        !$cita
        || $cita['estado'] !== 'pendiente'
        || !is_string($cita['token_hash'])
        || !hash_equals($cita['token_hash'], $hash)
    ) {
        return ['error' => 'Esta solicitud ya no está pendiente.', 'cita' => null];
    }

    return ['error' => null, 'cita' => $cita];
}

function koraAprobarSolicitud(int $citaId, string $token): array
{
    $pendiente = koraSolicitudPendiente($citaId, $token);
    if ($pendiente['error'] !== null) {
        return $pendiente;
    }
    $cita = $pendiente['cita'];
    if (!koraUuidValido((string) ($cita['kora_reserva_id'] ?? ''))) {
        return [
            'error' => 'Esta solicitud no tiene una reserva en Kora. Solo puede rechazarse.',
            'cita' => $cita,
        ];
    }

    $actualizar = base()->prepare(
        'UPDATE citas SET estado = \'reservada\', token_hash = NULL
         WHERE id = ? AND estado = \'pendiente\' AND token_hash = ?'
    );
    $actualizar->execute([$citaId, hash('sha256', $token)]);
    if ($actualizar->rowCount() !== 1) {
        return ['error' => 'Esta solicitud ya no está pendiente.', 'cita' => null];
    }

    $cita['estado'] = 'reservada';
    unset($cita['token_hash']);

    return ['error' => null, 'cita' => $cita];
}

function koraRechazarSolicitud(int $citaId, string $token): array
{
    $pendiente = koraSolicitudPendiente($citaId, $token);
    if ($pendiente['error'] !== null) {
        return $pendiente;
    }

    $cita = $pendiente['cita'];
    $koraId = (string) ($cita['kora_reserva_id'] ?? '');
    if ($koraId !== '' && !koraAnularReserva($koraId)) {
        return [
            'error' => 'Kora no pudo liberar el horario. La solicitud sigue pendiente.',
            'cita' => $cita,
        ];
    }

    return resolverSolicitud($citaId, 'cancelada', $token);
}

function koraReservasLocales(): array
{
    $consulta = base()->query(
        'SELECT cliente_nombre, cliente_apellido, cliente_email, cliente_telefono,
                cliente_tipo_persona, fecha, hora_inicio, servicio, estado, kora_reserva_id
         FROM citas
         WHERE estado IN (\'pendiente\', \'reservada\')
         ORDER BY fecha, hora_inicio'
    );
    $reservas = [];
    foreach ($consulta as $fila) {
        $inicio = DateTimeImmutable::createFromFormat('H:i:s', (string) $fila['hora_inicio'], zonaHoraria());
        $reservas[] = [
            'nombre' => nombreCliente((string) $fila['cliente_nombre'], (string) $fila['cliente_apellido']),
            'email' => (string) $fila['cliente_email'],
            'telefono' => (string) $fila['cliente_telefono'],
            'tipo_persona' => $fila['cliente_tipo_persona'],
            'fecha' => (string) $fila['fecha'],
            'hora' => $inicio ? $inicio->format('H:i') : substr((string) $fila['hora_inicio'], 0, 5),
            'servicio' => $fila['servicio'],
            'estado' => (string) $fila['estado'],
            'kora_id' => (string) ($fila['kora_reserva_id'] ?? ''),
        ];
    }

    return $reservas;
}

function koraReservasExternas(): array
{
    $zona = zonaHoraria();
    $desde = new DateTimeImmutable('today', $zona);
    $hasta = $desde->modify('+14 days')->setTime(23, 59, 59);
    $respuesta = koraSolicitud('GET', '/agenda/reservations?' . http_build_query([
        'calendar_id' => koraConfig()['calendario'],
        'from' => $desde->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        'to' => $hasta->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
    ]));
    if (!$respuesta['ok'] || !is_array($respuesta['datos']['data'] ?? null)) {
        return [
            'error' => 'No se pudieron consultar las reservas hechas directamente en Kora.',
            'reservas' => [],
        ];
    }
    if (!empty($respuesta['datos']['has_more'])) {
        error_log('Kora devolvió más reservas de las que esta consulta incluye.');
    }

    $conocidas = [];
    foreach (base()->query('SELECT kora_reserva_id FROM citas WHERE kora_reserva_id IS NOT NULL') as $fila) {
        $conocidas[(string) $fila['kora_reserva_id']] = true;
    }

    $reservas = [];
    foreach ($respuesta['datos']['data'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = (string) ($item['id'] ?? '');
        $inicio = koraMomento((string) ($item['starts_at'] ?? ''));
        if (!koraUuidValido($id) || isset($conocidas[$id]) || !$inicio) {
            continue;
        }
        $reservas[] = [
            'nombre' => trim((string) ($item['guest_name'] ?? '')),
            'email' => trim((string) ($item['guest_email'] ?? '')),
            'telefono' => trim((string) ($item['guest_phone'] ?? '')),
            'fecha' => $inicio->format('Y-m-d'),
            'hora' => $inicio->format('H:i'),
            'notas' => trim((string) ($item['notes'] ?? '')),
            'kora_id' => $id,
        ];
    }

    return ['error' => null, 'reservas' => $reservas];
}
