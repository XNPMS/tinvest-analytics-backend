<?php

declare(strict_types=1);

namespace System\Queue\Config;

final readonly class WebSocketConfig
{
    public function __construct(
        public int $port,
    ) {
    }
}
