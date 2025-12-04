<?php

declare(strict_types=1);

return [
    'rabbitmq' => [
        'host' => (string)getenv('RABBITMQ_HOST'),
        'port' => (int)getenv('RABBITMQ_PORT'),
        'username' => (string)getenv('RABBITMQ_USER'),
        'password' => (string)getenv('RABBITMQ_PASSWORD'),
        'vhost' => (string)getenv('RABBITMQ_VHOST'),
    ]
];
