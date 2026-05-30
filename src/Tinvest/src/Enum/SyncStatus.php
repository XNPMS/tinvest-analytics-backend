<?php

declare(strict_types=1);

namespace Tinvest\Enum;

enum SyncStatus: int
{
    case PENDING = 0;
    case RUNNING = 1;
    case COMPLETED = 2;
    case FAILED = 3;
}
