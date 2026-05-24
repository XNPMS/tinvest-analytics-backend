<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\TinvestSyncProcesses;
use Tinvest\Enum\SyncStatus;

readonly class TinvestSyncProcessesRepository extends AbstractEloquentRepository
{
    public function getEntityClass(): string
    {
        return TinvestSyncProcesses::class;
    }

    public function findLatestByAccountId(int $accountId): ?TinvestSyncProcesses
    {
        /** @var TinvestSyncProcesses */
        return $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->orderByDesc('id')
            ->first();
    }

    public function hasActiveByUserId(string $userId): bool
    {
        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->whereIn('status', [SyncStatus::PENDING->value, SyncStatus::RUNNING->value])
            ->exists();
    }

    public function findActiveByAccountId(int $accountId): ?TinvestSyncProcesses
    {
        /** @var TinvestSyncProcesses */
        return $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->whereIn('status', [SyncStatus::PENDING->value, SyncStatus::RUNNING->value])
            ->orderByDesc('id')
            ->first();
    }
}
