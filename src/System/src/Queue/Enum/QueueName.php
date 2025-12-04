<?php

declare(strict_types=1);

namespace System\Queue\Enum;

enum QueueName: string
{
    case SYNC_SELECT = 'sync_select_tinvest';
}
