<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

exigirRol('anfitrion');

$agenda = koraConsultarBloques();
$locales = koraReservasLocales();
$externas = koraReservasExternas();
$fechas = [];
foreach ($agenda['fechas'] as $dia) {
    $fechas[$dia->format('Y-m-d')] = $dia;
}
foreach (array_merge($locales, $externas['reservas']) as $reserva) {
    if (isset($fechas[$reserva['fecha']])) {
        continue;
    }
    $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $reserva['fecha'], zonaHoraria());
    if ($dia instanceof DateTimeImmutable) {
        $fechas[$reserva['fecha']] = $dia;
    }
}
ksort($fechas);
$fechas = array_values($fechas);

$solicitada = koraFecha((string) ($_GET['fecha'] ?? ''), $fechas);
$fecha = $solicitada ?? ($fechas[0] ?? null);
$fechaIso = $fecha ? $fecha->format('Y-m-d') : '';
$bloques = $fecha && $agenda['error'] === null
    ? array_values(array_filter(
        koraBloquesDelDia($fecha, $agenda['bloques']),
        static fn (array $bloque): bool => $bloque['estado'] === 'libre'
    ))
    : [];
$reservasDia = array_values(array_filter(
    $locales,
    static fn (array $reserva): bool => $reserva['fecha'] === $fechaIso
));
$externasDia = array_values(array_filter(
    $externas['reservas'],
    static fn (array $reserva): bool => $reserva['fecha'] === $fechaIso
));

abrirPagina('Agenda');
?>
<section>
  <h1>Agenda de asesorías</h1>
  <p><?= e(textoHorario()) ?> Los cupos se administran en Kora. Aquí se ven los bloques publicados y las reservas hechas desde la página.</p>
  <?php if ($agenda['error'] !== null): ?>
    <p class="nota"><?= e($agenda['error']) ?></p>
  <?php endif; ?>
  <?php if ($fechas !== []): ?>
    <div class="dias">
      <?php foreach ($fechas as $dia): ?>
        <?php $iso = $dia->format('Y-m-d'); ?>
        <a class="<?= $iso === $fechaIso ? 'dia-link activo' : 'dia-link' ?>" href="agenda.php?fecha=<?= e($iso) ?>"<?= $iso === $fechaIso ? ' aria-current="date"' : '' ?>>
          <span><?= e(fechaCorta($dia)) ?></span>
          <strong><?= e($dia->format('j')) ?></strong>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($fecha): ?>
      <h2><?= e(fechaLarga($fecha)) ?></h2>
    <?php endif; ?>

    <h3>Reservas hechas en la página</h3>
    <?php if ($reservasDia === []): ?>
      <p class="nota">No hay reservas de la página para este día.</p>
    <?php else: ?>
      <ul class="franjas">
        <?php foreach ($reservasDia as $reserva): ?>
          <li class="franja reservado">
            <div>
              <strong><?= e($reserva['hora']) ?></strong>
              <span class="estado"><?= e($reserva['estado'] === 'pendiente' ? 'Pendiente de aprobación' : 'Reservada en Kora') ?></span>
              <span><?= e($reserva['nombre']) ?></span>
              <span><?= e(etiquetaServicio($reserva['servicio'])) ?></span>
              <span class="nota">Tipo: <?= e(etiquetaTipoPersona($reserva['tipo_persona'])) ?></span>
              <?php if ($reserva['telefono'] !== '' || $reserva['email'] !== ''): ?>
                <span class="nota">
                  <?= e($reserva['telefono']) ?>
                  <?php if ($reserva['telefono'] !== '' && $reserva['email'] !== ''): ?> · <?php endif; ?>
                  <?= e($reserva['email']) ?>
                </span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <h3>Reservas hechas en Kora</h3>
    <?php if ($externas['error'] !== null): ?>
      <p class="nota"><?= e($externas['error']) ?></p>
    <?php elseif ($externasDia === []): ?>
      <p class="nota">No hay otras reservas de Kora para este día.</p>
    <?php else: ?>
      <ul class="franjas">
        <?php foreach ($externasDia as $reserva): ?>
          <li class="franja reservado">
            <div>
              <strong><?= e($reserva['hora']) ?></strong>
              <span class="estado">Reservada en Kora</span>
              <span><?= e($reserva['nombre']) ?></span>
              <?php if ($reserva['telefono'] !== '' || $reserva['email'] !== ''): ?>
                <span class="nota">
                  <?= e($reserva['telefono']) ?>
                  <?php if ($reserva['telefono'] !== '' && $reserva['email'] !== ''): ?> · <?php endif; ?>
                  <?= e($reserva['email']) ?>
                </span>
              <?php endif; ?>
              <?php if ($reserva['notas'] !== ''): ?>
                <span class="nota"><?= e($reserva['notas']) ?></span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($agenda['error'] === null): ?>
      <h3>Bloques publicados</h3>
      <?php if ($bloques === []): ?>
        <p class="nota">No hay bloques publicados para este día.</p>
      <?php else: ?>
        <ul class="franjas">
          <?php foreach ($bloques as $bloque): ?>
            <li class="franja <?= e($bloque['estado'] === 'libre' ? 'libre' : ($bloque['estado'] === 'pasado' ? 'pasado' : 'reservado')) ?>">
              <div>
                <strong><?= e($bloque['etiqueta']) ?></strong>
                <?php if ($bloque['estado'] === 'libre'): ?>
                  <span class="estado">Libre</span>
                <?php elseif ($bloque['estado'] === 'pasado'): ?>
                  <span class="estado">Pasado</span>
                <?php elseif ($bloque['estado'] === 'cerrado'): ?>
                  <span class="estado">No disponible</span>
                <?php else: ?>
                  <span class="estado">Reservado</span>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
