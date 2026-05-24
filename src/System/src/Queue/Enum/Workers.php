<?php

declare(strict_types=1);

namespace System\Queue\Enum;

use Tinvest\Worker\SyncOnboardingAccountWorker;

enum Workers: string
{
    case SYNC_ONBOARDING_ACCOUNTS = 'sync.onboarding.accounts';

    public function resolveWorker(): string
    {
        return match ($this) {
            self::SYNC_ONBOARDING_ACCOUNTS => SyncOnboardingAccountWorker::class,
        };
    }
}
