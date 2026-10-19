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

$esAnfitrion = $usuario && $usuario['rol'] === 'anfitrion';

$contactoPendiente = $_SESSION['contacto'] ?? null;
unset($_SESSION['contacto']);
$contacto = [
    'nombre' => '',
    'apellido' => '',
    'email' => '',
    'telefono' => '',
    'tipo_persona' => '',
];
$erroresContacto = [];
if (is_array($contactoPendiente)) {
    $guardados = is_array($contactoPendiente['datos'] ?? null) ? $contactoPendiente['datos'] : [];
    $contacto['nombre'] = trim((string) ($guardados['nombre'] ?? ''));
    $contacto['apellido'] = trim((string) ($guardados['apellido'] ?? ''));
    $contacto['email'] = trim((string) ($guardados['email'] ?? ''));
    $contacto['telefono'] = trim((string) ($guardados['telefono'] ?? ''));
    $contacto['tipo_persona'] = (string) ($guardados['tipo_persona'] ?? '');
    foreach ((array) ($contactoPendiente['errores'] ?? []) as $error) {
        if (is_string($error) && $error !== '') {
            $erroresContacto[] = $error;
        }
    }
}

$informacionServicios = [];
foreach (servicios() as $clave => $nombre) {
    $info = function_exists('informacionServicio') ? informacionServicio($clave) : [];
    $beneficios = [];
    foreach ((array) ($info['beneficios'] ?? []) as $beneficio) {
        $beneficio = trim((string) $beneficio);
        if ($beneficio !== '') {
            $beneficios[] = $beneficio;
        }
    }
    $informacionServicios[$clave] = [
        'descripcion' => trim((string) ($info['descripcion'] ?? '')),
        'beneficios' => $beneficios,
    ];
}

$slides = [
    [
        'clase' => 'hero-slide--asesoria',
        'etiqueta' => 'Más de 8 años de experiencia',
        'titulo' => 'Tranquilidad y orden fiscal ante el SII y la Dirección del Trabajo',
        'texto' => 'Acompañamos a pymes y emprendedores en sus obligaciones contables, laborales y tributarias.',
        'enlace' => 'horarios.php',
        'accion' => 'Reservar una hora',
        'externo' => false,
    ],
    [
        'clase' => 'hero-slide--orden',
        'etiqueta' => 'Atención remota y personalizada',
        'titulo' => 'Un servicio profesional, eficiente y con trato cercano',
        'texto' => 'Llevamos sus registros y preparamos sus estados financieros sin que tenga que trasladarse.',
        'enlace' => 'horarios.php',
        'accion' => 'Elegir un trámite',
        'externo' => false,
    ],
    [
        'clase' => 'hero-slide--acompanamiento',
        'etiqueta' => 'Acompañamiento mensual',
        'titulo' => 'Contabilidad, asesoría laboral y tributaria en un solo lugar',
        'texto' => 'Le acompañamos de forma mensual, con un trato cercano para pymes y emprendedores.',
        'enlace' => enlaceWhatsApp(),
        'accion' => 'Escribir por WhatsApp',
        'externo' => true,
    ],
];

