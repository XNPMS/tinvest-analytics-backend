<?php

declare(strict_types=1);

namespace System\Queue\Worker;

use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use System\Queue\Client\RabbitMQ;
use System\Queue\Enum\Workers;

abstract class AbstractWorker implements QueueWorkerInterface
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';
    private const HEADER_RETRY_COUNT = 'x-retry-count';

    protected Workers $queueName;

    public function __construct(
        private readonly RabbitMQ $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Exception
     */
    public function execute(OutputInterface $output, WorkerOptions $options): void
    {
        $channel = $this->client->getConnection(true)->channel();
        $channel->queue_declare($this->queueName->value, false, true, false, false);

        if ($options->saveFailure) {
            $failureQueue = sprintf('%s.failed', $this->queueName->value);
            $channel->queue_declare($failureQueue, false, true, false, false);
        }

        $callback = function (AMQPMessage $msg) use ($options): void {
            try {
                $payload = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $this->process(['message_id' => $msg->get_properties()['message_id'] ?? ''] + $payload);
                $msg->ack();
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    '[%s] Worker %s processing error',
                    date(self::DATE_FORMAT),
                    static::class
                ), [
                    'exception_message' => $e->getMessage(),
                    'message_id' => $msg->get_properties()['message_id'] ?? null,
                ]);

                $retryCount = $this->getRetryCount($msg);

                if ($retryCount < $options->maxRetries) {
                    $this->republish($msg, $retryCount + 1, $options->retryDelay);
                } elseif ($options->saveFailure) {
                    $this->publishToFailureQueue($msg);
                }

                try {
                    $msg->nack(false);
                } catch (\Throwable) {
                    // Соединение упало во время retry delay — брокер уже вернул сообщение в очередь
                }
            }
        };

        // Prefetch 1 - один воркер обрабатывает одно сообщение одновременно
        $channel->basic_qos(0, 1, false);
        $channel->basic_consume($this->queueName->value, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            try {
                $channel->wait(null, true);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('[%s] Worker %s channel error', date(self::DATE_FORMAT), static::class), [
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }
    }

    abstract public function process(array $payload): void;

    private function getRetryCount(AMQPMessage $msg): int
    {
        $headers = $msg->get_properties()['application_headers'] ?? null;
        if (!$headers instanceof AMQPTable) {
            return 0;
        }

        return (int)($headers->getNativeData()[self::HEADER_RETRY_COUNT] ?? 0);
    }

    private function republish(AMQPMessage $msg, int $retryCount, int $delaySeconds): void
    {
        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }

        $headers = new AMQPTable([self::HEADER_RETRY_COUNT => $retryCount]);
        $properties = ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, 'application_headers' => $headers];
        $originalMessageId = $msg->get_properties()['message_id'] ?? null;
        if ($originalMessageId !== null) {
            $properties['message_id'] = $originalMessageId;
        }
        $newMsg = new AMQPMessage($msg->getBody(), $properties);

        $channel = $this->client->getConnection(true)->channel();
        $channel->basic_publish($newMsg, '', $this->queueName->value);
        $channel->close();

        $this->logger->info(sprintf(
            '[%s] Worker %s retry %d/%d scheduled',
            date(self::DATE_FORMAT),
            static::class,
            $retryCount,
            $retryCount
        ));
    }

    private function publishToFailureQueue(AMQPMessage $msg): void
    {
        $failureQueue = sprintf('%s.failed', $this->queueName->value);
        $newMsg = new AMQPMessage($msg->getBody(), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $channel = $this->client->getConnection(true)->channel();
        $channel->basic_publish($newMsg, '', $failureQueue);
        $channel->close();

        $this->logger->warning(sprintf(
            '[%s] Worker %s message moved to failure queue %s',
            date(self::DATE_FORMAT),
            static::class,
            $failureQueue
        ));
    }
}