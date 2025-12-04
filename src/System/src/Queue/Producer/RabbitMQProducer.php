<?php

declare(strict_types=1);

namespace System\Queue\Producer;

use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use System\Queue\Client\RabbitMQ;
use System\Queue\Enum\QueueName;

readonly class RabbitMQProducer implements QueueProducerInterface
{
    public function __construct(
        private RabbitMQ $rabbit,
        private QueueName $queue
    ) {
    }

    /**
     * @throws JsonException
     */
    public function produce(array $data): void
    {
        $channel = $this->rabbit->getConnection()->channel();

        // создаём очередь, если ее нет (durable)
        $channel->queue_declare($this->queue->value, false, true, false, false);

        $channel->basic_publish(
            msg: new AMQPMessage(
                json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ['delivery_mode' => 2] // persistent
            ),
            routing_key: $this->queue->value
        );

        $channel->close();
    }
}
