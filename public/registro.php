<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = usuarioActual();
if ($usuario) {
    redirigir($usuario['rol'] === 'anfitrion' ? 'agenda.php' : 'horarios.php');
}

$errores = [];
$datos = ['nombre' => '', 'email' => '', 'telefono' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        $errores[] = 'La solicitud no es válida. Vuelve a intentar.';
    } else {
        $datos['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
        $datos['email'] = normalizarEmail((string) ($_POST['email'] ?? ''));
        $datos['telefono'] = trim((string) ($_POST['telefono'] ?? ''));
        $clave = (string) ($_POST['clave'] ?? '');
        $claveRepetida = (string) ($_POST['clave_repetida'] ?? '');
        $largoNombre = function_exists('mb_strlen')
            ? mb_strlen($datos['nombre'], 'UTF-8')
            : strlen($datos['nombre']);

        if ($largoNombre < 2 || $largoNombre > 120 || preg_match('/^[\p{L}\s\'.-]+$/u', $datos['nombre']) !== 1) {
            $errores[] = 'Escribe tu nombre, entre 2 y 120 letras.';
        }
        if (filter_var($datos['email'], FILTER_VALIDATE_EMAIL) === false || strlen($datos['email']) > 190) {
            $errores[] = 'El correo no es válido.';
        }
        $digitos = preg_replace('/\D+/', '', $datos['telefono']) ?? '';
        if (strlen($datos['telefono']) > 40 || strlen($digitos) < 6 || strlen($digitos) > 20) {
            $errores[] = 'El teléfono tiene que tener entre 6 y 20 números.';
        }
        if (strlen($clave) < 8 || strlen($clave) > 72) {
            $errores[] = 'La contraseña tiene que tener entre 8 y 72 caracteres.';
        }
        if ($clave !== $claveRepetida) {
            $errores[] = 'Las contraseñas no coinciden.';
        }

        if ($errores === []) {
            $id = registrarCliente($datos['nombre'], $datos['email'], $datos['telefono'], $clave);
            if ($id === null) {
                $errores[] = 'Ese correo ya está registrado.';
            } else {
                establecerSesion($id);
                aviso('ok', 'Cuenta creada. Ya puedes elegir un horario de asesoría.');
                redirigir('horarios.php');
            }
        }
    }
}

abrirPagina('Crear cuenta');
?>
<section>
  <h1>Crear cuenta</h1>
  <p>Con esta cuenta reservas una asesoría de servicios contables en un horario libre.</p>
  <?php erroresDe($errores); ?>
  <form class="formulario" method="post" action="registro.php">
    <?= campoCsrf() ?>
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
    <label>
      Contraseña
      <input type="password" name="clave" required minlength="8" maxlength="72" autocomplete="new-password">
    </label>
    <label>
      Repetir contraseña
      <input type="password" name="clave_repetida" required minlength="8" maxlength="72" autocomplete="new-password">
    </label>
    <button class="boton" type="submit">Crear cuenta</button>
  </form>
  <p class="nota">¿Ya tienes cuenta? <a href="login.php">Entrar</a></p>
</section>
<?php
cerrarPagina();
