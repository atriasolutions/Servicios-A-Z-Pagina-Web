<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usuario = usuarioActual();
$nombreSitio = (string) configuracion()['sitio']['nombre'];
$iconosServicio = [
    'gestion_tributaria' => 'tributaria',
    'contabilidad_financiera' => 'financiera',
    'gestion_remuneraciones' => 'remuneraciones',
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
    <h1><?= e($nombreSitio) ?></h1>
    <p><?= e(textoHorario()) ?> Entra con tu cuenta, elige el trámite y un horario libre. Ese horario queda ocupado para cualquier trámite.</p>
    <div class="acciones">
      <?php if ($usuario && $usuario['rol'] === 'anfitrion'): ?>
        <a class="boton" href="agenda.php">Ver la agenda</a>
      <?php elseif ($usuario): ?>
        <a class="boton" href="mis-citas.php">Mis asesorías</a>
      <?php else: ?>
        <a class="boton" href="registro.php">Crear cuenta</a>
        <a class="boton secundario" href="login.php">Entrar</a>
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
