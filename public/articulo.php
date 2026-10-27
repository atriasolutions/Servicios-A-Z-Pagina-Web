<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirRol('anfitrion');
    if (!csrfValido() || (string) ($_POST['accion'] ?? '') !== 'eliminar') {
        aviso('error', 'La solicitud no es válida. Vuelve a intentar.');
        redirigir('articulos.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    if (eliminarArticulo($id)) {
        aviso('ok', 'Eliminaste el artículo.');
    } else {
        aviso('error', 'Ese artículo ya no existe.');
    }
    redirigir('articulos.php');
}

$articulo = obtenerArticulo((int) ($_GET['id'] ?? 0), true);
if ($articulo === null) {
    http_response_code(404);
    abrirPagina('Artículo');
    ?>
    <section class="articulo">
      <h1>Artículo no encontrado</h1>
      <p>No encontramos ese artículo.</p>
      <p><a class="boton" href="articulos.php">Volver a los artículos</a></p>
    </section>
    <?php
    cerrarPagina();
    exit;
}

$usuario = usuarioActual();
$esAdmin = $usuario && $usuario['rol'] === 'anfitrion';
$id = (int) $articulo['id'];

abrirPagina((string) $articulo['titulo']);
?>
<article class="articulo">
  <div class="meta-articulo">
    <p class="fecha-articulo"><?= e(fechaArticulo((string) $articulo['publicado_en'])) ?></p>
    <?php if ($esAdmin): ?>
      <div class="acciones-articulo acciones-admin">
        <a class="boton secundario" href="articulo-editar.php?id=<?= e((string) $id) ?>">Editar</a>
        <form method="post" action="articulo.php" data-confirmar="<?= e('¿Eliminar el artículo «' . $articulo['titulo'] . '»?') ?>">
          <?= campoCsrf() ?>
          <input type="hidden" name="id" value="<?= e((string) $id) ?>">
          <input type="hidden" name="accion" value="eliminar">
          <button class="boton peligro" type="submit">Eliminar</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
  <h1><?= e((string) $articulo['titulo']) ?></h1>
  <div class="cuerpo">
    <?= htmlParrafos((string) $articulo['cuerpo']) ?>
  </div>
  <div class="acciones-articulo">
    <a class="boton secundario" href="articulos.php">Volver a los artículos</a>
  </div>
</article>
<?php
cerrarPagina();
