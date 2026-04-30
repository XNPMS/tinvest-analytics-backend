<?php

declare(strict_types=1);

namespace System\Queue\Command;

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tinvest\WebSocket\SyncProgressWsApp;

class WebSocketServerCommand extends Command
{
    public const COMMAND_NAME = 'websocket:server';

    /** Интервал рассылки прогресса в секундах */
    private const BROADCAST_INTERVAL = 1.0;

    public function __construct(
        private readonly SyncProgressWsApp $wsApp,
        private readonly int $port,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Run WebSocket server for sync progress broadcasting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('<info>Starting WebSocket server on port %d...</info>', $this->port));

        $loop   = Loop::get();
        $server = IoServer::factory(
            new HttpServer(new WsServer($this->wsApp)),
            $this->port,
            '0.0.0.0',
            $loop,
        );

        // Каждую секунду рассылаем обновления прогресса подписчикам
        $loop->addPeriodicTimer(self::BROADCAST_INTERVAL, function () {
            $this->wsApp->broadcastAll();
        });

        $output->writeln('<info>WebSocket server started. Waiting for connections...</info>');
        $server->run();

        return Command::SUCCESS;
    }
}