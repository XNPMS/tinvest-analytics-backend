<?php

declare(strict_types=1);

namespace Tinvest\Worker;

use System\Queue\Enum\QueueName;
use System\Queue\Worker\AbstractWorker;
use Tinvest\Message\AccountsMessage;

class SyncTinvestAccountWorker extends AbstractWorker
{
    protected QueueName $queueName = QueueName::SYNC_TINVEST_ACCOUNTS;

    public function process(array $payload): void
    {
        $accountsMessage = AccountsMessage::fromArray($payload);
    }
}
