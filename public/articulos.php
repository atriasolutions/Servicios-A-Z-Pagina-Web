<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = usuarioActual();
$esAdmin = $usuario && $usuario['rol'] === 'anfitrion';
$paginaPedida = array_key_exists('pagina', $_GET) ? (int) $_GET['pagina'] : 1;
$lista = listarArticulosPublicados($paginaPedida);
if ($paginaPedida !== $lista['pagina']) {
    redirigir($lista['pagina'] > 1 ? 'articulos.php?pagina=' . $lista['pagina'] : 'articulos.php');
}
$articulos = $lista['articulos'];

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
        <?php
          $id = (int) $articulo['id'];
          $extracto = extractoArticulo((string) $articulo['recorte']);
          $imagen = imagenListadoArticulo($articulo);
        ?>
        <li class="tarjeta-articulo">
          <?php if ($imagen !== null): ?>
            <img class="miniatura-articulo" src="<?= e($imagen['url']) ?>" alt="<?= e($imagen['alt']) ?>" loading="lazy" decoding="async">
          <?php endif; ?>
          <div class="texto-articulo">
            <p class="fecha-articulo"><?= e(fechaArticulo((string) $articulo['publicado_en'])) ?></p>
            <h2><a href="articulo.php?id=<?= e((string) $id) ?>"><?= e((string) $articulo['titulo']) ?></a></h2>
            <?php if ($extracto !== ''): ?>
              <p class="extracto-articulo"><?= e($extracto) ?></p>
            <?php endif; ?>
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
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($lista['paginas'] > 1): ?>
      <nav class="paginacion-articulos" aria-label="Páginas de artículos">
        <?php if ($lista['pagina'] > 1): ?>
          <a class="boton secundario" href="<?= $lista['pagina'] === 2 ? 'articulos.php' : 'articulos.php?pagina=' . e((string) ($lista['pagina'] - 1)) ?>">Anterior</a>
        <?php endif; ?>
        <p>Página <?= e((string) $lista['pagina']) ?> de <?= e((string) $lista['paginas']) ?></p>
        <?php if ($lista['pagina'] < $lista['paginas']): ?>
          <a class="boton secundario" href="articulos.php?pagina=<?= e((string) ($lista['pagina'] + 1)) ?>">Siguiente</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
