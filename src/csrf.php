<?php

declare(strict_types=1);

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(tokenCsrf()) . '">';
}

function csrfValido(): bool
{
    $enviado = $_POST['csrf'] ?? '';
    return is_string($enviado) && hash_equals(tokenCsrf(), $enviado);
}
