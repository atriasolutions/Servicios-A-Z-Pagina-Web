<?php

declare(strict_types=1);

function horasDeAgenda(): array
{
    $agenda = configuracion()['agenda'];
    $inicio = (int) $agenda['hora_inicio'] * 60;
    $fin = (int) $agenda['hora_fin'] * 60;
    $duracion = (int) $agenda['duracion_minutos'];

    if ($duracion < 1 || $fin <= $inicio) {
        throw new RuntimeException('El horario de config/config.php no es válido.');
    }

    $horas = [];
    for ($minuto = $inicio; $minuto + $duracion <= $fin; $minuto += $duracion) {
        $horas[] = sprintf('%02d:%02d:00', intdiv($minuto, 60), $minuto % 60);
    }

    if ($horas === []) {
        throw new RuntimeException('El horario de config/config.php no genera bloques.');
    }

    return $horas;
}

function diasHabiles(): array
{
    $dias = configuracion()['agenda']['dias'] ?? [1, 2, 3, 4, 5];
    if (!is_array($dias)) {
        throw new RuntimeException('Los días de la agenda en config/config.php no son válidos.');
    }

    $habiles = [];
    foreach ($dias as $dia) {
        $numero = (int) $dia;
        if ($numero >= 1 && $numero <= 7) {
            $habiles[$numero] = true;
        }
    }

    if ($habiles === []) {
        throw new RuntimeException('Los días de la agenda en config/config.php no son válidos.');
    }

    return $habiles;
}

function fechasDeAgenda(): array
{
    $meses = (int) (configuracion()['agenda']['meses_anticipacion'] ?? 1);
    if ($meses !== 1) {
        throw new RuntimeException('La anticipación de la agenda en config/config.php no es válida.');
    }

    $habiles = diasHabiles();
    $hoy = new DateTimeImmutable('today', zonaHoraria());
    $primerDiaDestino = $hoy->modify('first day of next month');
    $ultimoDiaDestino = (int) $primerDiaDestino->format('t');
    $diaDestino = min((int) $hoy->format('j'), $ultimoDiaDestino);
    $limite = $primerDiaDestino->setDate(
        (int) $primerDiaDestino->format('Y'),
        (int) $primerDiaDestino->format('n'),
        $diaDestino
    );
    $fechas = [];
    for ($fecha = $hoy; $fecha <= $limite; $fecha = $fecha->modify('+1 day')) {
        if (isset($habiles[(int) $fecha->format('N')])) {
            $fechas[] = $fecha;
        }
    }

    if ($fechas === []) {
        throw new RuntimeException('El horizonte de la agenda no incluye días hábiles.');
    }

    return $fechas;
}

function fechaEnAgenda(string $valor): ?DateTimeImmutable
{
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, zonaHoraria());
    if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
        return null;
    }

    foreach (fechasDeAgenda() as $habil) {
        if ($habil->format('Y-m-d') === $valor) {
            return $fecha;
        }
    }

    return null;
}

function normalizarHora(string $hora): ?string
{
    if (preg_match('/^\d{2}:\d{2}$/', $hora) === 1) {
        $hora .= ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora) !== 1) {
        return null;
    }
    if (!in_array($hora, horasDeAgenda(), true)) {
        return null;
    }

    return $hora;
}

function inicioDeBloque(string $fecha, string $hora): DateTimeImmutable
{
    return new DateTimeImmutable($fecha . ' ' . $hora, zonaHoraria());
}

