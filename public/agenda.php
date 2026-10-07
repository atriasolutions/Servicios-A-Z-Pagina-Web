<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

exigirRol('anfitrion');

$solicitada = fechaEnAgenda((string) ($_GET['fecha'] ?? $_POST['fecha'] ?? ''));
$fecha = $solicitada ?? fechasDeAgenda()[0];
$fechaIso = $fecha->format('Y-m-d');
$volver = 'agenda.php?fecha=' . urlencode($fechaIso);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        aviso('error', 'La solicitud no es válida. Vuelve a intentar.');
        redirigir($volver);
    }

    $accion = (string) ($_POST['accion'] ?? '');
    $hora = (string) ($_POST['hora'] ?? '');
    $citaId = (int) ($_POST['cita_id'] ?? 0);
    $mensaje = 'Acción desconocida.';
    $tipo = 'error';

    if ($accion === 'cerrar') {
        $resultado = cerrarBloque($fechaIso, $hora);
        if ($resultado === null) {
            $mensaje = 'Horario cerrado.';
            $tipo = 'ok';
        } elseif ($resultado === 'ya') {
            $mensaje = 'Ese horario ya estaba cerrado.';
            $tipo = 'ok';
        } elseif ($resultado === 'reservado') {
            $mensaje = 'Ese horario tiene una asesoría. Cancela esa asesoría si quieres cerrarlo.';
        } else {
            $mensaje = $resultado;
        }
    } elseif ($accion === 'abrir') {
        $resultado = abrirBloque($fechaIso, $hora);
        $tipo = $resultado === null ? 'ok' : 'error';
        $mensaje = $resultado ?? 'Horario abierto.';
    } elseif ($accion === 'cerrar_dia') {
        $mensaje = cerrarDia($fechaIso);
        $tipo = 'ok';
    } elseif ($accion === 'abrir_dia') {
        $mensaje = abrirDia($fechaIso);
        $tipo = 'ok';
    } elseif ($accion === 'cancelar_cita') {
        $resultado = cancelarReservaAnfitrion($citaId);
        $tipo = $resultado === null ? 'ok' : 'error';
        $mensaje = $resultado ?? 'Cancelaste la asesoría. El horario volvió a quedar libre.';
    }

    aviso($tipo, $mensaje);
    redirigir($volver);
}

$periodo = cargarPeriodo();
$bloques = bloquesDelDia($fecha, $periodo);

abrirPagina('Agenda');
?>
<section>
  <h1>Agenda de asesorías</h1>
  <p><?= e(textoHorario()) ?> Cierra un horario o un día entero para que nadie pueda reservarlo.</p>
  <div class="dias">
    <?php foreach (fechasDeAgenda() as $dia): ?>
      <?php $iso = $dia->format('Y-m-d'); ?>
      <a class="<?= $iso === $fechaIso ? 'dia-link activo' : 'dia-link' ?>" href="agenda.php?fecha=<?= e($iso) ?>"<?= $iso === $fechaIso ? ' aria-current="date"' : '' ?>>
        <span><?= e(fechaCorta($dia)) ?></span>
        <strong><?= e($dia->format('j')) ?></strong>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="encabezado-dia">
    <h2><?= e(fechaLarga($fecha)) ?></h2>
    <form method="post" action="agenda.php" data-confirmar="¿Cerrar los horarios libres de este día?">
      <?= campoCsrf() ?>
      <input type="hidden" name="fecha" value="<?= e($fechaIso) ?>">
      <input type="hidden" name="accion" value="cerrar_dia">
      <button class="boton secundario" type="submit">Cerrar día</button>
    </form>
    <form method="post" action="agenda.php" data-confirmar="¿Abrir los horarios cerrados de este día?">
      <?= campoCsrf() ?>
      <input type="hidden" name="fecha" value="<?= e($fechaIso) ?>">
      <input type="hidden" name="accion" value="abrir_dia">
      <button class="boton secundario" type="submit">Abrir día</button>
    </form>
  </div>

  <ul class="franjas">
    <?php foreach ($bloques as $bloque): ?>
      <li class="franja <?= e($bloque['estado']) ?>">
        <div>
          <strong><?= e($bloque['etiqueta']) ?></strong>
          <?php if ($bloque['estado'] === 'reservado'): ?>
            <span class="estado">Reservado por <?= e((string) $bloque['cliente']) ?></span>
            <span><?= e(etiquetaServicio($bloque['servicio'] ?? null)) ?></span>
            <?php if ($bloque['telefono'] || $bloque['email']): ?>
              <span class="nota">
                <?= e((string) $bloque['telefono']) ?>
                <?php if ($bloque['telefono'] && $bloque['email']): ?> · <?php endif; ?>
                <?= e((string) $bloque['email']) ?>
              </span>
            <?php endif; ?>
          <?php elseif ($bloque['estado'] === 'cerrado'): ?>
            <span class="estado">Cerrado</span>
          <?php elseif ($bloque['estado'] === 'pasado'): ?>
            <span class="estado">Pasado</span>
          <?php else: ?>
            <span class="estado">Libre</span>
          <?php endif; ?>
        </div>
        <div class="acciones-franja">
          <?php if ($bloque['estado'] === 'libre'): ?>
            <form method="post" action="agenda.php" data-confirmar="¿Cerrar este horario?">
              <?= campoCsrf() ?>
              <input type="hidden" name="fecha" value="<?= e($fechaIso) ?>">
              <input type="hidden" name="hora" value="<?= e($bloque['hora']) ?>">
              <input type="hidden" name="accion" value="cerrar">
              <button class="boton secundario" type="submit">Cerrar</button>
            </form>
          <?php elseif ($bloque['estado'] === 'cerrado'): ?>
            <form method="post" action="agenda.php" data-confirmar="¿Abrir este horario?">
              <?= campoCsrf() ?>
              <input type="hidden" name="fecha" value="<?= e($fechaIso) ?>">
              <input type="hidden" name="hora" value="<?= e($bloque['hora']) ?>">
              <input type="hidden" name="accion" value="abrir">
              <button class="boton secundario" type="submit">Abrir</button>
            </form>
          <?php elseif ($bloque['estado'] === 'reservado'): ?>
            <form method="post" action="agenda.php" data-confirmar="<?= e('¿Cancelar la asesoría de ' . $bloque['cliente'] . '?') ?>">
              <?= campoCsrf() ?>
              <input type="hidden" name="fecha" value="<?= e($fechaIso) ?>">
              <input type="hidden" name="cita_id" value="<?= e((string) $bloque['cita_id']) ?>">
              <input type="hidden" name="accion" value="cancelar_cita">
              <button class="boton peligro" type="submit">Cancelar asesoría</button>
            </form>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php
cerrarPagina();
