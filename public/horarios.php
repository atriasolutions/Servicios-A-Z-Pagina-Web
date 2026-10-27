<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (isset($_GET['elegir'])) {
    unset($_SESSION['servicio']);
}

$pedido = servicioValido((string) ($_GET['servicio'] ?? ''));
if ($pedido !== null) {
    $_SESSION['servicio'] = $pedido;
}

$servicio = servicioValido((string) ($_POST['servicio'] ?? $_SESSION['servicio'] ?? ''));
$agenda = $servicio === null ? ['error' => null, 'bloques' => [], 'fechas' => []] : koraConsultarBloques();
if ($agenda['bloques'] !== []) {
    $agenda['bloques'] = koraBloquesPublicos($agenda['bloques']);
}
$solicitada = koraFecha((string) ($_GET['fecha'] ?? $_POST['fecha'] ?? ''), $agenda['fechas']);
$fecha = $solicitada ?? ($agenda['fechas'][0] ?? null);
$fechaIso = $fecha ? $fecha->format('Y-m-d') : '';
$errores = [];
$datos = [
    'nombre' => trim((string) ($_POST['nombre'] ?? '')),
    'apellido' => trim((string) ($_POST['apellido'] ?? '')),
    'email' => normalizarEmail((string) ($_POST['email'] ?? '')),
    'telefono' => trim((string) ($_POST['telefono'] ?? '')),
    'tipo_persona' => tipoPersonaValido((string) ($_POST['tipo_persona'] ?? '')),
    'razon_social' => trim((string) ($_POST['razon_social'] ?? '')),
    'rut_empresa' => trim((string) ($_POST['rut_empresa'] ?? '')),
];

$volver = 'horarios.php';
if ($fechaIso !== '') {
    $volver .= '?fecha=' . urlencode($fechaIso);
}
if ($servicio !== null) {
    $volver .= ($fechaIso === '' ? '?' : '&') . 'servicio=' . urlencode($servicio);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        $errores[] = 'La solicitud no es válida. Vuelve a intentar.';
    } elseif ($servicio === null) {
        $errores[] = 'Elige un tipo de trámite.';
    } else {
        $validacion = validarContacto($datos);
        $datos = $validacion['datos'];
        $errores = $validacion['errores'];

        if ($errores === []) {
            $resultado = koraSolicitar(
                $datos,
                (string) ($_POST['bloque'] ?? ''),
                $servicio
            );
            if ($resultado['error'] !== null) {
                $errores[] = $resultado['error'];
            } else {
                $cita = $resultado['cita'];
                $correoCliente = notificarSolicitudCliente($cita);
                $dia = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $cita['fecha'], zonaHoraria());
                $mensaje = 'Recibimos tu solicitud de ' . etiquetaServicio($servicio) . ' para el '
                    . fechaLarga($dia ?: $fecha) . ', ' . $cita['etiqueta']
                    . '. Está pendiente de confirmación.';
                if (!$correoCliente) {
                    $mensaje .= ' No pudimos enviarte el aviso por correo.';
                }
                aviso($correoCliente ? 'ok' : 'error', $mensaje);
                redirigir('horarios.php?fecha=' . urlencode((string) $cita['fecha']) . '&servicio=' . urlencode($servicio));
            }
        }
    }
}