abrirPagina('Inicio', true);
?>
<div class="zona-escritorio">
<section class="banner" aria-labelledby="titulo-portada">
  <div class="banner-texto">
    <h1 id="titulo-portada" class="hero-marca"><?= e($nombreSitio) ?></h1>
    <div class="hero-carrusel" data-hero-carrusel aria-roledescription="carrusel" aria-label="Presentación de <?= e($nombreSitio) ?>">
      <p class="sr-only" data-hero-estado aria-live="polite"></p>
      <div class="hero-slides">
        <?php foreach ($slides as $indice => $slide): ?>
          <div class="hero-slide <?= e($slide['clase']) ?>" data-hero-slide role="group" aria-roledescription="diapositiva" aria-label="<?= e(($indice + 1) . ' de ' . count($slides)) ?>">
            <p class="hero-etiqueta"><?= e($slide['etiqueta']) ?></p>
            <h2 class="hero-titulo"><?= e($slide['titulo']) ?></h2>
            <p class="hero-texto"><?= e($slide['texto']) ?></p>
            <div class="acciones">
              <?php if ($slide['externo']): ?>
                <a class="boton" href="<?= e($slide['enlace']) ?>" target="_blank" rel="noopener noreferrer"><?= e($slide['accion']) ?></a>
              <?php else: ?>
                <a class="boton" href="<?= e($slide['enlace']) ?>"><?= e($slide['accion']) ?></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="hero-controles">
        <button type="button" class="boton secundario" data-hero-anterior aria-label="Diapositiva anterior">Anterior</button>
        <div class="hero-puntos" data-hero-puntos></div>
        <button type="button" class="boton secundario" data-hero-siguiente aria-label="Diapositiva siguiente">Siguiente</button>
      </div>
    </div>
    <p class="hero-horario"><?= e(textoHorario()) ?> Elige el trámite y un horario libre; no necesitas crear una cuenta.</p>
    <?php if ($esAnfitrion): ?>
      <div class="acciones">
        <a class="boton" href="agenda.php">Ver la agenda</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="franja-cualidades" aria-label="Nuestras cualidades">
  <ul class="seccion-inner">
    <li><strong><span aria-hidden="true">+<span data-contador="8">8</span></span><span class="sr-only">+8</span> años</strong><span>de experiencia</span></li>
    <li>
      <svg class="cualidad-icono cualidad-remoto" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path class="arco arco-1" d="M9.2 15.2a4 4 0 0 1 5.6 0"/>
        <path class="arco arco-2" d="M6.4 12.2a8 8 0 0 1 11.2 0"/>
        <path class="arco arco-3" d="M3.5 9.2a12 12 0 0 1 17 0"/>
        <circle cx="12" cy="18.5" r="1.1" fill="currentColor" stroke="none"/>
      </svg>
      <strong>Remoto</strong><span>atención sin traslados</span>
    </li>
    <li>
      <svg class="cualidad-icono cualidad-personalizado" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <circle cx="12" cy="5.5" r="2.5"/>
        <path d="M12 8.5v7M12 15.5l-3 5M12 15.5l3 5M12 11l-4 2.5"/>
        <path class="brazo" d="M12 11l4-4"/>
      </svg>
      <strong>Personalizado</strong><span>trato cercano</span>
    </li>
    <li>
      <svg class="cualidad-icono cualidad-mensual" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <rect x="4" y="5" width="16" height="15" rx="2"/>
        <path d="M8 3v4M16 3v4M4 10h16"/>
        <rect class="hoja" x="7.5" y="12.5" width="9" height="5" rx="0.8"/>
      </svg>
      <strong>Mensual</strong><span>acompañamiento continuo</span>
    </li>
  </ul>
</section>

<section id="servicios" class="servicios">
  <div class="seccion-inner">
    <h2>Nuestros servicios</h2>
    <div class="rejilla">
      <?php foreach (servicios() as $clave => $nombre): ?>
        <?php $info = $informacionServicios[$clave]; ?>
        <article class="tarjeta-servicio vidrio">
          <?= icono($iconosServicio[$clave] ?? 'horarios') ?>
          <h3><?= e($nombre) ?></h3>
          <?php if ($info['descripcion'] !== ''): ?>
            <p class="servicio-descripcion"><?= e($info['descripcion']) ?></p>
          <?php endif; ?>
          <?php if ($info['beneficios'] !== []): ?>
            <ul class="servicio-beneficios">
              <?php foreach ($info['beneficios'] as $beneficio): ?>
                <li><?= e($beneficio) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <a class="boton" href="horarios.php?servicio=<?= e($clave) ?>">Reservar</a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
</div>

<section class="proceso" aria-labelledby="titulo-proceso">
  <div class="seccion-inner">
    <h2 id="titulo-proceso">Cómo reservar una asesoría</h2>
    <ol class="proceso-pasos">
      <li class="proceso-paso">
        <h3>Elige el trámite</h3>
        <p>Selecciona el servicio sobre el que necesitas asesoría.</p>
      </li>
      <li class="proceso-paso">
        <h3>Escoge día y hora</h3>
        <p>Revisa los horarios libres publicados en la agenda.</p>
      </li>
      <li class="proceso-paso">
        <h3>Deja tus datos</h3>
        <p>Indica nombre, correo y teléfono. No necesitas crear una cuenta.</p>
      </li>
      <li class="proceso-paso">
        <h3>Recibe la confirmación</h3>
        <p>Revisamos tu solicitud y te avisamos por correo cuando la cita queda confirmada.</p>
      </li>
    </ol>
    <div class="acciones">
      <a class="boton" href="horarios.php">Reservar una hora</a>
    </div>
  </div>
</section>

<div class="zona-faro">
<div class="fondo-equipo" aria-hidden="true"></div>
<section id="nosotros" class="acerca">
  <div class="seccion-inner acerca-grid">
    <div class="acerca-contenido texto-presentacion">
      <h2>Acerca de nosotros</h2>
      <p>Somos una empresa con más de 8 años de experiencia, en donde nuestro objetivo principal es brindarte tranquilidad, orden fiscal y laboral ante el Servicio de Impuestos Internos (SII) y la Dirección del Trabajo (DT).</p>
      <p>Entregamos un Servicio profesional personalizado, remoto, eficiente y con un trato cercano para pymes y emprendedores.</p>
      <p>Llevamos sus registros, preparamos sus estados financieros y le acompañamos en sus obligaciones contables, laborales y tributarias de forma mensual.</p>
    </div>
    <ul class="acerca-destacados">
      <li class="vidrio"><strong>Más de 8 años</strong><span>de experiencia acompañando a pymes y emprendedores.</span></li>
      <li class="vidrio"><strong>SII y DT</strong><span>orden fiscal y laboral ante ambas instituciones.</span></li>
      <li class="vidrio"><strong>Remoto</strong><span>servicio personalizado, eficiente y cercano.</span></li>
    </ul>
  </div>
