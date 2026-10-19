<?php

declare(strict_types=1);

// En cPanel, copia este archivo a config.php y completa los datos
// de «Bases de datos MySQL». config.php no se sube a git.
// El host casi siempre es localhost y el puerto 3306.
// El nombre y el usuario suelen llevar el prefijo de la cuenta, por ejemplo cuenta_agenda.

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'cuenta_agenda',
        'user' => 'cuenta_agenda',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'sitio' => [
        'nombre' => 'Servicios Contables A&Z',
        // Dirección pública de los enlaces en los correos. Las pruebas en 127.0.0.1 usan la local.
        'url' => 'https://www.scontablesayz.cl',
        // Opcional. El sitio solo muestra las que tengan una dirección.
        'redes' => [
            'instagram' => '',
            'facebook' => '',
            'linkedin' => '',
        ],
    ],
    'agenda' => [
        'hora_inicio' => 10,
        'hora_fin' => 20,
        'duracion_minutos' => 60,
        'meses_anticipacion' => 1,
        'dias' => [1, 2, 3, 4, 5],
        'timezone' => 'America/Santiago',
    ],
    // La llave real va solo en config.php, que no se sube a git.
    'kora' => [
        'url' => 'https://atriasolutions.koracrm.cl/api/public/v1',
        'llave' => '',
        'calendario' => '13be6a3e-4c0f-47e9-a588-869967d76950',
    ],
    'correo' => [
        'administrador' => 'mmb21894@gmail.com',
        // Contraseña de aplicación de Gmail. No es la contraseña normal de la cuenta.
        'smtp_clave' => '',
    ],
    // Lista de profesionales del inicio. Si está vacía, esa sección no se muestra.
    // Cada entrada usa nombre, cargo, descripcion y foto.
    // foto es el archivo dentro de public/img/equipo/, por ejemplo archivo.jpg.
    'profesionales' => [],
    // Canal «Actualidad tributaria y normativa» del RSS oficial del SII.
    'noticias' => [
        'rss' => 'https://www.sii.cl/pagina/actualizada/noticias/rss/siiall_rss.xml',
        'cache_horas' => 4,
        'limite' => 9,
    ],
];
