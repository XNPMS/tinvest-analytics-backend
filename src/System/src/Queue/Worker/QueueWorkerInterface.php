<?php

declare(strict_types=1);

namespace System\Queue\Worker;

use Symfony\Component\Console\Output\OutputInterface;

interface QueueWorkerInterface
{
    public function execute(OutputInterface $output): void;
}
