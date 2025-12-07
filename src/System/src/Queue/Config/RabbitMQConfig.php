<?php

declare(strict_types=1);

namespace System\Queue\Config;

final readonly class RabbitMQConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public string $vhost,
    ) {
    }
}
