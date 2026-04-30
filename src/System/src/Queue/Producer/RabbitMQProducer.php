<?php

declare(strict_types=1);

namespace System\Queue\Producer;

use Exception;
use JsonException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use System\Queue\Client\RabbitMQ;
use System\Queue\Enum\Workers;
use Tinvest\Message\MessageInterface;

readonly class RabbitMQProducer implements QueueProducerInterface
{
    private AMQPChannel $channel;

    /**
     * @throws Exception
     */
    public function __construct(
        private RabbitMQ $rabbit,
        private Workers  $queue,
    ) {
        $this->channel = $this->rabbit->getConnection()->channel();
        $this->channel->queue_declare(
            queue: $this->queue->value,
            durable: true,
            auto_delete: false
        );

//        $this->channel->confirm_select();
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function produce(MessageInterface $msg): string
    {
        $jobId = sprintf('%s-%s-%s', $msg->userId, time(), bin2hex(random_bytes(4)));
        $amqpMsg = new AMQPMessage(
            json_encode($msg->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $jobId,
            ]
        );

        $this->channel->basic_publish($amqpMsg, routing_key: $this->queue->value);
//        $this->channel->wait_for_pending_acks();

        return $jobId;
    }

    public function __destruct()
    {
        $this->channel->close();
    }
}
