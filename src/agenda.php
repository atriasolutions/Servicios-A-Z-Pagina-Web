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
        'SELECT id, fecha, hora_inicio, servicio,
                cliente_nombre AS nombre,
                cliente_telefono AS telefono,
                cliente_email AS email
         FROM citas
         WHERE estado = \'reservada\' AND fecha BETWEEN ? AND ?'
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

        if ($cita) {
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
            'cliente' => $cita['nombre'] ?? null,
            'telefono' => $cita['telefono'] ?? null,
            'email' => $cita['email'] ?? null,
            'servicio' => $cita['servicio'] ?? null,
        ];
    }

    return $bloques;
}

function servicios(): array
{
    return [
        'gestion_tributaria' => 'Gestión tributaria',
        'contabilidad_financiera' => 'Contabilidad financiera',
        'gestion_remuneraciones' => 'Gestión de remuneraciones',
    ];
}

function servicioValido(?string $clave): ?string
{
    $clave = (string) $clave;

    return array_key_exists($clave, servicios()) ? $clave : null;
}

function etiquetaServicio(?string $clave): string
{
    return servicios()[$clave] ?? 'Asesoría';
}

function validarClienteReserva(array $entrada): array
{
    $datos = [
        'nombre' => trim((string) ($entrada['nombre'] ?? '')),
        'email' => normalizarEmail((string) ($entrada['email'] ?? '')),
        'telefono' => trim((string) ($entrada['telefono'] ?? '')),
    ];
    $errores = [];
    $largoNombre = function_exists('mb_strlen')
        ? mb_strlen($datos['nombre'], 'UTF-8')
        : strlen($datos['nombre']);

    if ($largoNombre < 2 || $largoNombre > 120 || preg_match('/^[\p{L}\s\'.-]+$/u', $datos['nombre']) !== 1) {
        $errores[] = 'Escribe tu nombre, entre 2 y 120 letras.';
    }
    if (filter_var($datos['email'], FILTER_VALIDATE_EMAIL) === false || strlen($datos['email']) > 190) {
        $errores[] = 'El correo no es válido.';
    }
    $digitos = preg_replace('/\D+/', '', $datos['telefono']) ?? '';
    if (strlen($datos['telefono']) > 40 || strlen($digitos) < 6 || strlen($digitos) > 20) {
        $errores[] = 'El teléfono tiene que tener entre 6 y 20 números.';
    }

    return ['datos' => $datos, 'errores' => $errores];
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
             WHERE fecha = ? AND hora_inicio = ? AND estado = \'reservada\'
             FOR UPDATE'
        );
        $ocupado->execute([$fecha, $hora]);
        if ($ocupado->fetch()) {
            $pdo->rollBack();
            return ['error' => 'Ese horario acaba de ser reservado.', 'cita' => null];
        }

        $alta = $pdo->prepare(
            'INSERT INTO citas
                (usuario_id, cliente_nombre, cliente_email, cliente_telefono, fecha, hora_inicio, servicio, estado)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, \'reservada\')'
        );
        $alta->execute([
            $cliente['nombre'],
            $cliente['email'],
            $cliente['telefono'],
            $fecha,
            $hora,
            $servicio,
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
            'cliente_email' => $cliente['email'],
            'cliente_telefono' => $cliente['telefono'],
            'fecha' => $fecha,
            'hora_inicio' => $hora,
            'servicio' => $servicio,
            'estado' => 'reservada',
        ],
    ];
}

function citaPorId(int $citaId): ?array
{
    $consulta = base()->prepare(
        'SELECT id, usuario_id, cliente_nombre, cliente_email, cliente_telefono,
                fecha, hora_inicio, servicio, estado
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

function cerrarBloque(string $fecha, string $hora): ?string
{
    if (!fechaEnAgenda($fecha) || !($hora = normalizarHora($hora))) {
        return 'Ese horario no está en la agenda.';
    }

    $ocupado = base()->prepare(
        'SELECT id FROM citas WHERE fecha = ? AND hora_inicio = ? AND estado = \'reservada\''
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
            . ' con asesoría: cancela esas asesorías si también quieres cerrarlos.';
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
