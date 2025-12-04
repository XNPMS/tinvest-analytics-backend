<?php

declare(strict_types=1);

namespace Tinvest\Worker;

use System\Queue\Enum\QueueName;
use System\Queue\Worker\AbstractWorker;

class SyncStateAccountTinvestWorker extends AbstractWorker
{
    protected string $queueName = QueueName::SYNC_SELECT->value;

    public function process(array $data): void
    {
    }
}
