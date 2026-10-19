<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

exigirRol('anfitrion');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $volver = $id > 0 ? 'chat.php?id=' . $id : 'chat.php';
    if (!csrfValido()) {
        aviso('error', 'La solicitud no es válida. Vuelve a intentar.');
        redirigir($volver);
    }

    $error = publicarRespuestaChat($id, (string) ($_POST['texto'] ?? ''));
    aviso($error === null ? 'ok' : 'error', $error ?? 'Respuesta enviada.');
    redirigir('chat.php?id=' . $id);
}

$conversacion = $id > 0 ? conversacionPorId($id) : null;
if ($id > 0 && !$conversacion) {
    aviso('error', 'Esa conversación no existe.');
    redirigir('chat.php');
}

if ($conversacion) {
    marcarLeidosChat($conversacion['id'], 'visita');
}

$lista = listarConversaciones();

abrirPagina('Chat');
?>
<section class="chat-admin">
  <?php if ($conversacion): ?>
    <p><a href="chat.php">Todas las conversaciones</a></p>
    <h1><?= e($conversacion['nombre']) ?></h1>
    <p class="chat-admin-tema"><?= e($conversacion['tema']) ?></p>
    <div data-chat-admin data-id="<?= e((string) $conversacion['id']) ?>" data-csrf="<?= e(tokenCsrf()) ?>">
      <ol class="hilo-chat" data-hilo>
        <?php foreach ($conversacion['mensajes'] as $mensaje): ?>
          <li class="mensaje-chat <?= e((string) $mensaje['autor']) ?>">
            <p class="mensaje-chat-autor"><?= $mensaje['autor'] === 'visita' ? 'Visita' : 'Tú' ?></p>
            <p class="mensaje-chat-texto"><?= e((string) $mensaje['texto']) ?></p>
            <p class="mensaje-chat-hora"><?= e((string) $mensaje['hora']) ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
    <form class="chat-respuesta" method="post" action="chat.php">
      <?= campoCsrf() ?>
      <input type="hidden" name="id" value="<?= e((string) $conversacion['id']) ?>">
      <label for="respuesta-chat">Respuesta</label>
      <textarea id="respuesta-chat" name="texto" maxlength="1000" required></textarea>
      <button class="boton" type="submit">Responder</button>
    </form>
  <?php else: ?>
    <h1>Conversaciones</h1>
    <?php if ($lista === []): ?>
      <p>Todavía no hay conversaciones.</p>
    <?php else: ?>
      <ul class="lista-chat">
        <?php foreach ($lista as $item): ?>
          <li>
            <a href="chat.php?id=<?= e((string) $item['id']) ?>">
              <span class="lista-chat-nombre"><?= e($item['nombre']) ?></span>
              <span class="lista-chat-tema"><?= e($item['tema']) ?></span>
              <span class="lista-chat-ultimo"><?= e($item['ultimo']) ?></span>
              <span class="lista-chat-hora"><?= e($item['hora']) ?></span>
              <?php if ($item['sin_leer'] > 0): ?>
                <span class="lista-chat-pendiente"><?= e((string) $item['sin_leer']) ?> sin leer</span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php
cerrarPagina();
