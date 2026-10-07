<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = usuarioActual();
if ($usuario) {
    redirigir($usuario['rol'] === 'anfitrion' ? 'agenda.php' : 'horarios.php');
}

$errores = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        $errores[] = 'La solicitud no es válida. Vuelve a intentar.';
    } else {
        $email = normalizarEmail((string) ($_POST['email'] ?? ''));
        $clave = (string) ($_POST['clave'] ?? '');
        if ($email === '' || $clave === '') {
            $errores[] = 'Completa el correo y la contraseña.';
        } elseif (!iniciarSesion($email, $clave)) {
            $errores[] = 'Correo o contraseña incorrectos.';
        } else {
            $ingreso = usuarioActual();
            redirigir($ingreso && $ingreso['rol'] === 'anfitrion' ? 'agenda.php' : 'horarios.php');
        }
    }
}

abrirPagina('Entrar');
?>
<section class="tarjeta">
  <h1>Entrar</h1>
  <p>Los clientes y Servicios Contables A&amp;Z usan el mismo formulario. La agenda se abre según la cuenta.</p>
  <?php erroresDe($errores); ?>
  <form class="formulario" method="post" action="login.php">
    <?= campoCsrf() ?>
    <label>
      Correo
      <input type="email" name="email" required maxlength="190" autocomplete="username" value="<?= e($email) ?>">
    </label>
    <label>
      Contraseña
      <input type="password" name="clave" required maxlength="72" autocomplete="current-password">
    </label>
    <button class="boton" type="submit">Entrar</button>
  </form>
  <p class="nota">¿Primera vez? <a href="registro.php">Crear cuenta</a></p>
</section>
<?php
cerrarPagina();
