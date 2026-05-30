<?php

declare(strict_types=1);

namespace System\Queue\Factory;

use Psr\Container\ContainerInterface;
use System\Queue\Command\QueueWorkerCommand;

class QueueWorkerCommandFactory
{
    public function __invoke(ContainerInterface $container): QueueWorkerCommand
    {
        return new QueueWorkerCommand($container);
    }
}
