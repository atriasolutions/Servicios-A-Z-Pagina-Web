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

function fechasDeAgenda(): array
{
    $cantidad = (int) (configuracion()['agenda']['horizonte_dias'] ?? 0);
    if ($cantidad < 1 || $cantidad > 90) {
        throw new RuntimeException('El horizonte de la agenda en config/config.php no es válido.');
    }

    $hoy = new DateTimeImmutable('today', zonaHoraria());
    $fechas = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $fechas[] = $hoy->modify('+' . $i . ' days');
    }

    return $fechas;
}

function fechaEnAgenda(string $valor): ?DateTimeImmutable
{
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, zonaHoraria());
    if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
        return null;
    }

    $fechas = fechasDeAgenda();
    $minimo = $fechas[0]->format('Y-m-d');
    $maximo = $fechas[array_key_last($fechas)]->format('Y-m-d');
    if ($valor < $minimo || $valor > $maximo) {
        return null;
    }

    return $fecha;
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
        'SELECT c.id, c.fecha, c.hora_inicio, c.usuario_id, u.nombre, u.telefono, u.email
         FROM citas c
         INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE c.estado = \'reservada\' AND c.fecha BETWEEN ? AND ?'
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
        ];
    }

    return $bloques;
}

function reservarBloque(int $usuarioId, string $fecha, string $hora): ?string
{
    if (!fechaEnAgenda($fecha) || !normalizarHora($hora)) {
        return 'Ese horario no está disponible.';
    }

    $hora = normalizarHora($hora);
    if (inicioDeBloque($fecha, $hora) <= new DateTimeImmutable('now', zonaHoraria())) {
        return 'Ese horario ya pasó.';
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
            return 'Ese horario está cerrado.';
        }

        $ocupado = $pdo->prepare(
            'SELECT id FROM citas
             WHERE fecha = ? AND hora_inicio = ? AND estado = \'reservada\'
             FOR UPDATE'
        );
        $ocupado->execute([$fecha, $hora]);
        if ($ocupado->fetch()) {
            $pdo->rollBack();
            return 'Ese horario acaba de ser reservado.';
        }

        $alta = $pdo->prepare(
            'INSERT INTO citas (usuario_id, fecha, hora_inicio, estado)
             VALUES (?, ?, ?, \'reservada\')'
        );
        $alta->execute([$usuarioId, $fecha, $hora]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return 'Ese horario acaba de ser reservado.';
        }
        throw $e;
    }

    return null;
}

function citaPorId(int $citaId): ?array
{
    $consulta = base()->prepare(
        'SELECT id, usuario_id, fecha, hora_inicio, estado FROM citas WHERE id = ?'
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

function cancelarReservaAnfitrion(int $citaId): ?string
{
    $cita = citaPorId($citaId);
    if (!$cita || $cita['estado'] !== 'reservada') {
        return 'No encontramos esa asesoría.';
    }

    $consulta = base()->prepare(
        'UPDATE citas SET estado = \'cancelada\' WHERE id = ? AND estado = \'reservada\''
    );
    $consulta->execute([$citaId]);

    return $consulta->rowCount() === 1 ? null : 'No encontramos esa asesoría.';
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
        'SELECT id, fecha, hora_inicio, estado
         FROM citas
         WHERE usuario_id = ?
         ORDER BY fecha ASC, hora_inicio ASC'
    );
    $consulta->execute([$usuarioId]);

    return $consulta->fetchAll();
}
