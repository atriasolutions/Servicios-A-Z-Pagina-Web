<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

exigirRol('anfitrion');

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$existente = $id > 0 ? obtenerArticulo($id) : null;
$titulo = (string) ($existente['titulo'] ?? '');
$cuerpo = (string) ($existente['cuerpo'] ?? '');
$estadoGuardado = (string) ($existente['estado'] ?? '');
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
        $estado = (string) ($_POST['estado'] ?? '');
        $resultado = guardarArticulo($id > 0 ? $id : null, $titulo, $cuerpo, $estado);
        $errores = $resultado['errores'];
        if ($errores === [] && $resultado['id'] !== null) {
            if ($estado === 'borrador') {
                $mensaje = 'Guardaste el borrador.';
            } elseif ($id > 0) {
                $mensaje = 'Publicaste los cambios.';
            } else {
                $mensaje = 'Publicaste el artículo.';
            }
            aviso('ok', $mensaje);
            redirigir('articulos-admin.php');
        }
    }
}

erroresDe($errores);
abrirPagina($id > 0 ? 'Editar artículo' : 'Nuevo artículo');
?>
<section class="articulo">
  <h1><?= $id > 0 ? 'Editar artículo' : 'Nuevo artículo' ?></h1>
  <p>Publicar muestra el artículo en el sitio. Un borrador queda solo en la administración. Una línea en blanco separa los párrafos. Los botones aplican negrita, cursiva, lista o enlace al texto seleccionado. Imagen sube un archivo y lo inserta en su propia línea.</p>
  <?php if ($id > 0): ?>
    <p>Ahora está <?= $estadoGuardado === 'publicado' ? 'publicado' : 'en borrador' ?>.</p>
  <?php endif; ?>
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
        <button class="boton secundario" type="button" data-marca="imagen"><?= icono('imagen') ?><span>Imagen</span></button>
        <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-imagen-articulo>
      </span>
      <textarea id="cuerpo" name="cuerpo" rows="24" required><?= e($cuerpo) ?></textarea>
    </div>
    <div class="acciones-articulo">
      <?php if ($estadoGuardado === 'publicado'): ?>
        <button class="boton" type="submit" name="estado" value="publicado">Publicar</button>
        <button class="boton secundario" type="submit" name="estado" value="borrador">Guardar borrador</button>
      <?php else: ?>
        <button class="boton secundario" type="submit" name="estado" value="borrador">Guardar borrador</button>
        <button class="boton" type="submit" name="estado" value="publicado">Publicar</button>
      <?php endif; ?>
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
