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
    ],
    'agenda' => [
        'hora_inicio' => 8,
        'hora_fin' => 18,
        'duracion_minutos' => 60,
        'horizonte_dias' => 30,
        'timezone' => 'America/Argentina/Buenos_Aires',
    ],
    // Lista de profesionales del inicio. Si está vacía, esa sección no se muestra.
    // Cada entrada usa nombre, cargo, descripcion y foto.
    // foto es el archivo dentro de public/img/equipo/, por ejemplo archivo.jpg.
    'profesionales' => [],
];
