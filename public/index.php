<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = usuarioActual();
$nombreSitio = (string) configuracion()['sitio']['nombre'];
$iconosServicio = [
    'constitucion_empresas' => 'cuenta',
    'contabilidad' => 'financiera',
    'asesoria_laboral' => 'remuneraciones',
    'operacion_renta' => 'tributaria',
];

$profesionales = [];
foreach (configuracion()['profesionales'] ?? [] as $persona) {
    if (!is_array($persona)) {
        continue;
    }

    $nombre = trim((string) ($persona['nombre'] ?? ''));
    if ($nombre === '') {
        continue;
    }

    $foto = basename(str_replace('\\', '/', (string) ($persona['foto'] ?? '')));
    $profesionales[] = [
        'nombre' => $nombre,
        'cargo' => trim((string) ($persona['cargo'] ?? '')),
        'descripcion' => trim((string) ($persona['descripcion'] ?? '')),
        'foto' => preg_match('/^[A-Za-z0-9._-]+$/', $foto) === 1 ? $foto : '',
    ];
}

abrirPagina('Inicio', true);
?>
<section class="banner">
  <div class="banner-texto">
    <h1 class="sr-only"><?= e($nombreSitio) ?></h1>
    <img class="logo-portada" src="/img/logo-az.png" width="747" height="631" alt="">
    <p><?= e(textoHorario()) ?> Elige el trámite y un horario libre; no necesitas crear una cuenta.</p>
    <div class="acciones">
      <?php if ($usuario && $usuario['rol'] === 'anfitrion'): ?>
        <a class="boton" href="agenda.php">Ver la agenda</a>
      <?php else: ?>
        <a class="boton" href="horarios.php">Reservar una hora</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="servicios">
  <div class="seccion-inner">
    <h2>Nuestros servicios</h2>
    <div class="rejilla">
      <?php foreach (servicios() as $clave => $nombre): ?>
        <article class="tarjeta-servicio vidrio">
          <?= icono($iconosServicio[$clave] ?? 'horarios') ?>
          <h3><?= e($nombre) ?></h3>
          <a class="boton" href="horarios.php?servicio=<?= e($clave) ?>">Reservar</a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="acerca">
  <div class="seccion-inner texto-presentacion">
    <h2>Acerca de nosotros</h2>
    <p>Somos una empresa con más de 8 años de experiencia, en donde nuestro objetivo principal es brindarte tranquilidad, orden fiscal y laboral ante el Servicio de Impuestos Internos (SII) y la Dirección del Trabajo (DT).</p>
    <p>Entregamos un Servicio profesional personalizado, remoto, eficiente y con un trato cercano para pymes y emprendedores.</p>
    <p>Llevamos sus registros, preparamos sus estados financieros y le acompañamos en sus obligaciones contables, laborales y tributarias de forma mensual.</p>
  </div>
</section>

<section class="contacto">
  <div class="seccion-inner">
    <h2>Contacto</h2>
    <div class="rejilla">
      <article class="tarjeta-contacto vidrio">
        <?= icono('whatsapp') ?>
        <h3>WhatsApp</h3>
        <a href="https://wa.me/56974531422">+569 74531422</a>
      </article>
      <article class="tarjeta-contacto vidrio">
        <?= icono('direccion') ?>
        <h3>Dirección</h3>
        <p>La Serena</p>
      </article>
      <article class="tarjeta-contacto vidrio">
        <?= icono('correo') ?>
        <h3>Correo</h3>
        <a href="mailto:servicioscontablesaz@gmail.com">servicioscontablesaz@gmail.com</a>
      </article>
    </div>
  </div>
</section>

<?php $noticias = noticiasTributarias(); ?>
<?php if ($noticias !== []): ?>
<section class="actualidad" aria-labelledby="titulo-actualidad">
  <div class="seccion-inner">
    <h2 id="titulo-actualidad">Actualidad tributaria</h2>
    <div class="carrusel" data-carrusel tabindex="0" aria-roledescription="carrusel" aria-label="Noticias del Servicio de Impuestos Internos">
      <p class="carrusel-estado" data-carrusel-estado aria-live="polite"></p>
      <div class="carrusel-ventana">
        <div class="carrusel-pista">
          <?php foreach ($noticias as $noticia): ?>
            <article class="tarjeta-noticia">
              <div class="noticia-cuerpo vidrio">
                <p class="cargo"><?= e($noticia['categoria']) ?></p>
                <h3><?= e($noticia['titulo']) ?></h3>
                <?php if ($noticia['fecha'] !== ''): ?>
                  <p class="nota"><?= e($noticia['fecha']) ?></p>
                <?php endif; ?>
                <?php if ($noticia['extracto'] !== ''): ?>
                  <p class="noticia-extracto"><?= e($noticia['extracto']) ?></p>
                <?php endif; ?>
                <a class="boton" href="<?= e($noticia['enlace']) ?>" target="_blank" rel="noopener noreferrer">Leer en el SII</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="carrusel-controles">
        <button type="button" class="boton secundario" data-carrusel-anterior>Anterior</button>
        <div class="carrusel-puntos" data-carrusel-puntos></div>
        <button type="button" class="boton secundario" data-carrusel-siguiente>Siguiente</button>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($profesionales !== []): ?>
<section class="equipo">
  <div class="seccion-inner">
    <h2>Nuestros profesionales</h2>
    <div class="rejilla">
      <?php foreach ($profesionales as $persona): ?>
        <article class="tarjeta-profesional vidrio">
          <?php if ($persona['foto'] !== ''): ?>
            <img class="foto-profesional" src="img/equipo/<?= e($persona['foto']) ?>" alt="">
          <?php endif; ?>
          <h3><?= e($persona['nombre']) ?></h3>
          <?php if ($persona['cargo'] !== ''): ?>
            <p class="cargo"><?= e($persona['cargo']) ?></p>
          <?php endif; ?>
          <?php if ($persona['descripcion'] !== ''): ?>
            <p><?= e($persona['descripcion']) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php
cerrarPagina();
