<?php

declare(strict_types=1);

namespace Tinvest\Worker;

use Psr\Log\LoggerInterface;
use System\Queue\Client\RabbitMQ;
use System\Queue\Enum\Workers;
use System\Queue\Worker\AbstractWorker;
use Tinvest\Message\AccountsMessage;
use Tinvest\UseCase\SyncOperationsUseCase;

class SyncTinvestAccountWorker extends AbstractWorker
{
    protected Workers $queueName = Workers::SYNC_TINVEST_ACCOUNTS;

    public function __construct(
        private readonly RabbitMQ $rabbitMQ,
        private readonly LoggerInterface $logger,
        private readonly SyncOperationsUseCase $syncOperationsUseCase,
    ) {
        parent::__construct($this->rabbitMQ, $this->logger);
    }

    public function process(array $payload): void
    {
        $accountsMessage = AccountsMessage::fromArray($payload);

        $this->syncOperationsUseCase->execute($accountsMessage, $payload['message_id']);

    }
}
