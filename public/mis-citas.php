<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigirRol('cliente');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        aviso('error', 'La solicitud no es válida. Vuelve a intentar.');
        redirigir('mis-citas.php');
    }

    $citaId = (int) ($_POST['cita_id'] ?? 0);
    $error = cancelarReservaCliente((int) $usuario['id'], $citaId);
    aviso($error ? 'error' : 'ok', $error ?? 'Cancelaste la asesoría. El horario volvió a quedar libre.');
    redirigir('mis-citas.php');
}

$ahora = new DateTimeImmutable('now', zonaHoraria());
$proximas = [];
$anteriores = [];
foreach (citasDelCliente((int) $usuario['id']) as $cita) {
    $inicio = inicioDeBloque($cita['fecha'], $cita['hora_inicio']);
    $cita['inicio'] = $inicio;
    if ($cita['estado'] === 'reservada' && $inicio > $ahora) {
        $proximas[] = $cita;
    } else {
        $anteriores[] = $cita;
    }
}
$anteriores = array_reverse($anteriores);

abrirPagina('Mis asesorías');
?>
<section>
  <h1>Mis asesorías</h1>
  <p>Hola, <?= e($usuario['nombre']) ?>. Puedes cancelar una asesoría mientras el horario no haya empezado.</p>

  <h2>Próximas</h2>
  <?php if ($proximas === []): ?>
    <p class="nota">No tienes asesorías próximas. <a href="horarios.php">Ver horarios libres</a></p>
  <?php else: ?>
    <ul class="lista-citas">
      <?php foreach ($proximas as $cita): ?>
        <li>
          <div>
            <strong><?= e(fechaLarga($cita['inicio'])) ?></strong>
            <span><?= e(etiquetaBloque($cita['hora_inicio'])) ?></span>
            <span><?= e(etiquetaServicio($cita['servicio'] ?? null)) ?></span>
            <span class="estado">Reservada</span>
          </div>
          <form method="post" action="mis-citas.php" data-confirmar="¿Cancelar esta asesoría?">
            <?= campoCsrf() ?>
            <input type="hidden" name="cita_id" value="<?= e((string) $cita['id']) ?>">
            <button class="boton peligro" type="submit">Cancelar</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($anteriores !== []): ?>
    <h2>Anteriores</h2>
    <ul class="lista-citas">
      <?php foreach ($anteriores as $cita): ?>
        <li>
          <div>
            <strong><?= e(fechaLarga($cita['inicio'])) ?></strong>
            <span><?= e(etiquetaBloque($cita['hora_inicio'])) ?></span>
            <span><?= e(etiquetaServicio($cita['servicio'] ?? null)) ?></span>
            <span class="estado"><?= $cita['estado'] === 'reservada' ? 'Realizada' : 'Cancelada' ?></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
