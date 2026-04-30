<?php

declare(strict_types=1);

namespace System\Queue\Enum;

use Tinvest\Worker\SyncTinvestAccountWorker;

enum Workers: string
{
    case SYNC_TINVEST_ACCOUNTS = 'sync.tinvest.accounts';

    public function resolveWorker(): string
    {
        return match ($this) {
            self::SYNC_TINVEST_ACCOUNTS => SyncTinvestAccountWorker::class,
        };
    }
}
