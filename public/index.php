<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = usuarioActual();

abrirPagina('Inicio');
?>
<section class="hero">
  <div class="hero-texto">
    <p class="sobre">Asesorías contables</p>
    <h1>Reserva una asesoría</h1>
    <p><?= e(textoHorario()) ?> Entra con tu cuenta, elige el trámite y un horario libre. Ese horario queda ocupado para cualquier trámite.</p>
    <?php if (!$usuario || $usuario['rol'] === 'cliente'): ?>
      <div class="tramites">
        <?php foreach (servicios() as $clave => $nombre): ?>
          <a class="tramite" href="horarios.php?servicio=<?= e($clave) ?>"><?= e($nombre) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="acciones">
      <?php if ($usuario && $usuario['rol'] === 'anfitrion'): ?>
        <a class="boton" href="agenda.php">Ver la agenda</a>
      <?php elseif ($usuario): ?>
        <a class="boton secundario" href="mis-citas.php">Mis asesorías</a>
      <?php else: ?>
        <a class="boton" href="registro.php">Crear cuenta</a>
        <a class="boton secundario" href="login.php">Entrar</a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php
cerrarPagina();
