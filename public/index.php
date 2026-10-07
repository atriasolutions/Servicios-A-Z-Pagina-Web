<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = usuarioActual();

abrirPagina('Inicio');
?>
<section class="hero">
  <p class="sobre">Asesorías contables</p>
  <h1>Reserva una asesoría</h1>
  <p><?= e(textoHorario()) ?> Entra con tu cuenta, elige un horario libre y la asesoría queda registrada.</p>
  <div class="acciones">
    <?php if ($usuario && $usuario['rol'] === 'anfitrion'): ?>
      <a class="boton" href="agenda.php">Ver la agenda</a>
    <?php elseif ($usuario): ?>
      <a class="boton" href="horarios.php">Ver horarios</a>
      <a class="boton secundario" href="mis-citas.php">Mis asesorías</a>
    <?php else: ?>
      <a class="boton" href="registro.php">Crear cuenta</a>
      <a class="boton secundario" href="login.php">Entrar</a>
    <?php endif; ?>
  </div>
</section>
<?php
cerrarPagina();
