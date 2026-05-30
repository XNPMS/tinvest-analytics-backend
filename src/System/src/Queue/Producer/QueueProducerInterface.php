<?php

declare(strict_types=1);

namespace System\Queue\Producer;

use Tinvest\Message\MessageInterface;

interface QueueProducerInterface
{
    public function produce(MessageInterface $msg): string;
}
