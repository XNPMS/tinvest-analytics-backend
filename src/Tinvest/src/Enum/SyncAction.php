<?php

declare(strict_types=1);

namespace Tinvest\Enum;

enum SyncAction: string
{
    case OPERATIONS = 'operations';
    case PORTFOLIO  = 'portfolio';
}