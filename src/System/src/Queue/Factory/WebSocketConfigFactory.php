<?php

declare(strict_types=1);

namespace System\Queue\Factory;

use Psr\Container\ContainerInterface;
use System\Queue\Config\WebSocketConfig;

class WebSocketConfigFactory
{
    public function __invoke(ContainerInterface $container): WebSocketConfig
    {
        $config = $container->get('config')['websocket'];

        return new WebSocketConfig(
            $config['port'],
        );
    }
}
