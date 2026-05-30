<?php

declare(strict_types=1);

namespace System\Queue\Factory;

use Psr\Container\ContainerInterface;
use System\Queue\Config\RabbitMQConfig;

class RabbitMQConfigFactory
{
    public function __invoke(ContainerInterface $container): RabbitMQConfig
    {
        $config = $container->get('config')['rabbitmq'];

        return new RabbitMQConfig(
            $config['host'],
            $config['port'],
            $config['username'],
            $config['password'],
            $config['vhost'],
        );
    }
}
