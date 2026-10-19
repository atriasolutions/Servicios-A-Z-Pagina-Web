<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

abrirPagina('Gestión de citas');
?>
<section class="articulo">
  <h1>Las citas se administran en Kora</h1>
  <p>La confirmación y la anulación de solicitudes ahora se realizan desde la agenda de Kora.</p>
  <p>El cliente recibirá automáticamente el aviso correspondiente cuando cambie el estado de la cita.</p>
</section>
<?php
cerrarPagina();
