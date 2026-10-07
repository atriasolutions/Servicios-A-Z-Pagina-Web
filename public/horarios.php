<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigirRol('cliente');
$solicitada = fechaEnAgenda((string) ($_GET['fecha'] ?? $_POST['fecha'] ?? ''));
$fecha = $solicitada ?? fechasDeAgenda()[0];
$fechaIso = $fecha->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $volver = 'horarios.php?fecha=' . urlencode($fechaIso);
    if (!csrfValido()) {
        aviso('error', 'La solicitud no es válida. Vuelve a intentar.');
        redirigir($volver);
    }

    $error = reservarBloque(
        (int) $usuario['id'],
        (string) ($_POST['fecha'] ?? ''),
        (string) ($_POST['hora'] ?? '')
    );
    if ($error) {
        aviso('error', $error);
        redirigir($volver);
    }

    $horaConfirmada = normalizarHora((string) ($_POST['hora'] ?? ''));
    aviso('ok', 'Asesoría reservada para el ' . fechaLarga($fecha) . ', ' . etiquetaBloque((string) $horaConfirmada) . '.');
    redirigir('mis-citas.php');
}

$periodo = cargarPeriodo();
$bloques = bloquesDelDia($fecha, $periodo);
$libres = array_values(array_filter(
    $bloques,
    static fn (array $bloque): bool => $bloque['estado'] === 'libre'
));

abrirPagina('Horarios');
?>
<section>
  <h1>Horarios para asesoría</h1>
  <p><?= e(textoHorario()) ?> Se muestran los próximos <?= e((string) configuracion()['agenda']['horizonte_dias']) ?> días.</p>
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
      <a class="<?= e($clase) ?>" href="horarios.php?fecha=<?= e($iso) ?>"<?= $iso === $fechaIso ? ' aria-current="date"' : '' ?>>
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
    <form class="grilla" method="post" action="horarios.php" data-reserva data-dia="<?= e(fechaLarga($fecha)) ?>">
      <?= campoCsrf() ?>
      <input type="hidden" name="fecha" value="<?= e($fechaIso) ?>">
      <?php foreach ($libres as $bloque): ?>
        <label class="bloque">
          <input type="radio" name="hora" value="<?= e($bloque['hora']) ?>" required>
          <span><?= e($bloque['etiqueta']) ?></span>
        </label>
      <?php endforeach; ?>
      <button class="boton" type="submit">Reservar</button>
    </form>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
