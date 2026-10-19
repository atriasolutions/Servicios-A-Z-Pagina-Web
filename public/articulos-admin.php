<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

exigirRol('anfitrion');

$articulos = listarArticulos();

abrirPagina('Blog');
?>
<section class="panel-blog">
  <div class="encabezado-articulos">
    <div>
      <h1>Blog</h1>
      <p>Administra los artículos publicados en el sitio.</p>
    </div>
    <div class="acciones-articulo">
      <a class="boton" href="articulo-editar.php">Nuevo artículo</a>
      <a class="boton secundario" href="articulos.php">Ver el blog público</a>
    </div>
  </div>

  <?php if ($articulos === []): ?>
    <p>Todavía no hay artículos.</p>
  <?php else: ?>
    <ul class="lista-articulos lista-admin">
      <?php foreach ($articulos as $articulo): ?>
        <?php $id = (int) $articulo['id']; ?>
        <li class="tarjeta-articulo">
          <p class="fecha-articulo"><?= e(fechaArticulo((string) $articulo['publicado_en'])) ?></p>
          <h2><a href="articulo.php?id=<?= e((string) $id) ?>"><?= e((string) $articulo['titulo']) ?></a></h2>
          <p><?= e(extractoArticulo((string) $articulo['cuerpo'])) ?></p>
          <div class="acciones-articulo">
            <a class="boton secundario" href="articulo.php?id=<?= e((string) $id) ?>">Ver</a>
            <a class="boton secundario" href="articulo-editar.php?id=<?= e((string) $id) ?>">Editar</a>
            <form method="post" action="articulo-editar.php" data-confirmar="<?= e('¿Eliminar el artículo «' . $articulo['titulo'] . '»?') ?>">
              <?= campoCsrf() ?>
              <input type="hidden" name="id" value="<?= e((string) $id) ?>">
              <input type="hidden" name="accion" value="eliminar">
              <button class="boton peligro" type="submit">Eliminar</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
