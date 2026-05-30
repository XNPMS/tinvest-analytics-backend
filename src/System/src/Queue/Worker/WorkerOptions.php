<?php

declare(strict_types=1);

namespace System\Queue\Worker;

final readonly class WorkerOptions
{
    public function __construct(
        public int $maxRetries = 0,
        public int $retryDelay = 0,
        public bool $saveFailure = false,
    ) {
    }
}