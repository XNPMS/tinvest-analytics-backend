<?php

declare(strict_types=1);

namespace System\Queue\Worker;

use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use System\Queue\Client\RabbitMQ;
use System\Queue\Enum\Workers;

abstract class AbstractWorker implements QueueWorkerInterface
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    protected Workers $queueName;

    public function __construct(
        private readonly RabbitMQ $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Exception
     */
    public function execute(OutputInterface $output): void
    {
        $channel = $this->client->getConnection(true)->channel();
        $channel->queue_declare($this->queueName->value, false, true, false, false);

        $callback = function (AMQPMessage $msg) use ($channel) {
            try {
                $payload = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $this->process(['message_id' => $msg->get('message_id') ?? ''] + $payload);

                // Подтверждаем обработку
                $msg->ack();
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    '[%s] Workers %s processing error',
                    date(self::DATE_FORMAT),
                    static::class
                ), [
                    'exception_message' => $e->getMessage(),
                    'message_id' => $msg->get('message_id') ,
//                    'body' => $msg->getBody(),
                ]);

                // Отправляем обратно с повтором (requeue)
                $msg->nack(false);
            }
        };

        // Prefetch 1 — один воркер обрабатывает одно сообщение одновременно
        $channel->basic_qos(0, 1, false);
        $channel->basic_consume($this->queueName->value, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait(null, true);
        }
    }

    abstract public function process(array $payload): void;
}
