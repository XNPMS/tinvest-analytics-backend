<?php

declare(strict_types=1);

namespace System\Factory;

use Psr\Container\ContainerInterface;
use System\Queue\Command\WebSocketServerCommand;
use Tinvest\WebSocket\SyncProgressWsApp;

class WebSocketServerCommandFactory
{
    public function __invoke(ContainerInterface $container): WebSocketServerCommand
    {
        $config = $container->get('config');
        $port   = (int)($config['websocket']['port'] ?? 8081);

        return new WebSocketServerCommand(
            $container->get(SyncProgressWsApp::class),
            $port,
        );
    }
}