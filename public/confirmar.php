<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$citaId = (int) ($_POST['cita'] ?? $_GET['cita'] ?? 0);
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$resultado = (string) ($_GET['resultado'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido() || !in_array((string) ($_POST['accion'] ?? ''), ['confirmar', 'rechazar'], true)) {
        aviso('error', 'La solicitud no es válida. Vuelve a intentar.');
        redirigir('confirmar.php?cita=' . $citaId . '&token=' . rawurlencode($token));
    }

    $confirmar = (string) $_POST['accion'] === 'confirmar';
    $resuelto = resolverSolicitud($citaId, $confirmar ? 'reservada' : 'cancelada', $token);
    if ($resuelto['error'] !== null) {
        aviso('error', $resuelto['error']);
        redirigir('confirmar.php?resultado=usada');
    }

    $correoEnviado = $confirmar
        ? notificarConfirmacion($resuelto['cita'])
        : notificarRechazo($resuelto['cita']);
    $mensaje = $confirmar
        ? 'Confirmaste la asesoría.'
        : 'Rechazaste la solicitud. El horario volvió a quedar libre.';
    if (!$correoEnviado) {
        $mensaje .= ' No pudimos enviar el aviso al cliente.';
    }
    aviso($correoEnviado ? 'ok' : 'error', $mensaje);
    redirigir('confirmar.php?resultado=' . ($confirmar ? 'confirmada' : 'rechazada'));
}

$cita = $citaId > 0 && $token !== '' ? citaPorId($citaId) : null;
$hash = $token === '' ? '' : hash('sha256', $token);
$pendiente = is_array($cita)
    && $cita['estado'] === 'pendiente'
    && is_string($cita['token_hash'])
    && hash_equals($cita['token_hash'], $hash);

abrirPagina('Confirmar cita');
?>
<section class="articulo">
  <?php if ($pendiente): ?>
    <?php $resumen = resumenCita($cita); ?>
    <h1>Solicitud en espera</h1>
    <p><?= e((string) $cita['cliente_nombre']) ?> pidió <?= e($resumen['servicio']) ?> para el <?= e($resumen['fecha']) ?>, <?= e($resumen['hora']) ?>.</p>
    <div class="acciones-articulo">
      <form method="post" action="confirmar.php" data-confirmar="<?= e('¿Confirmar la asesoría de ' . $cita['cliente_nombre'] . ' del ' . $resumen['fecha'] . ', ' . $resumen['hora'] . '?') ?>">
        <?= campoCsrf() ?>
        <input type="hidden" name="cita" value="<?= e((string) $citaId) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="accion" value="confirmar">
        <button class="boton" type="submit">Confirmar</button>
      </form>
      <form method="post" action="confirmar.php" data-confirmar="<?= e('¿Rechazar la solicitud de ' . $cita['cliente_nombre'] . ' del ' . $resumen['fecha'] . ', ' . $resumen['hora'] . '?') ?>">
        <?= campoCsrf() ?>
        <input type="hidden" name="cita" value="<?= e((string) $citaId) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="accion" value="rechazar">
        <button class="boton peligro" type="submit">Rechazar</button>
      </form>
    </div>
  <?php elseif ($resultado === 'confirmada'): ?>
    <h1>Cita confirmada</h1>
    <p>La asesoría quedó reservada.</p>
  <?php elseif ($resultado === 'rechazada'): ?>
    <h1>Solicitud rechazada</h1>
    <p>El horario volvió a quedar libre.</p>
  <?php else: ?>
    <h1>Solicitud no disponible</h1>
    <p>Esta solicitud ya no está pendiente.</p>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
