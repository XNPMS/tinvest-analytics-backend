<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use Illuminate\Support\Collection;
use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\TinvestAccount;

readonly class TinvestAccountRepository extends AbstractEloquentRepository
{
    private const SAVE_BATCH_SIZE = 500;
    private const MAX_LIMIT_ACCOUNT = 50;

    public function getEntityClass(): string
    {
        return TinvestAccount::class;
    }

    public function createTinvestAccounts(string $userId, array $accounts): void
    {
        $now = date('Y-m-d H:i:s');
        $accounts = array_map(
            static fn(array $account): array => $account + [
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $accounts
        );

        foreach (array_chunk($accounts, self::SAVE_BATCH_SIZE) as $chunkAccounts) {
            $this->createQueryBuilder()
                ->upsert(
                    $chunkAccounts,
                    'account_id',
                    ['user_id', 'account_id', 'type', 'status', 'name', 'opened_date', 'access_level'],
                );
        }
    }

    public function getTinvestAccountByUserIdAndAccountId(string $userId, string $accountId): ?TinvestAccount
    {
        /** @var TinvestAccount */
        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->where('account_id', '=', $accountId)
            ->first();
    }

    public function getTinvestAccountByIdAndUserId(int $brokerAccountId, string $userId): ?TinvestAccount
    {
        /** @var TinvestAccount */
        return $this->createQueryBuilder()
            ->where('account_id', '=', $brokerAccountId)
            ->where('user_id', '=', $userId)
            ->first();
    }

    public function countByUserId(string $userId): int
    {
        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->count();
    }

    public function findSyncedByUserId(string $userId): Collection
    {
        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->where('is_synced', '=', true)
            ->limit(self::MAX_LIMIT_ACCOUNT)
            ->get();
    }

    public function findByIds(string $userId, array $accountIds, int $limit = self::MAX_LIMIT_ACCOUNT): Collection
    {
        if (!$accountIds) {
            return new Collection();
        }

        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->whereIn('account_id', $accountIds)
            ->limit(min($limit, self::MAX_LIMIT_ACCOUNT))
            ->get();
    }
}