function cargarPeriodo(): array
{
    $fechas = fechasDeAgenda();
    $desde = $fechas[0]->format('Y-m-d');
    $hasta = $fechas[array_key_last($fechas)]->format('Y-m-d');

    $bloqueos = [];
    $consulta = base()->prepare(
        'SELECT fecha, hora_inicio FROM bloqueos WHERE fecha BETWEEN ? AND ?'
    );
    $consulta->execute([$desde, $hasta]);
    foreach ($consulta as $fila) {
        $bloqueos[$fila['fecha'] . ' ' . $fila['hora_inicio']] = true;
    }

    $citas = [];
    $consulta = base()->prepare(
        'SELECT id, fecha, hora_inicio, servicio, estado,
                cliente_nombre AS nombre,
                cliente_apellido AS apellido,
                cliente_telefono AS telefono,
                cliente_email AS email,
                cliente_tipo_persona AS tipo_persona
         FROM citas
         WHERE estado IN (\'pendiente\', \'reservada\') AND fecha BETWEEN ? AND ?'
    );
    $consulta->execute([$desde, $hasta]);
    foreach ($consulta as $fila) {
        $citas[$fila['fecha'] . ' ' . $fila['hora_inicio']] = $fila;
    }

    return ['bloqueos' => $bloqueos, 'citas' => $citas];
}

function bloquesDelDia(DateTimeImmutable $fecha, array $periodo): array
{
    $dia = $fecha->format('Y-m-d');
    $ahora = new DateTimeImmutable('now', zonaHoraria());
    $bloques = [];

    foreach (horasDeAgenda() as $hora) {
        $clave = $dia . ' ' . $hora;
        $inicio = inicioDeBloque($dia, $hora);
        $cita = $periodo['citas'][$clave] ?? null;
        $cerrado = isset($periodo['bloqueos'][$clave]);

        if ($cita && ($cita['estado'] ?? '') === 'pendiente') {
            $estado = 'espera';
        } elseif ($cita) {
            $estado = 'reservado';
        } elseif ($cerrado) {
            $estado = 'cerrado';
        } elseif ($inicio <= $ahora) {
            $estado = 'pasado';
        } else {
            $estado = 'libre';
        }

        $bloques[] = [
            'hora' => $hora,
            'etiqueta' => etiquetaBloque($hora),
            'estado' => $estado,
            'cita_id' => $cita ? (int) $cita['id'] : null,
            'cliente' => $cita ? nombreCliente((string) $cita['nombre'], (string) $cita['apellido']) : null,
            'telefono' => $cita['telefono'] ?? null,
            'email' => $cita['email'] ?? null,
            'tipo_persona' => $cita['tipo_persona'] ?? null,
            'servicio' => $cita['servicio'] ?? null,
        ];
    }

    return $bloques;
}

function servicios(): array
{
    return [
        'constitucion_empresas' => 'Constitución de Empresas',
        'contabilidad' => 'Contabilidad',
        'asesoria_laboral' => 'Asesoría Laboral',
        'operacion_renta' => 'Operación renta',
    ];
}

function servicioValido(?string $clave): ?string
{
    $clave = (string) $clave;

    return array_key_exists($clave, servicios()) ? $clave : null;
}

function etiquetaServicio(?string $clave): string
{
    $anteriores = [
        'gestion_tributaria' => 'Gestión tributaria',
        'contabilidad_financiera' => 'Contabilidad financiera',
        'gestion_remuneraciones' => 'Gestión de remuneraciones',
    ];

    return servicios()[$clave] ?? $anteriores[$clave] ?? 'Asesoría';
}

function tiposPersona(): array
{
    return [
        'persona_natural' => 'Persona Natural',
        'empresa' => 'Empresa',
        'empresa_por_constituir' => 'Empresa por Constituir',
    ];
}

function tipoPersonaValido(?string $clave): ?string
{
    $clave = (string) $clave;

    return array_key_exists($clave, tiposPersona()) ? $clave : null;
}

function etiquetaTipoPersona(?string $clave): string
{
    return tiposPersona()[$clave] ?? 'No informado';
}

function nombreCliente(string $nombre, ?string $apellido = null): string
{
    return trim($nombre . ' ' . trim((string) $apellido));
}

function informacionServicio(string $clave): array
{
    $catalogo = [
        'constitucion_empresas' => [
            'descripcion' => 'Orientación para constituir una empresa y ordenar la información del inicio.',
            'beneficios' => [
                'Conversación sobre el inicio de una empresa',
                'Apoyo para ordenar la información inicial',
                'Espacio para dudas de formalización',
            ],
        ],
        'contabilidad' => [
            'descripcion' => 'Acompañamiento para el orden de tus registros y la preparación de tu información financiera.',
            'beneficios' => [
                'Revisión del orden de tus registros',
                'Apoyo en la preparación de tu información financiera',
                'Espacio para dudas de tu contabilidad',
            ],
        ],
        'asesoria_laboral' => [
            'descripcion' => 'Orientación sobre las obligaciones laborales de tu actividad ante la Dirección del Trabajo.',
            'beneficios' => [
                'Conversación sobre tus obligaciones laborales',
                'Apoyo para ordenar la información de tu personal',
                'Espacio para dudas laborales generales',
            ],
        ],
        'operacion_renta' => [
            'descripcion' => 'Acompañamiento para revisar tu operación renta y ordenar la información del período.',
            'beneficios' => [
                'Revisión general de tu operación renta',
                'Apoyo para ordenar la información del período',
                'Espacio para dudas tributarias del período',
            ],
        ],
    ];

    $info = $catalogo[$clave] ?? null;
    if (!is_array($info)) {
        return ['descripcion' => '', 'beneficios' => []];
    }

    return [
        'descripcion' => (string) ($info['descripcion'] ?? ''),
        'beneficios' => array_values(array_map('strval', $info['beneficios'] ?? [])),
    ];
}

function validarClienteReserva(array $entrada): array
{
    $datos = [
        'nombre' => trim((string) ($entrada['nombre'] ?? '')),
        'apellido' => trim((string) ($entrada['apellido'] ?? '')),
        'email' => normalizarEmail((string) ($entrada['email'] ?? '')),
        'telefono' => trim((string) ($entrada['telefono'] ?? '')),
        'tipo_persona' => tipoPersonaValido((string) ($entrada['tipo_persona'] ?? '')),
    ];
    $errores = [];
    $largoNombre = function_exists('mb_strlen')
        ? mb_strlen($datos['nombre'], 'UTF-8')
        : strlen($datos['nombre']);

    if ($largoNombre < 2 || $largoNombre > 120 || preg_match('/^[\p{L}\s\'.-]+$/u', $datos['nombre']) !== 1) {
        $errores[] = 'Escribe tu nombre, entre 2 y 120 letras.';
    }
    $largoApellido = function_exists('mb_strlen')
        ? mb_strlen($datos['apellido'], 'UTF-8')
        : strlen($datos['apellido']);
    if (
        $datos['apellido'] !== ''
        && ($largoApellido > 120 || preg_match('/^[\p{L}\s\'.-]+$/u', $datos['apellido']) !== 1)
    ) {
        $errores[] = 'Escribe un apellido válido de hasta 120 letras.';
    }
    if (!correoValido($datos['email'])) {
        $errores[] = 'Escribe un correo válido, por ejemplo nombre@correo.cl.';
    }
    $celular = celularChileno($datos['telefono']);
    if ($celular === null) {
        $errores[] = 'Escribe un celular chileno, por ejemplo +56 9 1234 5678.';
    } else {
        $datos['telefono'] = $celular;
    }
    if ($datos['tipo_persona'] === null) {
        $errores[] = 'Elige un tipo de persona o régimen.';
    }

    return ['datos' => $datos, 'errores' => $errores];
}

function correoValido(string $email): bool
{
    if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    return preg_match('/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i', $email) === 1;
}

function celularChileno(string $telefono): ?string
{
    if (strlen($telefono) > 40) {
        return null;
    }

    $digitos = preg_replace('/\D+/', '', $telefono) ?? '';
    if (strlen($digitos) === 11 && strncmp($digitos, '569', 3) === 0) {
        $nacional = substr($digitos, 2);
    } elseif (strlen($digitos) === 10 && strncmp($digitos, '09', 2) === 0) {
        $nacional = substr($digitos, 1);
    } elseif (strlen($digitos) === 9 && strncmp($digitos, '9', 1) === 0) {
        $nacional = $digitos;
    } else {
        return null;
    }

    if (preg_match('/^9\d{8}$/', $nacional) !== 1) {
        return null;
    }

    return '+56 ' . $nacional[0] . ' ' . substr($nacional, 1, 4) . ' ' . substr($nacional, 5);
}

function validarContacto(array $entrada): array
{
    $validacion = validarClienteReserva($entrada);
    $datos = $validacion['datos'];
    $errores = $validacion['errores'];
    $datos['razon_social'] = '';
    $datos['rut_empresa'] = '';

    if ($datos['tipo_persona'] !== 'empresa') {
        return ['datos' => $datos, 'errores' => $errores];
    }

    $razon = trim((string) ($entrada['razon_social'] ?? ''));
    $rut = trim((string) ($entrada['rut_empresa'] ?? ''));
    $datos['razon_social'] = $razon;
    $datos['rut_empresa'] = $rut;

    $largoRazon = function_exists('mb_strlen') ? mb_strlen($razon, 'UTF-8') : strlen($razon);
    if ($largoRazon < 2 || $largoRazon > 200 || preg_match('/[\x00-\x1F\x7F]/', $razon) === 1) {
        $errores[] = 'Escribe la razón social, entre 2 y 200 caracteres.';
    }

    $normalizado = rutEmpresaValido($rut);
    if ($normalizado === null) {
        $errores[] = 'El RUT de la empresa no es válido.';
    } else {
        $datos['rut_empresa'] = $normalizado;
    }

    return ['datos' => $datos, 'errores' => $errores];
}

function rutEmpresaValido(string $rut): ?string
{
    $limpio = strtoupper(preg_replace('/[^0-9kK]/', '', $rut) ?? '');
    if (strlen($limpio) < 2 || strlen($limpio) > 9) {
        return null;
    }

    $verificador = substr($limpio, -1);
    $cuerpo = substr($limpio, 0, -1);
    if ($cuerpo === '' || preg_match('/^\d+$/', $cuerpo) !== 1) {
        return null;
    }

    $suma = 0;
    $factor = 2;
    for ($indice = strlen($cuerpo) - 1; $indice >= 0; $indice--) {
        $suma += (int) $cuerpo[$indice] * $factor;
        $factor = $factor === 7 ? 2 : $factor + 1;
    }
    $resto = 11 - ($suma % 11);
    $esperado = $resto === 11 ? '0' : ($resto === 10 ? 'K' : (string) $resto);
    if ($verificador !== $esperado) {
        return null;
    }

    return number_format((int) $cuerpo, 0, '', '.') . '-' . $verificador;
}

function reservarBloque(array $cliente, string $fecha, string $hora, string $servicio): array
{
    $validacion = validarClienteReserva($cliente);
    if ($validacion['errores'] !== []) {
        return ['error' => $validacion['errores'][0], 'cita' => null];
    }
    $cliente = $validacion['datos'];

    if (servicioValido($servicio) === null) {
        return ['error' => 'Elige un tipo de trámite.', 'cita' => null];
    }

    if (!fechaEnAgenda($fecha) || !normalizarHora($hora)) {
        return ['error' => 'Ese horario no está disponible.', 'cita' => null];
    }

    $hora = normalizarHora($hora);
    if (inicioDeBloque($fecha, $hora) <= new DateTimeImmutable('now', zonaHoraria())) {
        return ['error' => 'Ese horario ya pasó.', 'cita' => null];
    }

    $pdo = base();
    $pdo->beginTransaction();

    try {
        $bloqueo = $pdo->prepare(
            'SELECT id FROM bloqueos WHERE fecha = ? AND hora_inicio = ? FOR UPDATE'
        );
        $bloqueo->execute([$fecha, $hora]);
        if ($bloqueo->fetch()) {
            $pdo->rollBack();
            return ['error' => 'Ese horario está cerrado.', 'cita' => null];
        }

        $ocupado = $pdo->prepare(
            'SELECT id FROM citas
             WHERE fecha = ? AND hora_inicio = ? AND estado IN (\'pendiente\', \'reservada\')
             FOR UPDATE'
        );
        $ocupado->execute([$fecha, $hora]);
        if ($ocupado->fetch()) {
            $pdo->rollBack();
            return ['error' => 'Ese horario acaba de ser reservado.', 'cita' => null];
        }

        $token = bin2hex(random_bytes(32));
        $alta = $pdo->prepare(
            'INSERT INTO citas
                (usuario_id, cliente_nombre, cliente_apellido, cliente_email, cliente_telefono,
                 cliente_tipo_persona, fecha, hora_inicio, servicio, estado, token_hash)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, \'pendiente\', ?)'
        );
        $alta->execute([
            $cliente['nombre'],
            $cliente['apellido'],
            $cliente['email'],
            $cliente['telefono'],
            $cliente['tipo_persona'],
            $fecha,
            $hora,
            $servicio,
            hash('sha256', $token),
        ]);
        $citaId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['error' => 'Ese horario acaba de ser reservado.', 'cita' => null];
        }
        throw $e;
    }

    return [
        'error' => null,
        'cita' => [
            'id' => $citaId,
            'cliente_nombre' => $cliente['nombre'],
            'cliente_apellido' => $cliente['apellido'],
            'cliente_email' => $cliente['email'],
            'cliente_telefono' => $cliente['telefono'],
            'cliente_tipo_persona' => $cliente['tipo_persona'],
            'fecha' => $fecha,
            'hora_inicio' => $hora,
            'servicio' => $servicio,
            'estado' => 'pendiente',
            'token' => $token,
        ],
    ];
}

function citaPorId(int $citaId): ?array
{
    $consulta = base()->prepare(
        'SELECT id, usuario_id, cliente_nombre, cliente_apellido, cliente_email,
                cliente_telefono, cliente_tipo_persona, fecha, hora_inicio, hora_fin,
                servicio, estado, token_hash, kora_bloque_id, kora_reserva_id
         FROM citas WHERE id = ?'
    );
    $consulta->execute([$citaId]);
    $cita = $consulta->fetch();

    return $cita ?: null;
}

function cancelarReservaCliente(int $usuarioId, int $citaId): ?string
{
    $cita = citaPorId($citaId);
    if (!$cita || (int) $cita['usuario_id'] !== $usuarioId || $cita['estado'] !== 'reservada') {
        return 'No encontramos esa asesoría.';
    }

    $inicio = inicioDeBloque($cita['fecha'], $cita['hora_inicio']);
    if ($inicio <= new DateTimeImmutable('now', zonaHoraria())) {
        return 'Esa asesoría ya empezó y no se puede cancelar.';
    }

    $consulta = base()->prepare(
        'UPDATE citas SET estado = \'cancelada\'
         WHERE id = ? AND usuario_id = ? AND estado = \'reservada\''
    );
    $consulta->execute([$citaId, $usuarioId]);

    return $consulta->rowCount() === 1 ? null : 'No encontramos esa asesoría.';
}

function cancelarReservaAnfitrion(int $citaId): array
{
    $cita = citaPorId($citaId);
    if (!$cita || $cita['estado'] !== 'reservada') {
        return ['error' => 'No encontramos esa asesoría.', 'cita' => null];
    }

    $consulta = base()->prepare(
        'UPDATE citas SET estado = \'cancelada\' WHERE id = ? AND estado = \'reservada\''
    );
    $consulta->execute([$citaId]);

    if ($consulta->rowCount() !== 1) {
        return ['error' => 'No encontramos esa asesoría.', 'cita' => null];
    }

    $cita['estado'] = 'cancelada';
    return ['error' => null, 'cita' => $cita];
}

function resolverSolicitud(int $citaId, string $nuevoEstado, ?string $token): array
{
    if (!in_array($nuevoEstado, ['reservada', 'cancelada'], true)) {
        return ['error' => 'Esta solicitud ya no está pendiente.', 'cita' => null];
    }

    $cita = citaPorId($citaId);
    $hash = $token === null ? null : hash('sha256', $token);
    if (
        !$cita
        || $cita['estado'] !== 'pendiente'
        || ($hash !== null && (!is_string($cita['token_hash']) || !hash_equals($cita['token_hash'], $hash)))
    ) {
        return ['error' => 'Esta solicitud ya no está pendiente.', 'cita' => null];
    }

    $sql = 'UPDATE citas SET estado = ?, token_hash = NULL WHERE id = ? AND estado = \'pendiente\'';
    $parametros = [$nuevoEstado, $citaId];
    if ($hash !== null) {
        $sql .= ' AND token_hash = ?';
        $parametros[] = $hash;
    }

    $consulta = base()->prepare($sql);
    $consulta->execute($parametros);
    if ($consulta->rowCount() !== 1) {
        return ['error' => 'Esta solicitud ya no está pendiente.', 'cita' => null];
    }

    $cita['estado'] = $nuevoEstado;
    unset($cita['token_hash']);

    return ['error' => null, 'cita' => $cita];
}

function cerrarBloque(string $fecha, string $hora): ?string
{
    if (!fechaEnAgenda($fecha) || !($hora = normalizarHora($hora))) {
        return 'Ese horario no está en la agenda.';
    }

    $ocupado = base()->prepare(
        'SELECT id FROM citas WHERE fecha = ? AND hora_inicio = ? AND estado IN (\'pendiente\', \'reservada\')'
    );
    $ocupado->execute([$fecha, $hora]);
    if ($ocupado->fetch()) {
        return 'reservado';
    }

    $alta = base()->prepare(
        'INSERT INTO bloqueos (fecha, hora_inicio) VALUES (?, ?)'
    );
    try {
        $alta->execute([$fecha, $hora]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return 'ya';
        }
        throw $e;
    }

    return null;
}

function abrirBloque(string $fecha, string $hora): ?string
{
    if (!fechaEnAgenda($fecha) || !($hora = normalizarHora($hora))) {
        return 'Ese horario no está en la agenda.';
    }

    $baja = base()->prepare(
        'DELETE FROM bloqueos WHERE fecha = ? AND hora_inicio = ?'
    );
    $baja->execute([$fecha, $hora]);

    return $baja->rowCount() === 1 ? null : 'Ese horario ya estaba abierto.';
}

function cerrarDia(string $fecha): string
{
    if (!fechaEnAgenda($fecha)) {
        return 'Ese día no está en la agenda.';
    }

    $cerrados = 0;
    $conCita = 0;
    foreach (horasDeAgenda() as $hora) {
        $resultado = cerrarBloque($fecha, $hora);
        if ($resultado === null) {
            $cerrados++;
        } elseif ($resultado === 'reservado') {
            $conCita++;
        }
    }

    if ($cerrados === 0 && $conCita === 0) {
        return 'Ese día ya estaba cerrado.';
    }
    if ($conCita > 0) {
        return 'Se cerraron ' . $cerrados . ' horarios libres. Quedaron ' . $conCita
            . ' con solicitud o asesoría: resuélvelas si también quieres cerrarlos.';
    }

    return 'Día cerrado.';
}

function abrirDia(string $fecha): string
{
    if (!fechaEnAgenda($fecha)) {
        return 'Ese día no está en la agenda.';
    }

    $baja = base()->prepare('DELETE FROM bloqueos WHERE fecha = ?');
    $baja->execute([$fecha]);

    return $baja->rowCount() > 0 ? 'Día abierto.' : 'Ese día ya estaba abierto.';
}

function citasDelCliente(int $usuarioId): array
{
    $consulta = base()->prepare(
        'SELECT id, fecha, hora_inicio, servicio, estado
         FROM citas
         WHERE usuario_id = ?
         ORDER BY fecha ASC, hora_inicio ASC'
    );
    $consulta->execute([$usuarioId]);

    return $consulta->fetchAll();
}