</section>

<section class="beneficios por-que-elegirnos" aria-labelledby="titulo-beneficios">
  <div class="seccion-inner">
    <h2 id="titulo-beneficios">Por qué elegirnos</h2>
    <div class="rejilla">
      <article class="tarjeta-beneficio vidrio">
        <h3>Tranquilidad</h3>
        <p>Mantenemos en orden sus obligaciones ante el SII y la Dirección del Trabajo.</p>
      </article>
      <article class="tarjeta-beneficio vidrio">
        <h3>Acompañamiento mensual</h3>
        <p>Le acompañamos cada mes en lo contable, laboral y tributario.</p>
      </article>
      <article class="tarjeta-beneficio vidrio">
        <h3>Registros al día</h3>
        <p>Llevamos sus registros y preparamos sus estados financieros.</p>
      </article>
      <article class="tarjeta-beneficio vidrio">
        <h3>Atención remota</h3>
        <p>Un servicio profesional y personalizado, con trato cercano y sin traslados.</p>
      </article>
    </div>
  </div>
</section>
</div>

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

<section id="contacto" class="contacto" aria-labelledby="titulo-contacto">
  <div class="seccion-inner contacto-bloque">
    <div class="contacto-lado">
    <div class="contacto-presentacion">
      <p class="contacto-sobre">Estamos para ayudarle</p>
      <h2 id="titulo-contacto">Hablemos de su negocio</h2>
      <p>Déjenos sus datos y lo contactaremos para conocer su situación y ayudarle a encontrar la mejor solución.</p>
      <p class="contacto-plazo">
        <svg class="icono" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <circle cx="10" cy="10" r="7.2"/>
          <path d="M10 6.2V10l2.6 1.6"/>
        </svg>
        Respondemos su solicitud a la brevedad.
      </p>
    </div>

    <aside class="contacto-whatsapp">
      <div class="contacto-whatsapp-marca"><?= marcaWhatsApp() ?></div>
      <p class="contacto-sobre">Contacto directo</p>
      <h3>¿Prefiere WhatsApp?</h3>
      <p>Escríbanos directamente y cuéntenos qué necesita su empresa. Le responderemos a la brevedad.</p>
      <a class="boton contacto-whatsapp-boton" href="https://wa.me/56974531422" target="_blank" rel="noopener noreferrer">
        <?= marcaWhatsApp() ?>
        Escribir por WhatsApp
      </a>
      <p class="contacto-rapido">Atención rápida y personalizada</p>
    </aside>
    </div>

    <div class="contacto-formulario">
      <h3>Solicite una asesoría</h3>
      <p>Complete el formulario y nos pondremos en contacto con usted.</p>
      <?php erroresDe($erroresContacto); ?>
      <form class="formulario contacto-form" method="post" action="contacto.php">
        <?= campoCsrf() ?>
        <div class="trampa-contacto" aria-hidden="true">
          <label>Sitio web<input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
        </div>
        <div class="datos-reserva">
          <label>
            Nombre
            <input type="text" name="nombre" required maxlength="120" autocomplete="given-name" value="<?= e($contacto['nombre']) ?>">
          </label>
          <label>
            Apellido <span class="opcional">(opcional)</span>
            <input type="text" name="apellido" maxlength="120" autocomplete="family-name" value="<?= e($contacto['apellido']) ?>">
          </label>
          <label>
            Correo electrónico
            <input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= e($contacto['email']) ?>">
          </label>
          <label>
            Teléfono
            <input type="tel" name="telefono" required maxlength="40" autocomplete="tel" value="<?= e($contacto['telefono']) ?>">
          </label>
        </div>
        <fieldset class="tipo-persona">
          <legend>Tipo de Persona / Régimen</legend>
          <div class="opciones-tipo-persona">
            <?php foreach (tiposPersona() as $clave => $etiqueta): ?>
              <label>
                <input type="radio" name="tipo_persona" value="<?= e($clave) ?>" required<?= $contacto['tipo_persona'] === $clave ? ' checked' : '' ?>>
                <span><?= e($etiqueta) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <button class="boton" type="submit">Enviar solicitud</button>
      </form>
    </div>
  </div>
</section>
<?php
cerrarPagina();
