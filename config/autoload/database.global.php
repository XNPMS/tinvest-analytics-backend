<?php

declare(strict_types=1);

return [
    'database' => [
        'driver' => 'mysql',
        'host' => getenv('DATABASE_HOST'),
        'port' => getenv('DATABASE_PORT'),
        'database' => getenv('DATABASE_NAME'),
        'username' => getenv('DATABASE_USERNAME'),
        'password' => getenv('DATABASE_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],
];
