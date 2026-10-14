<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = usuarioActual();
$esAdmin = $usuario && $usuario['rol'] === 'anfitrion';
$articulos = listarArticulos();

abrirPagina('Artículos');
?>
<section>
  <div class="encabezado-articulos">
    <div>
      <h1>Artículos</h1>
      <p>Textos publicados por <?= e((string) configuracion()['sitio']['nombre']) ?>.</p>
    </div>
    <?php if ($esAdmin): ?>
      <a class="boton" href="articulo-editar.php">Nuevo artículo</a>
    <?php endif; ?>
  </div>

  <?php if ($articulos === []): ?>
    <p>Todavía no hay artículos.</p>
  <?php else: ?>
    <ul class="lista-articulos">
      <?php foreach ($articulos as $articulo): ?>
        <?php $id = (int) $articulo['id']; ?>
        <li class="tarjeta-articulo">
          <p class="fecha-articulo"><?= e(fechaArticulo((string) $articulo['publicado_en'])) ?></p>
          <h2><a href="articulo.php?id=<?= e((string) $id) ?>"><?= e((string) $articulo['titulo']) ?></a></h2>
          <p><?= e(extractoArticulo((string) $articulo['cuerpo'])) ?></p>
          <div class="acciones-articulo">
            <a class="boton secundario" href="articulo.php?id=<?= e((string) $id) ?>">Leer artículo</a>
            <?php if ($esAdmin): ?>
              <a class="boton secundario" href="articulo-editar.php?id=<?= e((string) $id) ?>">Editar</a>
              <form method="post" action="articulo.php" data-confirmar="<?= e('¿Eliminar el artículo «' . $articulo['titulo'] . '»?') ?>">
                <?= campoCsrf() ?>
                <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                <input type="hidden" name="accion" value="eliminar">
                <button class="boton peligro" type="submit">Eliminar</button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
