<?php

declare(strict_types=1);

namespace System\Queue\Worker;

use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use System\Queue\Client\RabbitMQ;

abstract class AbstractWorker implements QueueWorkerInterface
{
    protected string $queueName = '';

    public function __construct(
        private readonly RabbitMQ $client,
//        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Exception
     */
    public function execute(OutputInterface $output): void
    {
        $channel = $this->client->getConnection()->channel();
        $channel->queue_declare($this->queueName, false, true, false, false);

        $callback = function (AMQPMessage $msg) use ($channel) {
            try {
                $payload = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $this->process($payload);

                // Подтверждаем обработку
                $msg->ack();
            } catch (\Throwable $e) {
                file_put_contents('/tmp/rabbitmq_worker_errors.log', $e->getMessage() . "\n", FILE_APPEND);

                // Отправляем обратно с повтором (requeue)
                $msg->nack(true);
            }
        };

        // Prefetch 1 — один воркер обрабатывает одно сообщение одновременно
        $channel->basic_qos(0, 1, false);
        $channel->basic_consume($this->queueName, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    abstract public function process(array $data): void;
}
