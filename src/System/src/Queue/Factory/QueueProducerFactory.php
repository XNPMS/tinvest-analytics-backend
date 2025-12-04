<?php

declare(strict_types=1);

namespace System\Queue\Factory;

use System\Queue\Client\RabbitMQ;
use System\Queue\Enum\QueueName;
use System\Queue\Producer\QueueProducerInterface;
use System\Queue\Producer\RabbitMQProducer;

readonly class QueueProducerFactory
{
    public function __construct(private RabbitMQ $rabbit)
    {
    }

    public function create(QueueName $queue): QueueProducerInterface
    {
        return new RabbitMQProducer($this->rabbit, $queue);
    }
}
