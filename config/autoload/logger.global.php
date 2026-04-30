<?php

declare(strict_types=1);

return [
    'log' => [
        'channels' => [
            \Psr\Log\LoggerInterface::class => [
                'name' => 'logger',
                'class' => \Monolog\Handler\StreamHandler::class,
                'constructor' => [
                    'stream' => 'php://stdout',
                    'level' => \Monolog\Level::Debug,
                ],
                'formatter' => [
                    'class' => \Monolog\Formatter\JsonFormatter::class,
                ],
            ],
        ],
    ],
];
