<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$pedido = servicioValido((string) ($_GET['servicio'] ?? ''));
if ($pedido !== null) {
    $_SESSION['servicio'] = $pedido;
}

$usuario = exigirRol('cliente');
$servicio = servicioValido((string) ($_POST['servicio'] ?? $_SESSION['servicio'] ?? ''));
$solicitada = fechaEnAgenda((string) ($_GET['fecha'] ?? $_POST['fecha'] ?? ''));
$fecha = $solicitada ?? fechasDeAgenda()[0];
$fechaIso = $fecha->format('Y-m-d');

$volver = 'horarios.php?fecha=' . urlencode($fechaIso);
if ($servicio !== null) {
    $volver .= '&servicio=' . urlencode($servicio);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        aviso('error', 'La solicitud no es válida. Vuelve a intentar.');
        redirigir($volver);
    }

    if ($servicio === null) {
        aviso('error', 'Elige un tipo de trámite.');
        redirigir('horarios.php');
    }

    $error = reservarBloque(
        (int) $usuario['id'],
        (string) ($_POST['fecha'] ?? ''),
        (string) ($_POST['hora'] ?? ''),
        $servicio
    );
    if ($error) {
        aviso('error', $error);
        redirigir($volver);
    }

    $horaConfirmada = normalizarHora((string) ($_POST['hora'] ?? ''));
    aviso(
        'ok',
        etiquetaServicio($servicio) . ' reservada para el ' . fechaLarga($fecha) . ', ' . etiquetaBloque((string) $horaConfirmada) . '.'
    );
    redirigir('mis-citas.php');
}

abrirPagina('Horarios');
?>
<section>
  <?php if ($servicio === null): ?>
    <h1>Elige el trámite</h1>
    <p>El horario que reserves queda ocupado. No se puede usar de nuevo para otro trámite.</p>
    <div class="tramites">
      <?php foreach (servicios() as $clave => $nombre): ?>
        <a class="tramite" href="horarios.php?servicio=<?= e($clave) ?>"><?= e($nombre) ?></a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <?php
      $periodo = cargarPeriodo();
      $bloques = bloquesDelDia($fecha, $periodo);
      $libres = array_values(array_filter(
          $bloques,
          static fn (array $bloque): bool => $bloque['estado'] === 'libre'
      ));
    ?>
    <h1><?= e(etiquetaServicio($servicio)) ?></h1>
    <p><?= e(textoHorario()) ?> Se muestran los próximos <?= e((string) configuracion()['agenda']['horizonte_dias']) ?> días. Un horario ocupado no se puede reservar para otro trámite.</p>
    <div class="tramites">
      <?php foreach (servicios() as $clave => $nombre): ?>
        <a class="tramite<?= $clave === $servicio ? ' activo' : '' ?>" href="horarios.php?fecha=<?= e($fechaIso) ?>&amp;servicio=<?= e($clave) ?>"><?= e($nombre) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="dias">
      <?php foreach (fechasDeAgenda() as $dia): ?>
        <?php
          $iso = $dia->format('Y-m-d');
          $cantidad = 0;
          foreach (bloquesDelDia($dia, $periodo) as $bloque) {
              if ($bloque['estado'] === 'libre') {
                  $cantidad++;
              }
          }
          $clase = $iso === $fechaIso ? 'dia-link activo' : 'dia-link';
          $cupo = $cantidad === 0 ? 'sin cupo' : $cantidad . ' libres';
        ?>
        <a class="<?= e($clase) ?>" href="horarios.php?fecha=<?= e($iso) ?>&amp;servicio=<?= e($servicio) ?>"<?= $iso === $fechaIso ? ' aria-current="date"' : '' ?>>
          <span><?= e(fechaCorta($dia)) ?></span>
          <strong><?= e($dia->format('j')) ?></strong>
          <small><?= e($cupo) ?></small>
        </a>
      <?php endforeach; ?>
    </div>

    <h2><?= e(fechaLarga($fecha)) ?></h2>
    <?php if ($libres === []): ?>
      <p class="nota">No hay horarios libres este día.</p>
    <?php else: ?>
      <form class="grilla" method="post" action="horarios.php" data-reserva data-dia="<?= e(fechaLarga($fecha)) ?>" data-servicio="<?= e(etiquetaServicio($servicio)) ?>">
        <?= campoCsrf() ?>
        <input type="hidden" name="fecha" value="<?= e($fechaIso) ?>">
        <input type="hidden" name="servicio" value="<?= e($servicio) ?>">
        <?php foreach ($libres as $bloque): ?>
          <label class="bloque">
            <input type="radio" name="hora" value="<?= e($bloque['hora']) ?>" required>
            <span><?= e($bloque['etiqueta']) ?></span>
          </label>
        <?php endforeach; ?>
        <button class="boton" type="submit">Reservar</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
