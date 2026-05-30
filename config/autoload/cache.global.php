<?php

declare(strict_types=1);

return [
    'cache' => [
        'name' => Memcached::class,
        'options' => [
            'servers' => [
                [
                    'host' => (string)getenv('MEMCACHED_HOST'),
                    'port' => (int)getenv('MEMCACHED_PORT'),
                    'weight' => (int)getenv('MEMCACHED_WEIGHT'),
                ],
            ],
            'liboptions' => [
                // В неблокирующем режиме задает таймаут соединения для сокета в миллисекундах
                'connect_timeout' => 2000,
                // Задержка в секундах перед попыткой повторного соединения после ошибки
                'retry_timeout' => 10,
                // Разрешает или запрещает сжатие данных.
                'compression' => true,
                // Включает использование бинарного протокола.
                'binary_protocol' => true,
                // Включает или отключает асинхронный ввод/вывод.
                'no_block' => true,
            ],
        ],
    ],
];
