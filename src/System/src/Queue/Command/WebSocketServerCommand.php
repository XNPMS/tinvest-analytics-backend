<?php

declare(strict_types=1);

namespace System\Queue\Command;

use Psr\Log\LoggerInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use System\Queue\Config\WebSocketConfig;
use Tinvest\WebSocket\SyncProgressWsApp;

class WebSocketServerCommand extends Command
{
    public const COMMAND_NAME = 'websocket:server';
    /**
     * Интервал рассылки прогресса в секундах
     */
    private const BROADCAST_INTERVAL = 1.0;
    /**
     * Интервал keepalive-ping чтобы nginx не рвал idle-соединение
     */
    private const PING_INTERVAL = 15.0;

    public function __construct(
        private readonly SyncProgressWsApp $wsApp,
        private readonly LoggerInterface $logger,
        private readonly WebSocketConfig $config,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Run WebSocket server for sync progress broadcasting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info(sprintf('Starting WebSocket server on port %d...', $this->config->port));

        $server = IoServer::factory(new HttpServer(new WsServer($this->wsApp)), $this->config->port);

        // Каждую секунду рассылаем обновления прогресса подписчикам
        $server->loop->addPeriodicTimer(self::BROADCAST_INTERVAL, function () {
            $this->wsApp->broadcastAll();
        });

        // Keepalive ping чтобы nginx не рвал idle-соединение
        $server->loop->addPeriodicTimer(self::PING_INTERVAL, function () {
            $this->wsApp->pingAll();
        });

        $this->logger->info('WebSocket server started. Waiting for connections...');
        $server->run();

        return Command::SUCCESS;
    }
}
