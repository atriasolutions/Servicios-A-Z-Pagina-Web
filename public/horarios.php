<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (isset($_GET['elegir'])) {
    unset($_SESSION['servicio']);
}

$pedido = servicioValido((string) ($_GET['servicio'] ?? ''));
if ($pedido !== null) {
    $_SESSION['servicio'] = $pedido;
}

$servicio = servicioValido((string) ($_POST['servicio'] ?? $_SESSION['servicio'] ?? ''));
$solicitada = fechaEnAgenda((string) ($_GET['fecha'] ?? $_POST['fecha'] ?? ''));
$fecha = $solicitada ?? fechasDeAgenda()[0];
$fechaIso = $fecha->format('Y-m-d');
$errores = [];
$datos = [
    'nombre' => trim((string) ($_POST['nombre'] ?? '')),
    'email' => normalizarEmail((string) ($_POST['email'] ?? '')),
    'telefono' => trim((string) ($_POST['telefono'] ?? '')),
];

$volver = 'horarios.php?fecha=' . urlencode($fechaIso);
if ($servicio !== null) {
    $volver .= '&servicio=' . urlencode($servicio);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        $errores[] = 'La solicitud no es válida. Vuelve a intentar.';
    } elseif ($servicio === null) {
        $errores[] = 'Elige un tipo de trámite.';
    } else {
        $validacion = validarClienteReserva($datos);
        $datos = $validacion['datos'];
        $errores = $validacion['errores'];

        if ($errores === []) {
            $resultado = reservarBloque(
                $datos,
                (string) ($_POST['fecha'] ?? ''),
                (string) ($_POST['hora'] ?? ''),
                $servicio
            );
            if ($resultado['error'] !== null) {
                $errores[] = $resultado['error'];
            } else {
                $cita = $resultado['cita'];
                $correoEnviado = notificarReserva($cita);
                $mensaje = 'Tu solicitud de ' . etiquetaServicio($servicio) . ' quedó en espera para el '
                    . fechaLarga($fecha) . ', ' . etiquetaBloque((string) $cita['hora_inicio'])
                    . '. Te avisaremos cuando sea confirmada.';
                if (!$correoEnviado) {
                    $mensaje .= ' No pudimos enviar el aviso por correo.';
                }
                aviso($correoEnviado ? 'ok' : 'error', $mensaje);
                redirigir($volver);
            }
        }
    }
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
    <p><?= e(textoHorario()) ?> Puedes reservar hasta un mes de anticipación. La hora queda en espera hasta que la confirmemos y no se ofrece a otra persona.</p>
    <?php erroresDe($errores); ?>
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
      <form class="formulario reserva-publica" method="post" action="horarios.php" data-reserva data-dia="<?= e(fechaLarga($fecha)) ?>" data-servicio="<?= e(etiquetaServicio($servicio)) ?>">
        <?= campoCsrf() ?>
        <input type="hidden" name="fecha" value="<?= e($fechaIso) ?>">
        <input type="hidden" name="servicio" value="<?= e($servicio) ?>">
        <div class="datos-reserva">
          <label>
            Nombre
            <input type="text" name="nombre" required maxlength="120" autocomplete="name" value="<?= e($datos['nombre']) ?>">
          </label>
          <label>
            Correo
            <input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= e($datos['email']) ?>">
          </label>
          <label>
            Teléfono
            <input type="tel" name="telefono" required maxlength="40" autocomplete="tel" value="<?= e($datos['telefono']) ?>">
          </label>
        </div>
        <fieldset>
          <legend>Elige una hora</legend>
          <div class="grilla">
            <?php foreach ($libres as $bloque): ?>
              <label class="bloque">
                <input type="radio" name="hora" value="<?= e($bloque['hora']) ?>" required>
                <span><?= e($bloque['etiqueta']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="acciones acciones-reserva">
          <a class="boton secundario" href="horarios.php?elegir=1">Volver</a>
          <button class="boton" type="submit">Reservar</button>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
