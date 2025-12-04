<?php

namespace System\Queue\Factory;

use Psr\Container\ContainerInterface;
use System\Queue\Command\QueueWorkerCommand;
use System\Queue\Enum\QueueName;
use Tinvest\Worker\SyncStateAccountTinvestWorker;

class QueueWorkerCommandFactory
{
    public function __invoke(ContainerInterface $container): QueueWorkerCommand
    {
        return new QueueWorkerCommand($container, [
            QueueName::SYNC_SELECT->value => SyncStateAccountTinvestWorker::class,
        ]);
    }
}
