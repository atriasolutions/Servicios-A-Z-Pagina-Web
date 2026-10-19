<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

exigirRol('anfitrion');

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$existente = $id > 0 ? obtenerArticulo($id) : null;
$titulo = (string) ($existente['titulo'] ?? '');
$cuerpo = (string) ($existente['cuerpo'] ?? '');
$errores = [];

if ($id > 0 && $existente === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    abrirPagina('Editar artículo');
    ?>
    <section class="articulo">
      <h1>Artículo no encontrado</h1>
      <p>No encontramos ese artículo.</p>
      <p><a class="boton" href="articulos-admin.php">Volver al blog</a></p>
    </section>
    <?php
    cerrarPagina();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        $errores[] = 'La solicitud no es válida. Vuelve a intentar.';
    } elseif ((string) ($_POST['accion'] ?? '') === 'eliminar') {
        if (eliminarArticulo($id)) {
            aviso('ok', 'Eliminaste el artículo.');
        } else {
            aviso('error', 'Ese artículo ya no existe.');
        }
        redirigir('articulos-admin.php');
    } else {
        $titulo = (string) ($_POST['titulo'] ?? '');
        $cuerpo = (string) ($_POST['cuerpo'] ?? '');
        $resultado = guardarArticulo($id > 0 ? $id : null, $titulo, $cuerpo);
        $errores = $resultado['errores'];
        if ($errores === [] && $resultado['id'] !== null) {
            aviso('ok', $id > 0 ? 'Guardaste los cambios.' : 'Publicaste el artículo.');
            redirigir('articulos-admin.php');
        }
    }
}

abrirPagina($id > 0 ? 'Editar artículo' : 'Nuevo artículo');
?>
<section class="articulo">
  <h1><?= $id > 0 ? 'Editar artículo' : 'Nuevo artículo' ?></h1>
  <p>El título y el texto quedan visibles para quien visite el sitio. Una línea en blanco separa los párrafos. Los botones aplican negrita, cursiva, lista o enlace al texto seleccionado.</p>
  <?php erroresDe($errores); ?>
  <form class="formulario editor-articulo" method="post" action="articulo-editar.php<?= $id > 0 ? '?id=' . e((string) $id) : '' ?>">
    <?= campoCsrf() ?>
    <?php if ($id > 0): ?>
      <input type="hidden" name="id" value="<?= e((string) $id) ?>">
    <?php endif; ?>
    <label>
      Título
      <input type="text" name="titulo" maxlength="160" required value="<?= e($titulo) ?>">
    </label>
    <div class="campo-cuerpo">
      <label for="cuerpo">Texto</label>
      <span class="barra-formato" data-formato>
        <button class="boton secundario" type="button" data-marca="negrita"><?= icono('negrita') ?><span>Negrita</span></button>
        <button class="boton secundario" type="button" data-marca="cursiva"><?= icono('cursiva') ?><span>Cursiva</span></button>
        <button class="boton secundario" type="button" data-marca="lista"><?= icono('lista') ?><span>Lista</span></button>
        <button class="boton secundario" type="button" data-marca="enlace"><?= icono('enlace') ?><span>Enlace</span></button>
      </span>
      <textarea id="cuerpo" name="cuerpo" rows="14" required><?= e($cuerpo) ?></textarea>
    </div>
    <div class="acciones-articulo">
      <button class="boton" type="submit">Guardar</button>
      <a class="boton secundario" href="articulos-admin.php">Cancelar</a>
    </div>
  </form>
  <?php if ($id > 0): ?>
    <form class="eliminar-articulo" method="post" action="articulo-editar.php" data-confirmar="<?= e('¿Eliminar el artículo «' . $titulo . '»?') ?>">
      <?= campoCsrf() ?>
      <input type="hidden" name="id" value="<?= e((string) $id) ?>">
      <input type="hidden" name="accion" value="eliminar">
      <button class="boton peligro" type="submit">Eliminar</button>
    </form>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
