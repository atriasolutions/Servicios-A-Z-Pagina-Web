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
            redirigir('agenda.php');
        }
    }
}

abrirPagina('Acceso administrativo');
?>
<section>
  <h1>Acceso administrativo</h1>
  <p>Este ingreso es exclusivo para gestionar horarios y reservas.</p>
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
</section>
<?php
cerrarPagina();