if ($agenda['error'] !== null) {
    $errores[] = $agenda['error'];
}
erroresDe($errores);
abrirPagina('Horarios');
?>
<section>
  <?php if ($servicio === null): ?>
    <h1>Elige el trámite</h1>
    <p>El horario solicitado queda en espera hasta que confirmemos la asesoría.</p>
    <div class="tramites">
      <?php foreach (servicios() as $clave => $nombre): ?>
        <a class="tramite" href="horarios.php?servicio=<?= e($clave) ?>"><?= e($nombre) ?></a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <?php
      $bloques = $fecha ? koraBloquesDelDia($fecha, $agenda['bloques']) : [];
      $libres = array_values(array_filter(
          $bloques,
          static fn (array $bloque): bool => $bloque['estado'] === 'libre'
      ));
    ?>
    <h1><?= e(etiquetaServicio($servicio)) ?></h1>
    <?php
      $infoServicio = informacionServicio($servicio);
      $iconosServicio = [
          'constitucion_empresas' => 'cuenta',
          'contabilidad' => 'financiera',
          'asesoria_laboral' => 'remuneraciones',
          'operacion_renta' => 'tributaria',
      ];
    ?>
    <div class="reserva-intro">
      <div class="reserva-detalle">
        <?= icono($iconosServicio[$servicio] ?? 'horarios') ?>
        <p><?= e($infoServicio['descripcion']) ?></p>
      </div>
      <?php if ($infoServicio['beneficios'] !== []): ?>
        <ul class="reserva-beneficios">
          <?php foreach ($infoServicio['beneficios'] as $beneficio): ?>
            <li><?= e($beneficio) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <p><?= e(textoHorario()) ?> Enviaremos tu solicitud para revisión y te avisaremos por correo cuando sea confirmada.</p>
    <div class="reserva-layout">
    <div class="tramites">
      <?php foreach (servicios() as $clave => $nombre): ?>
        <a class="tramite<?= $clave === $servicio ? ' activo' : '' ?>" href="horarios.php?fecha=<?= e($fechaIso) ?>&amp;servicio=<?= e($clave) ?>"><?= e($nombre) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="calendario-paginas">
    <button type="button" class="boton secundario mes-flecha" data-mes-anterior aria-label="Mes anterior">Mes anterior</button>
    <div class="calendarios" tabindex="0" aria-label="Calendario por mes">
      <?php
        $nombresMes = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $cuposPorDia = [];
        $meses = [];
        foreach ($agenda['fechas'] as $dia) {
            $iso = $dia->format('Y-m-d');
            $cantidad = 0;
            foreach (koraBloquesDelDia($dia, $agenda['bloques']) as $bloque) {
                if ($bloque['estado'] === 'libre') {
                    $cantidad++;
                }
            }
            $cuposPorDia[$iso] = $cantidad;
            $claveMes = $dia->format('Y-m');
            if (!isset($meses[$claveMes])) {
                $meses[$claveMes] = $dia->modify('first day of this month');
            }
        }
      ?>
      <?php foreach ($meses as $inicioMes): ?>
        <?php
          $diasDelMes = (int) $inicioMes->format('t');
          $huecos = ((int) $inicioMes->format('w') + 6) % 7;
        ?>
        <section class="mes-calendario" aria-label="<?= e($nombresMes[(int) $inicioMes->format('n')] . ' ' . $inicioMes->format('Y')) ?>">
          <h2 class="mes-titulo"><?= e($nombresMes[(int) $inicioMes->format('n')] . ' ' . $inicioMes->format('Y')) ?></h2>
          <div class="mes-semana" aria-hidden="true">
            <span>lun</span><span>mar</span><span>mié</span><span>jue</span><span>vie</span><span>sáb</span><span>dom</span>
          </div>
          <div class="mes-dias">
            <?php for ($hueco = 0; $hueco < $huecos; $hueco++): ?>
              <span class="dia-hueco"></span>
            <?php endfor; ?>
            <?php for ($numero = 1; $numero <= $diasDelMes; $numero++): ?>
              <?php
                $diaMes = $inicioMes->setDate((int) $inicioMes->format('Y'), (int) $inicioMes->format('n'), $numero);
                $iso = $diaMes->format('Y-m-d');
              ?>
              <?php if (!array_key_exists($iso, $cuposPorDia)): ?>
                <span class="dia-apagado"><?= e((string) $numero) ?></span>
              <?php else: ?>
                <?php
                  $cantidad = $cuposPorDia[$iso];
                  $cupo = $cantidad === 0 ? 'sin cupo' : $cantidad . ' libres';
                ?>
                <a class="dia-link<?= $iso === $fechaIso ? ' activo' : '' ?>" href="horarios.php?fecha=<?= e($iso) ?>&amp;servicio=<?= e($servicio) ?>"<?= $iso === $fechaIso ? ' aria-current="date"' : '' ?>>
                  <strong><?= e((string) $numero) ?></strong>
                  <small><?= e($cupo) ?></small>
                </a>
              <?php endif; ?>
            <?php endfor; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
    <button type="button" class="boton secundario mes-flecha" data-mes-siguiente aria-label="Mes siguiente">Mes siguiente</button>
    </div>

    <?php if ($fecha): ?>
    <h2><?= e(fechaLarga($fecha)) ?></h2>
    <?php endif; ?>
    <?php if ($agenda['error'] === null && ($fecha === null || $libres === [])): ?>
      <p class="nota">No hay horarios libres este día.</p>
    <?php else: ?>
      <form class="formulario reserva-publica" method="post" action="horarios.php" data-reserva data-dia="<?= e(fechaLarga($fecha)) ?>" data-servicio="<?= e(etiquetaServicio($servicio)) ?>">
        <?= campoCsrf() ?>
        <input type="hidden" name="fecha" value="<?= e($fechaIso) ?>">
        <input type="hidden" name="servicio" value="<?= e($servicio) ?>">
        <?php $esEmpresa = ($datos['tipo_persona'] ?? '') === 'empresa'; ?>
        <fieldset class="tipo-persona">
          <legend>Tipo de Persona / Régimen</legend>
          <div class="opciones-tipo-persona">
            <?php foreach (tiposPersona() as $clave => $etiqueta): ?>
              <label>
                <input type="radio" name="tipo_persona" value="<?= e($clave) ?>" required<?= $datos['tipo_persona'] === $clave ? ' checked' : '' ?>>
                <span><?= e($etiqueta) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="datos-reserva">
          <label>
            <span data-etiqueta-nombre<?= $esEmpresa ? ' hidden' : '' ?>>Nombre</span>
            <span data-etiqueta-contacto<?= $esEmpresa ? '' : ' hidden' ?>>Nombre de contacto</span>
            <input type="text" name="nombre" required maxlength="120" autocomplete="given-name" value="<?= e($datos['nombre']) ?>">
          </label>
          <label>
            Apellido <span class="opcional">(opcional)</span>
            <input type="text" name="apellido" maxlength="120" autocomplete="family-name" value="<?= e($datos['apellido']) ?>">
          </label>
          <label data-campo-empresa<?= $esEmpresa ? '' : ' hidden' ?>>
            Razón social
            <input type="text" name="razon_social" maxlength="200" autocomplete="organization" value="<?= e($datos['razon_social']) ?>"<?= $esEmpresa ? '' : ' disabled' ?>>
          </label>
          <label data-campo-empresa<?= $esEmpresa ? '' : ' hidden' ?>>
            RUT empresa
            <input type="text" name="rut_empresa" maxlength="12" autocomplete="off" value="<?= e($datos['rut_empresa']) ?>"<?= $esEmpresa ? '' : ' disabled' ?>>
          </label>
          <label>
            Correo electrónico
            <input type="email" name="email" required maxlength="190" autocomplete="email" inputmode="email" placeholder="nombre@correo.cl" value="<?= e($datos['email']) ?>">
          </label>
          <label>
            Celular
            <input type="tel" name="telefono" required maxlength="40" autocomplete="tel" inputmode="tel" placeholder="+56 9 1234 5678" value="<?= e($datos['telefono']) ?>">
          </label>
        </div>
        <fieldset>
          <legend>Elige una hora</legend>
          <div class="grilla">
            <?php foreach ($libres as $bloque): ?>
              <label class="bloque">
                <input type="radio" name="bloque" value="<?= e($bloque['id']) ?>" required>
                <span><?= e($bloque['etiqueta']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="acciones acciones-reserva">
          <a class="boton secundario" href="horarios.php?elegir=1">Volver</a>
          <button class="boton" type="submit">Solicitar horario</button>
        </div>
      </form>
    <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
