<?php

declare(strict_types=1);

namespace System\Queue\Producer;

use System\Queue\Enum\QueueName;
use System\Queue\Factory\QueueProducerFactory;

readonly class QueueManager
{
    public function __construct(private QueueProducerFactory $factory)
    {
    }

    public function send(QueueName $queue, array $data): void
    {
        $this->factory->create($queue)->produce($data);
    }
}
