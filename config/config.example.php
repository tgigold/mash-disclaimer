<?php

declare(strict_types=1);

/*
 * Example of data/config.php. The real file lives only on the server, outside
 * the web root, and is never committed (G-14). The setup at /setup writes it;
 * every key is optional because defaults live in src/Config.php (G-13).
 */

return [
    // Canonical URL without trailing slash; requests on any other host or via
    // plain HTTP are redirected here (S-1).
    'base_url' => 'https://disclaimer.lab09.de',

    // Only needed behind a reverse proxy: its IP addresses (S-6).
    'trusted_proxies' => [],

    // Own database user for this project with rights on this database only (G-16, S-46).
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'example_db',
        'user' => 'example_user',
        'password' => '',
    ],
];
